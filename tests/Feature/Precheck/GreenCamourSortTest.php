<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Contracts\DocumentScanner;
use App\Models\ApplicantDraftDocument;
use App\Models\User;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\DraftDropService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload-first sorting on the real 12-PDF Green Camour LJUP package with the real local reader (pdftotext/Ghostscript/Tesseract).
 * The package is not in git; set PLAN_REVIEW_GREEN_CAMOUR_PATH to its folder of PDFs or to the ZIP.
 */
final class GreenCamourSortTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    public function test_pilot_package_sorts_with_real_local_reader(): void
    {
        $fixture = (string) env('PLAN_REVIEW_GREEN_CAMOUR_PATH', '');
        if ($fixture === '' || ! file_exists($fixture)) {
            self::markTestSkipped('Set PLAN_REVIEW_GREEN_CAMOUR_PATH to run the real pilot sorting test.');
        }
        $this->seed();
        Storage::fake('local');
        config(['queue.default' => 'sync']);
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
        $admin = User::factory()->create(['department_id' => 'JKEJ', 'role' => 'system_admin']);
        app(\App\Services\DocumentCatalogueService::class)->publish('LJUP', json_decode((string) file_get_contents(database_path('catalogues/ljup.v1.json')), true), $admin);
        $owner = $this->applicant();
        $drops = app(DraftDropService::class);
        $draft = $drops->createDraft($owner, 'LJUP');

        $paths = is_dir($fixture) ? (glob(rtrim($fixture, '/\\').'/*.pdf') ?: []) : [$fixture];
        sort($paths);
        $accepted = 0;
        foreach ($paths as $path) {
            $mime = str_ends_with(strtolower($path), '.zip') ? 'application/zip' : 'application/pdf';
            $result = $drops->accept($owner, $draft, new UploadedFile($path, basename($path), $mime, null, true));
            $accepted += count($result->accepted);
            $this->assertSame([], $result->rejected, 'Rejected: '.json_encode($result->rejected));
        }

        $this->assertSame(12, $accepted);
        $documents = ApplicantDraftDocument::query()->where('draft_id', $draft->id)->orderBy('original_name')->get();
        fwrite(STDERR, PHP_EOL.'Green Camour sorting:'.PHP_EOL);
        foreach ($documents as $document) {
            fwrite(STDERR, sprintf('  %-36s -> %-26s score=%s chars=%s%s', $document->original_name, $document->item_code ?? '(belum dipadankan)',
                $document->sort_score ?? '-', $document->text_chars ?? '-', PHP_EOL));
        }
        $placed = fn (string $code) => $documents->where('item_code', $code)->pluck('original_name')->all();
        $this->assertSame(['PELAN_LAMPU_JALAN_ (22).pdf'], $placed('pelan_lampu_jalan'));
        $this->assertSame(['LAIN_LAIN_LUX REPORT.pdf'], $placed('laporan_fotometrik'));
        // The payment receipt belongs to no checklist item.
        $this->assertNull($documents->firstWhere('original_name', 'LAIN_LAIN_RECEIPT RM250.pdf')?->item_code);
        // Auto-sorting never hides a file: each one is either the current file of one item or in the tray.
        $checklist = app(DraftChecklist::class)->for($draft->fresh());
        $this->assertSame(12, collect($checklist['items'])->pluck('document')->filter()->count() + count($checklist['unsorted']));
    }
}
