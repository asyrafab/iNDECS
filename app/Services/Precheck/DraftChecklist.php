<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Enums\PrecheckLabel;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentRequirement;
use App\Services\Checklist\ChecklistRuleEvaluator;
use Illuminate\Support\Facades\DB;

/**
 * The draft's required documents (published catalogue rules applied to its particulars)
 * with each item's free pre-check label (ADR-363). Unsorted uploads, and the current upload
 * of an item that no longer applies, are returned as the "Belum diisih" tray.
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
            return ['available' => false, 'items' => [], 'unsorted' => [], 'required_total' => 0, 'required_ready' => 0, 'ready' => false];
        }
        $documents = ApplicantDraftDocument::query()->where('draft_id', $draft->id)->orderBy('sequence')->orderBy('created_at')->orderBy('id')->get();
        $latest = $documents->whereNotNull('item_code')->keyBy('item_code');
        $items = $this->rules->documentsFor($draft->application_type_id, $draft->particulars())
            ->map(function (array $item) use ($latest): array {
                $document = $latest->get($item['code']);

                return $item + ['document' => $document, 'label' => self::label($document, $item['keywords'])];
            })->values()->all();
        $required = array_filter($items, fn (array $item) => $item['is_required']);
        $ready = count(array_filter($required, fn (array $item) => $item['label'] === PrecheckLabel::Attached));
        // The tray: files never placed, plus the current file of any item that no longer applies.
        $applicable = array_column($items, 'code');
        $unsorted = $documents->whereNull('item_code')->values()
            ->concat($latest->reject(fn ($document, $code) => in_array($code, $applicable, true))->values())->all();

        return ['available' => true, 'items' => $items, 'unsorted' => $unsorted, 'required_total' => count($required), 'required_ready' => $ready,
            'ready' => count($required) > 0 && $ready === count($required)];
    }

    /**
     * Every active item of the type's published catalogue, regardless of the draft's particulars (sorting candidates).
     *
     * @return list<array{code: string, name: string, keywords: list<string>, document_type_id: int}>
     */
    public function catalogueItems(string $applicationTypeId): array
    {
        return DocumentRequirement::query()->with('documentType')->where('application_type_id', $applicationTypeId)
            ->where('is_active', true)->whereNull('permission_category')->whereNull('permission_type')->orderBy('sort_order')->get()
            ->map(fn (DocumentRequirement $item) => [
                'code' => \App\Services\DocumentCatalogueService::itemCode($applicationTypeId, $item->documentType->code),
                'name' => $item->documentType->name,
                'keywords' => array_values($item->precheck_keywords ?? []),
                'document_type_id' => (int) $item->document_type_id,
            ])->values()->all();
    }

    /** @param list<string> $keywords */
    public static function label(?ApplicantDraftDocument $document, array $keywords): PrecheckLabel
    {
        return match (true) {
            $document === null => PrecheckLabel::Missing,
            $document->rejected_reason !== null => PrecheckLabel::Unreadable,
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
