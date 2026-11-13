<?php

namespace Tests\Feature\Amira;

use App\Jobs\AnswerAmiraMessage;
use App\Jobs\IndexLibraryVersion;
use App\Models\AmiraConversation;
use App\Services\Amira\ChatPanel;
use App\Services\Amira\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

/** Findings from the step 4 final review (ADR-369). */
class ReviewFixesTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    /** These tests act as a PSP who has accepted the AMIRA agreement. */
    protected function amiraConsentByDefault(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['amira.enabled' => true, 'amira.url' => 'http://127.0.0.1:8770', 'amira.token' => str_repeat('t', 64)]);
    }

    private function reply(): array
    {
        return ['data' => ['reply' => 'Jawapan sebenar.', 'source_ids' => [], 'proposal' => null, 'mentions_outcome' => false,
            'model' => 'qwen/qwen3.5-9b', 'ai_generated' => true, 'official_decision' => false]];
    }

    public function test_stale_question_gets_fallback_and_a_new_question_is_accepted(): void
    {
        Queue::fake();
        $user = $this->applicant();
        $service = app(ConversationService::class);
        $conversation = $service->ask($user, null, 'Soalan yang tidak dijawab');
        $this->travel(10)->minutes();
        $this->assertFalse($conversation->pending());
        $service->ask($user, null, 'Soalan baharu');
        $messages = $conversation->messages()->get();
        $this->assertSame(['psp', 'amira', 'psp'], $messages->pluck('role')->all());
        $this->assertTrue($messages[1]->fallback);
    }

    public function test_stale_question_is_not_shown_as_typing(): void
    {
        $user = $this->applicant();
        AmiraConversation::for($user, null)->messages()->create(['role' => 'psp', 'body' => 'Lama']);
        $this->travel(10)->minutes();
        $this->assertFalse(app(ChatPanel::class)->forPage($user, null)['pending']);
    }

    public function test_late_job_for_stale_question_stores_fallback_without_calling_amira(): void
    {
        Http::fake();
        $conversation = AmiraConversation::for($this->applicant(), null);
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Lama']);
        $this->travel(10)->minutes();
        app()->call([new AnswerAmiraMessage($conversation->id), 'handle']);
        Http::assertNothingSent();
        $this->assertTrue($conversation->messages()->get()->last()->fallback);
    }

    public function test_answer_is_not_added_when_another_reply_landed_meanwhile(): void
    {
        $conversation = AmiraConversation::for($this->applicant(), null);
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Soalan']);
        Http::fake(function () use ($conversation) {
            // A retried attempt's failed() answered while this call was in flight.
            $conversation->messages()->create(['role' => 'amira', 'body' => 'Panduan asas', 'fallback' => true]);

            return Http::response($this->reply());
        });
        app()->call([new AnswerAmiraMessage($conversation->id), 'handle']);
        $this->assertSame(['psp', 'amira'], $conversation->messages()->pluck('role')->all());
    }

    public function test_amira_jobs_run_on_their_own_connection_and_indexing_on_its_own_queue(): void
    {
        $answer = new AnswerAmiraMessage('01JABCDEFGHJKMNPQRSTVWXYZ0');
        $index = new IndexLibraryVersion(1);
        $this->assertSame('amira', $answer->connection);
        $this->assertSame('amira', $answer->queue);
        $this->assertSame('amira', $index->connection);
        $this->assertSame('amira-index', $index->queue);
        $this->assertGreaterThan(max($answer->timeout, $index->timeout), config('queue.connections.amira.retry_after'));
    }

    public function test_unverified_account_gets_the_reason_as_json(): void
    {
        $this->actingAs($this->applicant('baru@example.test', verified: false));
        $this->postJson(route('portal.amira.ask'), ['question' => 'Hai'])
            ->assertForbidden()->assertJson(['message' => 'Sahkan emel anda melalui halaman Akaun saya sebelum berbual dengan AMIRA.']);
    }

    public function test_new_draft_page_fills_the_form_instead_of_reloading_it(): void
    {
        $user = $this->applicant();
        $conversation = AmiraConversation::for($user, null);
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Tambah 1 tingkat']);
        $conversation->messages()->create(['role' => 'amira', 'body' => 'Baik.', 'proposal' => ['work_type' => 'tambahan']]);
        $this->actingAs($user)->get(route('portal.drafts.create'))->assertOk()
            ->assertSee('data-amira-apply=', false)
            ->assertDontSee('prefill%5Bwork_type%5D', false);
    }
}
