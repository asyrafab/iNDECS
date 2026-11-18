<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\ApplicationType;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class LandskapWorkflowSeeder extends Seeder
{
    private const ROLES = [
        'landscape_officer' => 'Pegawai Landskap',
        'senior_landscape_officer' => 'Pegawai Kanan Landskap',
    ];

    private const PERMISSIONS = [
        'access' => 'Akses permohonan Landskap',
        'register' => 'Daftar permohonan Landskap',
        'review' => 'Mulakan pengesahan dokumen Landskap',
        'documents.upload' => 'Muat naik dokumen Landskap',
        'documents.verify' => 'Sahkan dokumen Landskap',
        'documents.configure' => 'Sahkan katalog dokumen Landskap',
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            Department::query()->updateOrCreate(
                ['id' => 'JLAND'],
                ['name' => 'Jabatan Landskap', 'is_active' => true],
            );

            ApplicationType::query()->updateOrCreate(
                ['id' => 'PL'],
                [
                    'name' => 'Pelan Landskap',
                    'primary_department_id' => 'JLAND',
                    'is_active' => true,
                ],
            );

            foreach (self::ROLES as $id => $label) {
                DB::table('core.roles')->updateOrInsert(
                    ['id' => $id],
                    ['label' => $label, 'account_type' => 'staff', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }

            foreach (self::PERMISSIONS as $action => $label) {
                $name = 'landskap.pl.'.$action;
                DB::table('core.permissions')->insertOrIgnore([
                    'name' => $name,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // ADR-362: checklist rules (catalogue) are published by system admin only.
                $roles = $action === 'documents.configure'
                    ? ['system_admin']
                    : ['landscape_officer', 'senior_landscape_officer', 'system_admin'];

                foreach ($roles as $role) {
                    $scope = [
                        'role_id' => $role,
                        'permission_name' => $name,
                        'department_id' => $role === 'system_admin' ? null : 'JLAND',
                        'application_type_id' => $role === 'system_admin' ? null : 'PL',
                    ];
                    if (! DB::table('core.role_permissions')->where($scope)->exists()) {
                        DB::table('core.role_permissions')->insert($scope + ['created_at' => $now, 'updated_at' => $now]);
                    }
                }
            }

            DB::table('core.workflow_templates')->insertOrIgnore([
                'application_type_id' => 'PL',
                'version' => 1,
                'initial_stage' => ApplicationStage::Registration->value,
                'initial_status' => ApplicationStatus::Submitted->value,
                'registration_permission' => 'landskap.pl.register',
                'document_upload_permission' => 'landskap.pl.documents.upload',
                'document_review_permission' => 'landskap.pl.documents.verify',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $template = DB::table('core.workflow_templates')
                ->where('application_type_id', 'PL')
                ->where('version', 1)
                ->first();

            DB::table('core.workflow_templates')->where('id', $template->id)->update([
                'document_upload_permission' => 'landskap.pl.documents.upload',
                'document_review_permission' => 'landskap.pl.documents.verify',
                'updated_at' => $now,
            ]);

            foreach ([
                ['start_document_verification', 'Mulakan pengesahan dokumen', ApplicationStage::Registration->value, ApplicationStatus::Submitted->value, ApplicationStage::DocumentVerification->value, ApplicationStatus::InReview->value, 'open_document_checklist', 'landskap.pl.review'],
                ['complete_document_verification', 'Sahkan lengkap dan teruskan ke taksiran fi', ApplicationStage::DocumentVerification->value, ApplicationStatus::InReview->value, ApplicationStage::FeeAssessment->value, ApplicationStatus::AwaitingPayment->value, 'complete_document_checklist', 'landskap.pl.documents.verify'],
                ['return_document_verification', 'Pulangkan untuk pembetulan', ApplicationStage::DocumentVerification->value, ApplicationStatus::InReview->value, ApplicationStage::DocumentVerification->value, ApplicationStatus::ReturnedForAmendment->value, 'return_document_checklist', 'landskap.pl.documents.verify'],
                ['resubmit_documents', 'Hantar semula dokumen', ApplicationStage::DocumentVerification->value, ApplicationStatus::ReturnedForAmendment->value, ApplicationStage::DocumentVerification->value, ApplicationStatus::InReview->value, 'resubmit_document_checklist', 'landskap.pl.documents.upload'],
            ] as [$action, $label, $fromStage, $fromStatus, $toStage, $toStatus, $guard, $permission]) {
                DB::table('core.workflow_transitions')->insertOrIgnore([
                    'workflow_template_id' => $template->id,
                    'action' => $action,
                    'label' => $label,
                    'from_stage' => $fromStage,
                    'from_status' => $fromStatus,
                    'to_stage' => $toStage,
                    'to_status' => $toStatus,
                    'guard' => $guard,
                    'permission_name' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
