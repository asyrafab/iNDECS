<?php

namespace Tests\Feature\Amira;

use App\Jobs\AnswerAmiraMessage;
use App\Models\AmiraConversation;
use App\Models\AmiraMessage;
use App\Models\ApplicantDraft;
use App\Models\LibraryDocument;
use App\Models\LibraryDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

class AnswerAmiraMessageTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    private User $user;

    private ApplicantDraft $draft;

    private LibraryDocument $guide;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        config(['amira.enabled' => true, 'amira.url' => 'http://127.0.0.1:8770', 'amira.token' => str_repeat('t', 64)]);
        $this->user = $this->applicant('psp.rahsia@example.test', name: 'Ahmad Rahsia');
        $this->draft = $this->telDraft($this->user, ['title' => 'Menara Rahsia Lot 77', 'site_address' => 'Jalan Rahsia 9', 'storeys' => 3]);
        $pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);
        $this->guide = new LibraryDocument;
        $this->guide->forceFill(['id' => (string) Str::ulid(), 'department_id' => 'JBANG', 'title' => 'Panduan Geran', 'category' => 'garis_panduan',
            'visibility' => 'public', 'status' => 'active', 'current_version' => 1, 'created_by' => $pp->id])->save();
        $this->guide->applicationTypes()->sync(['TEL']);
        $version = new LibraryDocumentVersion;
        $version->forceFill(['document_id' => $this->guide->id, 'version' => 1, 'original_name' => 'g.pdf', 'path' => 'library/g.pdf',
            'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $pp->id, 'created_at' => now()])->save();
        DB::table('core.library_chunks')->insert(['version_id' => $version->id, 'document_id' => $this->guide->id, 'chunk_no' => 1, 'page' => 1,
            'body' => 'Salinan geran tanah terkini mesti disertakan.']);
    }

    private function asked(string $question = 'Geran tanah perlu salinan terkini?'): AmiraConversation
    {
        $conversation = AmiraConversation::for($this->user, $this->draft);
        $conversation->messages()->create(['role' => 'psp', 'body' => $question]);

        return $conversation;
    }

    private function answer(AmiraConversation $conversation): ?AmiraMessage
    {
        app()->call([new AnswerAmiraMessage($conversation->id), 'handle']);

        return $conversation->messages()->get()->last()?->role === 'amira' ? $conversation->messages()->get()->last() : null;
    }

    private function fakeReply(array $data, int $status = 200): void
    {
        Http::fake(['127.0.0.1:8770/api/v1/conversation' => Http::response($status === 200 ? ['data' => $data + [
            'reply' => 'Ya, salinan terkini.', 'source_ids' => [], 'proposal' => null, 'mentions_outcome' => false,
            'model' => 'qwen/qwen3.5-9b', 'ai_generated' => true, 'official_decision' => false]] : ['error' => ['code' => 'MODEL_UNAVAILABLE']], $status)]);
    }

    public function test_valid_reply_is_stored_with_library_citations(): void
    {
        $this->fakeReply(['source_ids' => ['S1'], 'proposal' => ['work_type' => 'tambahan', 'storeys' => 0], 'mentions_outcome' => true]);
        $reply = $this->answer($this->asked());
        $this->assertSame('Ya, salinan terkini.', $reply->body);
        $this->assertFalse($reply->fallback);
        $this->assertTrue($reply->mentions_outcome);
        $this->assertSame('qwen/qwen3.5-9b', $reply->model);
        $this->assertNotNull($reply->latency_ms);
        $this->assertEquals([['library_document_id' => $this->guide->id, 'version' => 1, 'title' => 'Panduan Geran']], $reply->sources); // jsonb does not keep key order
        $this->assertSame(['work_type' => 'tambahan'], $reply->proposal);
        Http::assertSent(fn (Request $request) => $request['context']['sources'][0]['id'] === 'S1'
            && $request['context']['page'] === 'draft' && $request['context']['application_type']['id'] === 'TEL'
            && $request['context']['particulars']['storeys'] === 3 && count($request['context']['checklist']) === 4
            && strlen($request['subject']) === 64);
    }

    public function test_request_carries_the_psps_own_account_and_files(): void
    {
        $this->telDraft($this->applicant('lain@example.test'), ['title' => 'Draf orang lain']);
        $this->draft->forceFill(['notes' => 'Klien mahu siap Disember'])->save();
        $this->fakeReply([]);
        $this->answer($this->asked('Berapa permohonan yang ada di akaun saya?'));
        Http::assertSent(function (Request $request): bool {
            $profile = $request['context']['profile'];
            $files = $request['context']['files'];
            $draft = $files['drafts'][0];

            return $profile['name'] === 'Ahmad Rahsia' && $profile['email'] === 'psp.rahsia@example.test' && $profile['email_verified'] === true
                && $files['total'] === 1 && count($files['drafts']) === 1
                && $draft['ref'] === 'D1' && $draft['type'] === 'TEL' && $draft['title'] === 'Menara Rahsia Lot 77'
                && $draft['site_address'] === 'Jalan Rahsia 9' && $draft['notes'] === 'Klien mahu siap Disember'
                && str_contains((string) $draft['particulars'], '3 tingkat')
                && $draft['documents_ready'] === 0 && $draft['documents_required'] === 3
                && array_column($draft['outstanding'], 'name') === ['Geran tanah', 'Pelan lantai', 'Pelan struktur'];
        });
    }

    public function test_oversized_context_is_trimmed_to_fit_amira(): void
    {
        foreach (range(1, 19) as $i) {
            // Accented text is escaped to six bytes per character in JSON, so this overflows AMIRA's limit untrimmed.
            $this->telDraft($this->user, ['title' => str_repeat('é', 500), 'site_address' => str_repeat('à', 1000), 'notes' => str_repeat('ü', 3000)]);
        }
        $this->fakeReply([]);
        $this->answer($this->asked());
        Http::assertSent(fn (Request $request) => strlen($request->body()) <= 60000 && $request['context']['files']['total'] === 20
            && count($request['context']['files']['drafts']) < 20 && $request['context']['files']['drafts'][0]['notes'] === null);
    }

    public function test_citations_outside_context_are_dropped(): void
    {
        $this->fakeReply(['source_ids' => ['S1', 'S7']]);
        $this->assertCount(1, $this->answer($this->asked())->sources);
    }

    public function test_invalid_service_payload_stores_fallback(): void
    {
        $this->fakeReply(['reply' => 5]);
        $reply = $this->answer($this->asked());
        $this->assertTrue($reply->fallback);
        $this->assertStringStartsWith('Maaf, saya tidak dapat berbual sekarang.', $reply->body);
        $this->assertStringContainsString('Geran tanah', $reply->body);
        $this->assertStringEndsWith('Untuk soalan teknikal, hubungi Jabatan Bangunan MBSP.', $reply->body);
    }

    public function test_unavailable_model_stores_fallback(): void
    {
        $this->fakeReply([], 502);
        $this->assertTrue($this->answer($this->asked())->fallback);
    }

    public function test_disabled_amira_answers_with_fallback_without_calling(): void
    {
        Http::fake();
        config(['amira.enabled' => false]);
        $this->assertTrue($this->answer($this->asked())->fallback);
        Http::assertNothingSent();
    }

    public function test_failed_job_writes_fallback_reply_once(): void
    {
        $conversation = $this->asked();
        $job = new AnswerAmiraMessage($conversation->id);
        $job->failed(new RuntimeException('worker killed'));
        $job->failed(new RuntimeException('again'));
        $this->assertSame(['psp', 'amira'], $conversation->messages()->pluck('role')->all());
        $this->assertTrue($conversation->messages()->get()->last()->fallback);
    }

    public function test_request_never_carries_credentials_or_other_users(): void
    {
        $this->user->forceFill(['google_subject' => 'google-sub-rahsia-123', 'remember_token' => 'remember-rahsia-456'])->save();
        $this->telDraft($this->applicant('orang.lain@example.test', name: 'Orang Lain'), ['title' => 'Projek orang lain']);
        $this->fakeReply([]);
        $this->answer($this->asked());
        Http::assertSent(function (Request $request): bool {
            foreach ([$this->user->fresh()->password, 'google-sub-rahsia-123', 'remember-rahsia-456', 'orang.lain@example.test', 'Orang Lain', 'Projek orang lain'] as $secret) {
                if (str_contains($request->body(), (string) $secret)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_answered_conversation_is_left_alone(): void
    {
        Http::fake();
        $conversation = $this->asked();
        $conversation->messages()->create(['role' => 'amira', 'body' => 'Sudah dijawab']);
        $this->answer($conversation);
        $this->assertSame(2, $conversation->messages()->count());
        Http::assertNothingSent();
    }
}
