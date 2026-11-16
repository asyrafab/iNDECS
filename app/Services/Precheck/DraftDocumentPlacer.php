<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a draft document into a checklist item (upload-first drafts). Auto-placement is cautious:
 * a weak or close match, an applicant-placed file, or a better earlier match leaves the file in the tray.
 */
final class DraftDocumentPlacer
{
    /** @param list<array{code: string, score: int, name_score: int}> $ranked */
    public function autoPlace(ApplicantDraftDocument $document, array $ranked): bool
    {
        $winner = $ranked[0] ?? null;
        // The file name must support the match: LJUP forms all mention "Pelan Lampu Jalan", so text alone is not enough.
        if ($winner === null || $winner['score'] < (int) config('kaunter.precheck.sort_threshold', 3) || ($winner['name_score'] ?? 0) < 1) {
            return false;
        }
        if (isset($ranked[1]) && $winner['score'] - $ranked[1]['score'] < (int) config('kaunter.precheck.sort_margin', 2)) {
            return false;
        }

        return DB::transaction(function () use ($document, $winner): bool {
            $draft = ApplicantDraft::query()->lockForUpdate()->findOrFail($document->draft_id);
            $document = ApplicantDraftDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($document->item_code !== null) {
                return false;
            }
            $current = $this->current($draft, $winner['code']);
            if ($current !== null && ($current->sort_source !== 'auto' || (int) $current->sort_score >= $winner['score'])) {
                return false;
            }
            try {
                $rules = DraftFileRules::forItem($draft, $winner['code']);
                DraftFileRules::assertFits(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path), $rules);
            } catch (ValidationException) {
                return false;
            }
            if ($current !== null) {
                $current->forceFill(['item_code' => null, 'document_type_id' => null, 'sequence' => 0])->save();
            }
            $this->move($draft, $document, $winner['code'], (int) $rules['document_type']->id, 'auto', $winner['score']);

            return true;
        });
    }

    /** Unconditional placement (the applicant chose the item); an existing file stays as superseded history. */
    public function place(ApplicantDraftDocument $document, string $itemCode, string $source): void
    {
        DB::transaction(function () use ($document, $itemCode, $source): void {
            $draft = ApplicantDraft::query()->lockForUpdate()->findOrFail($document->draft_id);
            $rules = DraftFileRules::forItem($draft, $itemCode);
            $this->move($draft, ApplicantDraftDocument::query()->lockForUpdate()->findOrFail($document->id), $itemCode, (int) $rules['document_type']->id, $source, null);
        });
    }

    private function current(ApplicantDraft $draft, string $itemCode): ?ApplicantDraftDocument
    {
        return ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->orderByDesc('sequence')->first();
    }

    private function move(ApplicantDraft $draft, ApplicantDraftDocument $document, string $itemCode, int $typeId, string $source, ?int $score): void
    {
        $sequence = (int) ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->max('sequence') + 1;
        $document->forceFill(['item_code' => $itemCode, 'document_type_id' => $typeId, 'sequence' => $sequence,
            'sort_source' => $source, 'sort_score' => $score])->save();
    }
}
