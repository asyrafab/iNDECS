<?php

declare(strict_types=1);

namespace Tests\Feature\Precheck;

use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\Precheck\DraftDropService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Upload-first part 2, Task 3: filled details are shown with their source, merged into an open form and confirmed on save. */
final class DraftPrefillPagesTest extends TestCase
{
    use BuildsPrecheck, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seedTelCatalogue();
        $this->owner = $this->applicant();
    }

    private function filledDraft(): ApplicantDraft
    {
        $draft = app(DraftDropService::class)->createDraft($this->owner, 'TEL');
        $source = ['document_id' => '01m7hmyh0vgqvhpfxh1h5nxkts', 'name' => 'SURAT PERMOHONAN.pdf', 'preferred' => true];
        $draft->forceFill(['title' => 'CADANGAN MEMBINA MENARA', 'site_address' => 'LOT 101, MUKIM 6', 'version' => 2,
            'prefill_sources' => ['title' => $source, 'site_address' => $source]])->save();

        return $draft;
    }

    public function test_form_shows_filled_title_with_source_note(): void
    {
        $draft = $this->filledDraft();

        $this->actingAs($this->owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('name="title" value="CADANGAN MEMBINA MENARA"', false)
            ->assertSee('Diisi daripada SURAT PERMOHONAN.pdf — sila semak.')
            ->assertSee('js/draft-prefill.js', false);
    }

    public function test_saving_confirms_and_clears_sources(): void
    {
        $draft = $this->filledDraft();

        $this->actingAs($this->owner)->put(route('portal.drafts.update', $draft), [
            'application_type_id' => 'TEL', 'title' => 'CADANGAN MEMBINA MENARA', 'site_address' => 'LOT 101, MUKIM 6', 'version' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $draft->refresh();
        $this->assertFalse($draft->title_provisional);
        $this->assertNull($draft->prefill_sources);
        $this->actingAs($this->owner)->get(route('portal.drafts.edit', $draft))->assertDontSee('sila semak.');
    }

    public function test_checklist_fragment_carries_prefill_and_version(): void
    {
        $draft = $this->filledDraft();

        $html = $this->actingAs($this->owner)->get(route('portal.drafts.checklist', $draft))->assertOk()->getContent();

        $this->assertSame(1, preg_match("/data-prefill='([^']*)'/", $html, $match));
        $payload = json_decode(html_entity_decode($match[1]), true);
        $this->assertSame(2, $payload['version']);
        $this->assertSame('CADANGAN MEMBINA MENARA', $payload['fields']['title']);
        $this->assertSame('SURAT PERMOHONAN.pdf', $payload['sources']['site_address']);
    }

    public function test_fragment_names_the_last_version_the_applicant_saved(): void
    {
        $draft = $this->filledDraft();
        DB::table('core.applicant_draft_revisions')->insert(['draft_id' => $draft->id, 'actor_id' => $this->owner->id,
            'version' => 2, 'snapshot' => json_encode(['title' => 'CADANGAN MEMBINA MENARA', 'prefill' => true]), 'created_at' => now()]);

        $payload = $this->payload($draft);
        $this->assertSame(1, $payload['base'], 'Only fills happened after version 1, so a form at version 1 may take version 2.');

        // A save in another tab is not a fill: a form older than it must keep getting the conflict message.
        $draft->forceFill(['version' => 3])->save();
        DB::table('core.applicant_draft_revisions')->insert(['draft_id' => $draft->id, 'actor_id' => $this->owner->id,
            'version' => 3, 'snapshot' => json_encode(['title' => 'Tajuk lain']), 'created_at' => now()]);
        $this->assertSame(3, $this->payload($draft)['base']);
    }

    private function payload(ApplicantDraft $draft): array
    {
        $html = $this->actingAs($this->owner)->get(route('portal.drafts.checklist', $draft))->assertOk()->getContent();
        preg_match("/data-prefill='([^']*)'/", $html, $match);

        return json_decode(html_entity_decode($match[1]), true);
    }

    public function test_payment_still_blocked_until_saved(): void
    {
        $draft = $this->filledDraft();

        $this->assertTrue($draft->fresh()->title_provisional);
    }
}
