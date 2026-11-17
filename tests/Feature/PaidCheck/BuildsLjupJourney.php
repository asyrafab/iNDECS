<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentCatalogueService;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Portal\BuildsApplicants;

/** LJUP paid-check fixtures. Requires $this->seed() and Storage::fake('local') in setUp. */
trait BuildsLjupJourney
{
    use BuildsApplicants;

    /** LJUP catalogue: pelan_lampu and laporan_fotometrik required, gambar_tapak optional. */
    protected function seedLjupCatalogue(): void
    {
        $engineer = User::factory()->create(['department_id' => 'JKEJ', 'role' => 'senior_engineering_officer']);
        app(DocumentCatalogueService::class)->publish('LJUP', ['version' => 0, 'source_reference' => 'Ujian sahaja', 'items' => [
            ['code' => 'pelan_lampu', 'name' => 'Pelan Lampu Jalan', 'is_required' => true],
            ['code' => 'laporan_fotometrik', 'name' => 'Laporan Fotometrik', 'is_required' => true],
            ['code' => 'gambar_tapak', 'name' => 'Gambar Tapak', 'is_required' => false],
        ]], $engineer);
    }

    protected function ljupDraft(User $owner, array $lightingTypes = ['BESI']): ApplicantDraft
    {
        $draft = new ApplicantDraft(['application_type_id' => 'LJUP', 'title' => 'Lampu jalan Taman Uji', 'site_address' => 'Lot 101, Mukim 6',
            'lighting_types' => $lightingTypes]);
        $draft->user_id = $owner->id;
        $draft->version = 1;
        $draft->save();

        return $draft;
    }

    /** A pre-checked, confirmed draft upload with real bytes on the fake disk. */
    protected function ljupUpload(ApplicantDraft $draft, string $code, ?string $bytes = null, array $state = []): ApplicantDraftDocument
    {
        $bytes ??= "%PDF-1.4\n% {$code} ".uniqid('', true)."\n%%EOF";
        $sequence = (int) ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $code)->max('sequence') + 1;
        $path = "drafts/{$draft->id}/{$code}/{$sequence}.pdf";
        Storage::disk('local')->put($path, $bytes);
        $document = new ApplicantDraftDocument;
        $document->forceFill(array_replace([
            'draft_id' => $draft->id, 'item_code' => $code, 'sequence' => $sequence,
            'document_type_id' => DocumentType::query()->where('code', 'kej_ljup_'.$code)->value('id'),
            'original_name' => $code.'.pdf', 'path' => $path, 'mime' => 'application/pdf',
            'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'uploaded_by' => $draft->user_id,
            'precheck_status' => 'done', 'readable' => true, 'text_chars' => 200, 'matched_keywords' => [], 'confirmed_at' => now(),
        ], $state))->save();
        // As in the app: a newer file in a single-file item marks the older one replaced.
        if ($document->item_code !== null && DraftChecklist::maxFilesForType($document->document_type_id) === 1) {
            ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $document->item_code)
                ->whereKeyNot($document->id)->whereNull('superseded_at')->update(['superseded_at' => now()]);
        }

        return $document;
    }

    /** Owner, LJUP draft with both required documents uploaded. */
    protected function readyLjupDraft(?User $owner = null): ApplicantDraft
    {
        $owner ??= $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        $this->ljupUpload($draft, 'laporan_fotometrik');

        return $draft;
    }
}
