<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\BangunanApplication;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TestDocumentCatalogues
{
    public static function seed(User $actor): void
    {
        // Disposable test fixtures only. No department requirements are seeded by the application.
        foreach (BangunanApplication::TYPES as $type) {
            foreach ([true, false] as $index => $required) {
                $document = DocumentType::query()->create(['code' => 'fixture_'.strtolower($type).'_'.$index,
                    'name' => $required ? 'Dokumen wajib ujian' : 'Dokumen pilihan ujian',
                    'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_file_size_kb' => 20480, 'is_active' => true]);
                DocumentRequirement::query()->create(['application_type_id' => $type, 'document_type_id' => $document->id,
                    'is_required' => $required, 'is_active' => true, 'sort_order' => $index + 1]);
            }
            DB::table('core.document_catalogues')->insert(['application_type_id' => $type, 'version' => 1,
                'source_reference' => 'TEST ONLY: disposable checklist', 'confirmed_by' => $actor->id,
                'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
