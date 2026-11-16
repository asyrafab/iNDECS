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
            try {
                $rules = DraftFileRules::forItem($draft, $winner['code']);
                DraftFileRules::assertFits(\Illuminate\Support\Facades\Storage::disk('local')->path($document->path), $rules);
            } catch (ValidationException) {
                return false;
            }
            $typeId = (int) $rules['document_type']->id;
            if (DraftChecklist::maxFilesForType($typeId) > 1) {
                // A multi-file item gains the file while it has room; nothing is ever pushed out of it.
                if ($this->refusal($draft, $document, $winner['code'], $typeId) !== null) {
                    return false;
                }
                $this->move($draft, $document, $winner['code'], $typeId, 'auto', $winner['score']);

                return true;
            }
            $current = $this->currentFiles($draft, $winner['code']);
            $newest = end($current) ?: null;
            // Never displace a file the applicant placed or confirmed.
            if ($newest !== null && ($newest->sort_source !== 'auto' || $newest->confirmed_at !== null || (int) $newest->sort_score >= $winner['score'])) {
                return false;
            }
            foreach ($current as $displaced) {
                $displaced->forceFill(['item_code' => null, 'document_type_id' => null, 'sequence' => 0])->save();
            }
            $this->move($draft, $document, $winner['code'], $typeId, 'auto', $winner['score']);

            return true;
        });
    }

    /**
     * The applicant chose the item. A multi-file item must have room and not hold the same bytes;
     * a single-file item's current file is marked replaced (kept as history).
     */
    public function place(ApplicantDraftDocument $document, string $itemCode, string $source): void
    {
        DB::transaction(function () use ($document, $itemCode, $source): void {
            $draft = ApplicantDraft::query()->lockForUpdate()->findOrFail($document->draft_id);
            $document = ApplicantDraftDocument::query()->lockForUpdate()->findOrFail($document->id);
            $typeId = (int) DraftFileRules::forItem($draft, $itemCode)['document_type']->id;
            if (DraftChecklist::maxFilesForType($typeId) > 1) {
                if (($message = $this->refusal($draft, $document, $itemCode, $typeId)) !== null) {
                    throw ValidationException::withMessages(['file' => $message]);
                }
            } else {
                self::supersede($this->currentFiles($draft, $itemCode));
            }
            $this->move($draft, $document, $itemCode, $typeId, $source, null);
        });
    }

    /** Why a multi-file item cannot take this file now (checked under the draft lock), or null. */
    public function refusal(ApplicantDraft $draft, ApplicantDraftDocument $document, string $itemCode, int $typeId): ?string
    {
        $current = $this->currentFiles($draft, $itemCode);
        if (collect($current)->contains(fn (ApplicantDraftDocument $file) => $file->id !== $document->id && hash_equals($file->sha256, (string) $document->sha256))) {
            return 'Fail yang sama telah dimuat naik.';
        }
        $max = DraftChecklist::maxFilesForType($typeId);

        return count($current) >= $max ? "Dokumen ini sudah mempunyai {$max} fail. Buang satu fail sebelum menambah." : null;
    }

    /** @param list<ApplicantDraftDocument> $files */
    public static function supersede(array $files): void
    {
        foreach ($files as $file) {
            $file->forceFill(['superseded_at' => now()])->save();
        }
    }

    /** @return list<ApplicantDraftDocument> */
    public function currentFiles(ApplicantDraft $draft, string $itemCode): array
    {
        return DraftChecklist::current(ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->get());
    }

    private function move(ApplicantDraft $draft, ApplicantDraftDocument $document, string $itemCode, int $typeId, string $source, ?int $score): void
    {
        $sequence = (int) ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->max('sequence') + 1;
        $document->forceFill(['item_code' => $itemCode, 'document_type_id' => $typeId, 'sequence' => $sequence,
            'sort_source' => $source, 'sort_score' => $score, 'superseded_at' => null])->save();
    }
}
