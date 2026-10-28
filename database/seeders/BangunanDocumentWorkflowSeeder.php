<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BangunanApplication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class BangunanDocumentWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['upload' => 'Muat naik dokumen Bangunan', 'verify' => 'Sahkan dokumen Bangunan', 'configure' => 'Sahkan katalog dokumen Bangunan'] as $action => $label) {
                $name = 'bangunan.application.documents.'.$action;
                DB::table('core.permissions')->insertOrIgnore(['name' => $name, 'label' => $label, 'created_at' => now(), 'updated_at' => now()]);
                $roles = $action === 'configure' ? ['senior_building_officer', 'system_admin'] : ['building_officer', 'senior_building_officer', 'system_admin'];
                foreach ($roles as $role) {
                    $scope = ['role_id' => $role, 'permission_name' => $name, 'department_id' => $role === 'system_admin' ? null : 'JBANG', 'application_type_id' => null];
                    if (! DB::table('core.role_permissions')->where($scope)->exists()) {
                        DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
            foreach (DB::table('core.workflow_templates')->whereIn('application_type_id', BangunanApplication::TYPES)->where('version', 1)->get() as $template) {
                DB::table('core.workflow_templates')->where('id', $template->id)->update([
                    'document_upload_permission' => 'bangunan.application.documents.upload',
                    'document_review_permission' => 'bangunan.application.documents.verify', 'updated_at' => now(),
                ]);
                DB::table('core.workflow_transitions')->where('workflow_template_id', $template->id)
                    ->where('action', 'start_document_verification')->update(['guard' => 'open_document_checklist', 'updated_at' => now()]);
                foreach ([
                    ['complete_document_verification', 'Sahkan lengkap dan teruskan ke taksiran fi', 'in_review', 'fee_assessment', 'awaiting_payment', 'complete_document_checklist', 'verify'],
                    ['return_document_verification', 'Pulangkan untuk pembetulan', 'in_review', 'document_verification', 'returned_for_amendment', 'return_document_checklist', 'verify'],
                    ['resubmit_documents', 'Hantar semula dokumen', 'returned_for_amendment', 'document_verification', 'in_review', 'resubmit_document_checklist', 'upload'],
                ] as [$action, $label, $fromStatus, $toStage, $toStatus, $guard, $permission]) {
                    DB::table('core.workflow_transitions')->insertOrIgnore([
                        'workflow_template_id' => $template->id, 'action' => $action, 'label' => $label,
                        'from_stage' => 'document_verification', 'from_status' => $fromStatus, 'to_stage' => $toStage,
                        'to_status' => $toStatus, 'guard' => $guard, 'permission_name' => 'bangunan.application.documents.'.$permission,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
