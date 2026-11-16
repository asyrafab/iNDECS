<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Support\Facades\DB;

/**
 * Fills a draft's still-empty details from what DraftParticularsReader found in one of its documents.
 * Never overwrites the applicant: the title only while provisional, other fields only while empty.
 * A filled value stays unconfirmed (prefill_sources) until the applicant saves the draft.
 */
final class DraftPrefiller
{
    /** The checklist item that holds the cover letter. */
    private const COVER_LETTER_ITEM = 'borang_pengemukaan';

    /** @param array{title?: string, site_address?: string, lighting_types?: list<string>} $found */
    public function apply(ApplicantDraftDocument $document, array $found): bool
    {
        if ($found === []) {
            return false;
        }

        return DB::transaction(function () use ($document, $found): bool {
            $draft = ApplicantDraft::query()->lockForUpdate()->find($document->draft_id);
            if ($draft === null || ! PaidCheckJourneyService::draftEditable($draft)) {
                return false;
            }
            if ($draft->application_type_id !== 'LJUP') {
                unset($found['lighting_types']);
            }
            $preferred = str_contains(mb_strtoupper($document->original_name), 'PERMOHONAN') || $document->item_code === self::COVER_LETTER_ITEM;
            $sources = $draft->prefill_sources ?? [];
            $changed = false;
            foreach ($found as $field => $value) {
                $source = $sources[$field] ?? null;
                $open = $field === 'title' ? $draft->title_provisional : ($source !== null || in_array($draft->{$field}, [null, '', []], true));
                // A field filled earlier may be replaced only by a preferred file over a non-preferred one, or by the same file.
                $replaceable = $source === null || $source['document_id'] === $document->id || ($preferred && ! $source['preferred']);
                if (! $open || ! $replaceable || $draft->{$field} === $value) {
                    continue;
                }
                $draft->{$field} = $value;
                $sources[$field] = ['document_id' => $document->id, 'name' => $document->original_name, 'preferred' => $preferred];
                $changed = true;
            }
            if (! $changed) {
                return false;
            }
            $draft->prefill_sources = $sources;
            $draft->version++;
            $draft->save();
            DB::table('core.applicant_draft_revisions')->insert([
                'draft_id' => $draft->id, 'actor_id' => $draft->user_id, 'version' => $draft->version,
                'snapshot' => json_encode($draft->only(['application_type_id', 'title', 'site_address', 'notes', ...ApplicantDraft::PARTICULARS, 'lighting_types'])
                    + ['prefill' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return true;
        });
    }
}
