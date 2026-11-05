<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApplicationType;
use App\Models\AuditLog;
use App\Models\BangunanApplication;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Checklist\ChecklistRuleEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BangunanDocumentCatalogueService
{
    /** Pre-check keywords (ADR-363): trimmed, upper-cased, de-duplicated, at least 2 characters; null when none. */
    public static function keywords(?array $keywords): ?array
    {
        $clean = collect($keywords ?? [])->map(fn ($word) => mb_strtoupper(trim((string) $word)))
            ->filter(fn (string $word) => mb_strlen($word) >= 2)->unique()->values()->all();

        return $clean === [] ? null : $clean;
    }

    public function publish(string $type, array $data, User $actor): void
    {
        abort_unless(in_array($type, BangunanApplication::TYPES, true), 404);
        abort_unless($actor->canPerformBangunan('access', $type) && $actor->canPerformBangunan('documents.configure', $type), 403);
        $data = Validator::make($data, [
            'version' => ['required', 'integer', 'min:0'], 'source_reference' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*' => ['array:code,name,is_required,guidance,conditions,precheck_keywords'],
            'items.*.precheck_keywords' => ['nullable', 'array', 'max:10'],
            'items.*.precheck_keywords.*' => ['nullable', 'string', 'max:60'],
            'items.*.code' => ['required', 'regex:/^[a-z][a-z0-9_]{0,35}$/', 'distinct'],
            'items.*.name' => ['required', 'string', 'max:255'], 'items.*.is_required' => ['required', 'boolean'],
            'items.*.guidance' => ['nullable', 'string', 'max:2000'],
        ] + ChecklistRuleEvaluator::conditionRules('items.*.conditions'))->validate();
        if (! collect($data['items'])->contains(fn ($item) => (bool) $item['is_required'])) {
            throw ValidationException::withMessages(['items' => 'Sekurang-kurangnya satu dokumen wajib diperlukan.']);
        }
        DB::transaction(function () use ($type, $data, $actor): void {
            ApplicationType::query()->whereKey($type)->where('primary_department_id', 'JBANG')->where('is_active', true)->lockForUpdate()->firstOrFail();
            $catalogue = DB::table('core.document_catalogues')->where('application_type_id', $type)->first();
            if ((int) $data['version'] !== (int) ($catalogue?->version ?? 0)) {
                throw ValidationException::withMessages(['version' => 'Katalog telah berubah. Muat semula sebelum menerbitkan versi baharu.']);
            }
            DocumentRequirement::query()->where('application_type_id', $type)->whereNull('permission_category')->whereNull('permission_type')->update(['is_active' => false]);
            foreach ($data['items'] as $index => $item) {
                $documentType = DocumentType::query()->updateOrCreate(['code' => 'bina_'.strtolower($type).'_'.$item['code']], [
                    'name' => $item['name'], 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_file_size_kb' => 20480, 'is_active' => true,
                ]);
                DocumentRequirement::query()->updateOrCreate(['application_type_id' => $type, 'permission_category' => null,
                    'permission_type' => null, 'document_type_id' => $documentType->id],
                    ['is_required' => $item['is_required'], 'sort_order' => $index + 1, 'is_active' => true,
                        'guidance' => $item['guidance'] ?? null, 'conditions' => ChecklistRuleEvaluator::normalise($item['conditions'] ?? null),
                        'precheck_keywords' => self::keywords($item['precheck_keywords'] ?? null)]);
            }
            $version = (int) ($catalogue?->version ?? 0) + 1;
            DB::table('core.document_catalogues')->updateOrInsert(['application_type_id' => $type], [
                'version' => $version, 'source_reference' => $data['source_reference'], 'confirmed_by' => $actor->id,
                'confirmed_at' => now(), 'created_at' => $catalogue?->created_at ?? now(), 'updated_at' => now(),
            ]);
            AuditLog::query()->create(['auditable_type' => ApplicationType::class, 'auditable_id' => $type,
                'event' => 'document_catalogue_published', 'actor_id' => $actor->id,
                'before_values' => ['version' => $catalogue?->version],
                'after_values' => ['version' => $version, 'source_reference' => $data['source_reference'], 'items' => $data['items']], 'ip_address' => request()?->ip()]);
        });
    }
}
