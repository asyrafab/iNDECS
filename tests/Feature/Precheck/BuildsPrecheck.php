<?php

namespace Tests\Feature\Precheck;

use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\BangunanDocumentCatalogueService;
use Tests\Feature\Portal\BuildsApplicants;

/** Shared fixtures for the free pre-check tests. Requires $this->seed() in setUp. */
trait BuildsPrecheck
{
    use BuildsApplicants;

    /** TEL catalogue: geran (required, keywords), pelan_lantai (required, no keywords), pelan_struktur (required if 3+ storeys), gambar (optional). */
    protected function seedTelCatalogue(): void
    {
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        app(BangunanDocumentCatalogueService::class)->publish('TEL', ['version' => 0, 'source_reference' => 'Ujian', 'items' => [
            ['code' => 'geran', 'name' => 'Geran tanah', 'is_required' => true, 'precheck_keywords' => ['HAKMILIK', 'NO. LOT']],
            ['code' => 'pelan_lantai', 'name' => 'Pelan lantai', 'is_required' => true],
            ['code' => 'pelan_struktur', 'name' => 'Pelan struktur', 'is_required' => true, 'conditions' => ['storeys_min' => 3]],
            ['code' => 'gambar', 'name' => 'Gambar tapak', 'is_required' => false],
        ]], $admin);
    }

    protected function telDraft(User $owner, array $particulars = []): ApplicantDraft
    {
        $draft = new ApplicantDraft(array_replace(['application_type_id' => 'TEL', 'title' => 'Menara telekomunikasi'], $particulars));
        $draft->user_id = $owner->id;
        $draft->version = 1;
        $draft->save();

        return $draft;
    }

    protected function draftDocument(ApplicantDraft $draft, string $code, array $state = []): ApplicantDraftDocument
    {
        $sequence = (int) ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $code)->max('sequence') + 1;
        $document = new ApplicantDraftDocument;
        $document->forceFill(array_replace([
            'draft_id' => $draft->id, 'item_code' => $code, 'sequence' => $sequence,
            'document_type_id' => DocumentType::query()->where('code', 'bina_tel_'.$code)->value('id'),
            'original_name' => $code.'.pdf', 'path' => "drafts/{$draft->id}/{$code}/{$sequence}.pdf", 'mime' => 'application/pdf',
            'size_bytes' => 100, 'sha256' => str_repeat((string) $sequence, 64), 'uploaded_by' => $draft->user_id,
            'precheck_status' => 'done', 'readable' => true, 'text_chars' => 200, 'matched_keywords' => [],
        ], $state))->save();

        return $document;
    }
}
