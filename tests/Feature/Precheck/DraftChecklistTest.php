<?php

namespace Tests\Feature\Precheck;

use App\Enums\PrecheckLabel;
use App\Models\ApplicantDraft;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DraftChecklistTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
    }

    public function test_no_catalogue_means_unavailable(): void
    {
        $draft = new ApplicantDraft(['application_type_id' => 'PKM', 'title' => 'PKM']);
        $draft->user_id = $this->applicant()->id;
        $draft->version = 1;
        $draft->save();
        $list = app(DraftChecklist::class)->for($draft);
        $this->assertFalse($list['available']);
        $this->assertFalse($list['ready']);
        $this->assertSame([], $list['items']);
    }

    public function test_labels_follow_document_state(): void
    {
        $draft = $this->telDraft($this->applicant());
        $cases = [
            [null, PrecheckLabel::Missing],
            [['precheck_status' => 'pending', 'readable' => null], PrecheckLabel::Pending],
            [['readable' => false], PrecheckLabel::Unreadable],
            [['matched_keywords' => []], PrecheckLabel::NeedsConfirmation],
            [['matched_keywords' => [], 'confirmed_at' => now()], PrecheckLabel::Attached],
            [['ocr_unavailable' => true, 'matched_keywords' => ['HAKMILIK']], PrecheckLabel::NeedsConfirmation],
            [['matched_keywords' => ['HAKMILIK']], PrecheckLabel::Attached],
        ];
        foreach ($cases as [$state, $expected]) {
            if ($state !== null) {
                $this->draftDocument($draft, 'geran', $state);
            }
            $item = collect(app(DraftChecklist::class)->for($draft)['items'])->firstWhere('code', 'geran');
            $this->assertSame($expected, $item['label'], 'State: '.json_encode($state));
        }
        // An item without keywords is Attached as soon as a readable file is in.
        $this->draftDocument($draft, 'pelan_lantai');
        $this->assertSame(PrecheckLabel::Attached, collect(app(DraftChecklist::class)->for($draft)['items'])->firstWhere('code', 'pelan_lantai')['label']);
    }

    public function test_ready_only_when_all_required_items_attached(): void
    {
        $draft = $this->telDraft($this->applicant());
        $this->draftDocument($draft, 'geran', ['matched_keywords' => ['NO. LOT']]);
        $list = app(DraftChecklist::class)->for($draft);
        $this->assertSame(2, $list['required_total']);
        $this->assertSame(1, $list['required_ready']);
        $this->assertFalse($list['ready']);
        $this->draftDocument($draft, 'pelan_lantai');
        $this->assertTrue(app(DraftChecklist::class)->for($draft)['ready'], 'Optional gambar missing must not block readiness.');
    }

    public function test_items_follow_particulars_and_orphan_uploads_are_not_counted(): void
    {
        $draft = $this->telDraft($this->applicant(), ['storeys' => 4]);
        $this->draftDocument($draft, 'pelan_struktur');
        $this->assertSame(3, app(DraftChecklist::class)->for($draft)['required_total']);
        $draft->forceFill(['storeys' => 2])->save();
        $list = app(DraftChecklist::class)->for($draft->fresh());
        $this->assertSame(2, $list['required_total']);
        $this->assertSame(0, $list['required_ready']);
        $this->assertNull(collect($list['items'])->firstWhere('code', 'pelan_struktur'));
    }

    public function test_latest_sequence_wins(): void
    {
        $draft = $this->telDraft($this->applicant());
        $this->draftDocument($draft, 'pelan_lantai', ['readable' => false]);
        $latest = $this->draftDocument($draft, 'pelan_lantai');
        $item = collect(app(DraftChecklist::class)->for($draft)['items'])->firstWhere('code', 'pelan_lantai');
        $this->assertSame(PrecheckLabel::Attached, $item['label']);
        $this->assertSame($latest->id, $item['document']->id);
    }
}
