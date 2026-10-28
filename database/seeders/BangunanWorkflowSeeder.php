<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class BangunanWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['access' => 'Akses permohonan Bangunan', 'register' => 'Daftar permohonan Bangunan', 'review' => 'Mulakan pengesahan dokumen Bangunan'] as $action => $label) {
                $permission = 'bangunan.application.'.$action;
                DB::table('core.permissions')->insertOrIgnore([
                    'name' => $permission, 'label' => $label, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (['building_officer', 'senior_building_officer', 'system_admin'] as $role) {
                    // Preserve scoped grants; rerunning the deployment seeder adds no duplicates.
                    $scope = ['role_id' => $role, 'permission_name' => $permission,
                        'department_id' => $role === 'system_admin' ? null : 'JBANG', 'application_type_id' => null];
                    if (! DB::table('core.role_permissions')->where($scope)->exists()) {
                        DB::table('core.role_permissions')->insert($scope + ['created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }

            foreach (['PB', 'PBK', 'PBS', 'PMT', 'TEL'] as $type) {
                DB::table('core.workflow_templates')->insertOrIgnore([
                    'application_type_id' => $type, 'version' => 1,
                    'initial_stage' => ApplicationStage::Registration->value,
                    'initial_status' => ApplicationStatus::Submitted->value,
                    'registration_permission' => 'bangunan.application.register',
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $template = DB::table('core.workflow_templates')->where('application_type_id', $type)->where('version', 1)->first();
                DB::table('core.workflow_transitions')->insertOrIgnore([
                    'workflow_template_id' => $template->id, 'action' => 'start_document_verification',
                    'label' => 'Mulakan pengesahan dokumen',
                    'from_stage' => ApplicationStage::Registration->value,
                    'from_status' => ApplicationStatus::Submitted->value,
                    'to_stage' => ApplicationStage::DocumentVerification->value,
                    'to_status' => ApplicationStatus::InReview->value,
                    'permission_name' => 'bangunan.application.review',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }
}
