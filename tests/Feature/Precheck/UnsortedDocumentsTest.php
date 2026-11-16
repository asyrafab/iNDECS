<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\DocumentCatalogueService;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\DraftFileRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Upload-first part 1, Task 1: unsorted draft documents and shared file rules. */
final class UnsortedDocumentsTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
    }

    private function unsorted(ApplicantDraft $draft, string $name = 'fail.pdf')
    {
        return $this->draftDocument($draft, 'x', ['item_code' => null, 'document_type_id' => null, 'sequence' => 0,
            'original_name' => $name, 'path' => "drafts/{$draft->id}/inbox/".uniqid().'.pdf', 'sha256' => hash('sha256', uniqid('', true))]);
    }

    public function test_unsorted_document_appears_in_tray_not_in_items(): void
    {
        $draft = $this->telDraft($this->applicant());
        $before = app(DraftChecklist::class)->for($draft)['required_ready'];
        $loose = $this->unsorted($draft);

        $checklist = app(DraftChecklist::class)->for($draft);

        $this->assertSame([$loose->id], array_map(fn ($d) => $d->id, $checklist['unsorted']));
        $this->assertFalse(collect($checklist['items'])->contains(fn ($item) => $item['document']?->id === $loose->id));
        $this->assertSame($before, $checklist['required_ready']);
    }

    public function test_file_in_item_that_no_longer_applies_moves_to_tray(): void
    {
        $draft = $this->telDraft($this->applicant(), ['storeys' => 4]);
        $structural = $this->draftDocument($draft, 'pelan_struktur');
        $this->assertSame([], app(DraftChecklist::class)->for($draft)['unsorted']);

        $draft->forceFill(['storeys' => 1])->save();
        $checklist = app(DraftChecklist::class)->for($draft->fresh());

        $this->assertSame([$structural->id], array_map(fn ($d) => $d->id, $checklist['unsorted']));
    }

    public function test_superseded_versions_are_not_in_the_tray(): void
    {
        $draft = $this->telDraft($this->applicant(), ['storeys' => 4]);
        $this->draftDocument($draft, 'pelan_struktur');
        $latest = $this->draftDocument($draft, 'pelan_struktur');
        $draft->forceFill(['storeys' => 1])->save();

        $this->assertSame([$latest->id], array_map(fn ($d) => $d->id, app(DraftChecklist::class)->for($draft->fresh())['unsorted']));
    }

    public function test_loosest_rules_take_largest_limit_and_union_of_types(): void
    {
        $admin = User::query()->where('role', 'system_admin')->firstOrFail();
        app(DocumentCatalogueService::class)->publish('LJUP', json_decode((string) file_get_contents(database_path('catalogues/ljup.v1.json')), true), $admin);

        $rules = DraftFileRules::loosest('LJUP');

        $this->assertSame(51200, $rules['max_kb']);
        $this->assertContains('pdf', $rules['extensions']);
        $this->assertContains('application/pdf', $rules['mimes']);
        $this->assertNotContains('zip', $rules['extensions']);
    }

    public function test_item_rules_come_from_the_item_document_type(): void
    {
        $draft = $this->telDraft($this->applicant());
        $rules = DraftFileRules::forItem($draft, 'geran');

        $this->assertSame('bina_tel_geran', $rules['document_type']->code);
        $this->assertSame((int) $rules['document_type']->max_file_size_kb, $rules['max_kb']);
    }

    public function test_catalogue_items_ignore_particulars(): void
    {
        $codes = array_column(app(DraftChecklist::class)->catalogueItems('TEL'), 'code');

        $this->assertSame(['geran', 'pelan_lantai', 'pelan_struktur', 'gambar'], $codes);
        $geran = collect(app(DraftChecklist::class)->catalogueItems('TEL'))->firstWhere('code', 'geran');
        $this->assertSame(['HAKMILIK', 'NO. LOT'], $geran['keywords']);
        $this->assertIsInt($geran['document_type_id']);
    }

    public function test_draft_title_provisional_defaults_false(): void
    {
        $draft = $this->telDraft($this->applicant());
        $this->assertFalse($draft->fresh()->title_provisional);
    }
}
