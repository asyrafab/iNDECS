<?php

namespace Tests\Feature\Amira;

use App\Models\LibraryDocument;
use App\Models\LibraryDocumentVersion;
use App\Models\User;
use App\Services\Amira\LibraryRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryRetrieverTest extends TestCase
{
    use RefreshDatabase;

    private User $pp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);
    }

    /** A library document whose current version holds the given chunks. */
    private function document(string $title, array $chunks, array $state = []): LibraryDocument
    {
        $state += ['visibility' => 'public', 'status' => 'active', 'types' => [], 'versions' => 1];
        $document = new LibraryDocument;
        $document->forceFill(['id' => (string) Str::ulid(), 'department_id' => 'JBANG', 'title' => $title, 'category' => 'garis_panduan',
            'visibility' => $state['visibility'], 'status' => $state['status'], 'current_version' => $state['versions'], 'created_by' => $this->pp->id])->save();
        $document->applicationTypes()->sync($state['types']);
        for ($v = 1; $v <= $state['versions']; $v++) {
            $version = new LibraryDocumentVersion;
            $version->forceFill(['document_id' => $document->id, 'version' => $v, 'original_name' => 'x.pdf', 'path' => "library/x/{$v}.pdf",
                'size_bytes' => 1, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $this->pp->id, 'created_at' => now()])->save();
            // Older versions carry "lama" text that must never be returned.
            foreach ($v === $state['versions'] ? $chunks : ['anjakan lama versi terdahulu'] as $i => $body) {
                DB::table('core.library_chunks')->insert(['version_id' => $version->id, 'document_id' => $document->id, 'chunk_no' => $i + 1, 'page' => 1, 'body' => $body]);
            }
        }

        return $document;
    }

    private function search(string $question, ?string $type = null, int $limit = 4): array
    {
        return app(LibraryRetriever::class)->search($question, $type, $limit);
    }

    public function test_best_matching_chunk_comes_first(): void
    {
        $this->document('Garis Panduan Anjakan', ['Anjakan hadapan minimum bagi teres ialah 6 meter.', 'Tempat letak kereta mengikut nisbah.']);
        $this->document('Panduan Pagar', ['Ketinggian pagar maksimum 1.8 meter.']);
        $results = $this->search('Berapa anjakan hadapan untuk teres?');
        $this->assertSame('S1', $results[0]['id']);
        $this->assertSame('Garis Panduan Anjakan', $results[0]['title']);
        $this->assertStringContainsString('Anjakan hadapan', $results[0]['excerpt']);
        $this->assertSame(1, $results[0]['version']);
        $this->assertArrayHasKey('library_document_id', $results[0]);
    }

    public function test_internal_retired_and_old_versions_are_never_returned(): void
    {
        $this->document('Dalaman', ['anjakan dalaman sulit'], ['visibility' => 'internal']);
        $this->document('Dibatalkan', ['anjakan batal'], ['status' => 'retired']);
        $this->document('Semasa', ['anjakan semasa'], ['versions' => 2]);
        $bodies = array_column($this->search('anjakan'), 'excerpt');
        $this->assertSame(['anjakan semasa'], $bodies);
    }

    public function test_type_filter_limits_to_linked_documents(): void
    {
        $this->document('Panduan TEL', ['menara anjakan'], ['types' => ['TEL']]);
        $this->document('Panduan PB', ['rumah anjakan'], ['types' => ['PB']]);
        $this->assertSame(['Panduan TEL'], array_column($this->search('anjakan', 'TEL'), 'title'));
        $this->assertCount(2, $this->search('anjakan'));
    }

    public function test_stopword_only_question_matches_nothing(): void
    {
        $this->document('Panduan', ['saya nak apa yang perlu']);
        $this->assertSame([], $this->search('saya nak apa yang perlu?'));
    }

    public function test_limit_and_excerpt_length_are_bounded(): void
    {
        $this->document('Panjang', array_fill(0, 6, 'anjakan '.str_repeat('panjang ', 300)));
        $results = $this->search('anjakan', null, 3);
        $this->assertCount(3, $results);
        $this->assertSame(['S1', 'S2', 'S3'], array_column($results, 'id'));
        foreach ($results as $result) {
            $this->assertLessThanOrEqual(1200, mb_strlen($result['excerpt']));
        }
    }
}
