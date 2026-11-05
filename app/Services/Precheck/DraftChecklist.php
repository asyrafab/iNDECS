<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Enums\PrecheckLabel;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Services\Checklist\ChecklistRuleEvaluator;
use Illuminate\Support\Facades\DB;

/**
 * The draft's required documents (published catalogue rules applied to its particulars)
 * with each item's free pre-check label (ADR-363). Uploads for items that no longer
 * apply are kept but not counted.
 */
final class DraftChecklist
{
    public function __construct(private readonly ChecklistRuleEvaluator $rules) {}

    /**
     * @return array{available: bool, items: list<array{code: string, name: string, guidance: ?string, is_required: bool, conditional: bool, keywords: list<string>, label: PrecheckLabel, document: ?ApplicantDraftDocument}>, required_total: int, required_ready: int, ready: bool}
     */
    public function for(ApplicantDraft $draft): array
    {
        $available = DB::table('core.document_catalogues')->where('application_type_id', $draft->application_type_id)->exists();
        if (! $available) {
            return ['available' => false, 'items' => [], 'required_total' => 0, 'required_ready' => 0, 'ready' => false];
        }
        $latest = ApplicantDraftDocument::query()->where('draft_id', $draft->id)->orderBy('sequence')->get()->keyBy('item_code');
        $items = $this->rules->documentsFor($draft->application_type_id, $draft->particulars())
            ->map(function (array $item) use ($latest): array {
                $document = $latest->get($item['code']);

                return $item + ['document' => $document, 'label' => self::label($document, $item['keywords'])];
            })->values()->all();
        $required = array_filter($items, fn (array $item) => $item['is_required']);
        $ready = count(array_filter($required, fn (array $item) => $item['label'] === PrecheckLabel::Attached));

        return ['available' => true, 'items' => $items, 'required_total' => count($required), 'required_ready' => $ready,
            'ready' => count($required) > 0 && $ready === count($required)];
    }

    /** @param list<string> $keywords */
    public static function label(?ApplicantDraftDocument $document, array $keywords): PrecheckLabel
    {
        return match (true) {
            $document === null => PrecheckLabel::Missing,
            $document->precheck_status === 'pending' => PrecheckLabel::Pending,
            $document->readable === false => PrecheckLabel::Unreadable,
            $document->confirmed_at !== null => PrecheckLabel::Attached,
            $document->ocr_unavailable => PrecheckLabel::NeedsConfirmation,
            $document->text_chars !== null && $document->text_chars < (int) config('kaunter.precheck.min_text_chars', 30) => PrecheckLabel::NeedsConfirmation,
            $keywords !== [] && empty($document->matched_keywords) => PrecheckLabel::NeedsConfirmation,
            default => PrecheckLabel::Attached,
        };
    }
}
