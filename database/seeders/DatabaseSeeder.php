<?php

namespace Database\Seeders;

use App\Models\ApplicationType;
use App\Models\Department;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $planning = Department::query()->updateOrCreate(
            ['id' => 'JPBD'],
            ['name' => 'Jabatan Perancang Bandar', 'is_active' => true],
        );

        Department::query()->updateOrCreate(
            ['id' => 'JKEJ'],
            ['name' => 'Jabatan Kejuruteraan', 'is_active' => true],
        );

        $building = Department::query()->updateOrCreate(
            ['id' => 'JBANG'],
            ['name' => 'Jabatan Bangunan', 'is_active' => true],
        );

        Department::query()->updateOrCreate(
            ['id' => 'JLAND'],
            ['name' => 'Jabatan Landskap', 'is_active' => true],
        );

        ApplicationType::query()->updateOrCreate(
            ['id' => 'PKM'],
            [
                'name' => 'Permohonan Kebenaran Merancang',
                'primary_department_id' => $planning->id,
                'is_active' => true,
            ],
        );

        collect([
            ['id' => 'PB', 'name' => 'Pelan Bangunan Penuh'],
            ['id' => 'PBK', 'name' => 'Pelan Tambahan / Ubahsuai Kecil'],
            ['id' => 'PBS', 'name' => 'Pelan Bangunan Sementara dan Permit Sementara Seksyen 19'],
            ['id' => 'PMT', 'name' => 'Permit Bangunan (Bahan Binaan, Perancah, Runtuh, Tukar Guna)'],
            ['id' => 'TEL', 'name' => 'Struktur Telekomunikasi dan Permit Boleh Baharu'],
        ])->each(function (array $type) use ($building): void {
            ApplicationType::query()->updateOrCreate(
                ['id' => $type['id']],
                [
                    'name' => $type['name'],
                    'primary_department_id' => $building->id,
                    'is_active' => true,
                ],
            );
        });

        $this->seedCorePermissions();
        $this->call(BangunanWorkflowSeeder::class);
        $this->call(BangunanDocumentWorkflowSeeder::class);

        $officer = User::query()->firstOrNew(['email' => 'pegawai@indecs.test']);
        $officer->fill([
            'department_id' => $planning->id,
            'role' => 'planning_officer',
            'is_active' => true,
            'name' => 'Pegawai Perancang Demo',
            'email_verified_at' => now(),
        ]);

        if (! $officer->exists) {
            $password = config('indecs.demo_password');

            if (app()->environment('testing')) {
                $password ??= 'password';
            }

            if (blank($password)) {
                throw new \RuntimeException('Tetapkan INDECS_DEMO_PASSWORD dalam fail .env sebelum menjalankan seed pertama.');
            }

            $officer->password = Hash::make($password);
        }

        $officer->save();

        $documentTypes = collect([
            ['code' => 'pkm_form', 'name' => 'Borang Permohonan Kebenaran Merancang', 'description' => 'Borang permohonan PKM yang lengkap dan ditandatangani.'],
            ['code' => 'land_title', 'name' => 'Hak Milik Tanah', 'description' => 'Salinan hak milik atau carian rasmi tanah.'],
            ['code' => 'planning_proposal_report', 'name' => 'Laporan Cadangan Pemajuan', 'description' => 'Laporan Cadangan Pemajuan oleh perunding berdaftar.'],
            ['code' => 'site_plan', 'name' => 'Pelan Lokasi dan Tapak', 'description' => 'Pelan lokasi serta pelan tapak cadangan.'],
            ['code' => 'development_plan', 'name' => 'Pelan Cadangan Pembangunan', 'description' => 'Pelan susun atur atau pelan cadangan pembangunan baharu.'],
            ['code' => 'prior_approval', 'name' => 'Surat Kelulusan Terdahulu', 'description' => 'Surat dan pelan kelulusan terdahulu bagi permohonan pindaan.'],
            ['code' => 'owner_consent', 'name' => 'Kebenaran Pemilik Tanah', 'description' => 'Surat kebenaran pemilik tanah jika pemohon bukan pemilik.'],
        ])->mapWithKeys(function (array $attributes): array {
            $documentType = DocumentType::query()->updateOrCreate(
                ['code' => $attributes['code']],
                [...$attributes, 'allowed_extensions' => 'pdf,jpg,jpeg,png', 'max_file_size_kb' => 20480, 'is_active' => true],
            );

            return [$attributes['code'] => $documentType];
        });

        $requirements = [
            ['code' => 'pkm_form', 'category' => null, 'required' => true, 'order' => 10],
            ['code' => 'land_title', 'category' => null, 'required' => true, 'order' => 20],
            ['code' => 'planning_proposal_report', 'category' => null, 'required' => true, 'order' => 30],
            ['code' => 'site_plan', 'category' => null, 'required' => true, 'order' => 40],
            ['code' => 'development_plan', 'category' => 'Pembangunan Baharu', 'required' => true, 'order' => 50],
            ['code' => 'prior_approval', 'category' => 'Pindaan', 'required' => true, 'order' => 50],
            ['code' => 'owner_consent', 'category' => null, 'required' => false, 'order' => 60],
        ];

        foreach ($requirements as $requirement) {
            DocumentRequirement::query()->updateOrCreate(
                [
                    'application_type_id' => 'PKM',
                    'permission_category' => $requirement['category'],
                    'permission_type' => null,
                    'document_type_id' => $documentTypes[$requirement['code']]->id,
                ],
                [
                    'is_required' => $requirement['required'],
                    'sort_order' => $requirement['order'],
                    'is_active' => true,
                ],
            );
        }

        $this->call(OscSeeder::class);
    }

    private function seedCorePermissions(): void
    {
        $now = now();

        collect([
            ['id' => 'system_admin', 'label' => 'Pentadbir Sistem'],
            ['id' => 'intake_officer', 'label' => 'Pegawai Pendaftaran'],
            ['id' => 'planning_officer', 'label' => 'Pegawai Perancang'],
            ['id' => 'senior_planning_officer', 'label' => 'Pegawai Kanan Perancang'],
            ['id' => 'building_officer', 'label' => 'Pegawai Bangunan'],
            ['id' => 'senior_building_officer', 'label' => 'Pegawai Kanan Bangunan'],
        ])->each(function (array $role) use ($now): void {
            DB::table('core.roles')->updateOrInsert(
                ['id' => $role['id']],
                [
                    'label' => $role['label'],
                    'account_type' => 'staff',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        });

        collect([
            [
                'name' => 'perancang.pkm.access',
                'label' => 'Akses modul PKM',
                'description' => 'Membenarkan kakitangan membuka ruang kerja Permohonan Kebenaran Merancang.',
            ],
            [
                'name' => 'perancang.pkm.register',
                'label' => 'Daftar dan kemas kini dokumen PKM',
                'description' => 'Membenarkan pendaftaran permohonan PKM baharu, muat naik dokumen dan penghantaran semula pindaan.',
            ],
            [
                'name' => 'perancang.pkm.review',
                'label' => 'Semakan kelengkapan PKM',
                'description' => 'Membenarkan semakan kelengkapan dokumen, pengesahan lengkap atau pemulangan untuk pindaan.',
            ],
            [
                'name' => 'bangunan.legacy.view',
                'label' => 'Carian rekod warisan Bangunan',
                'description' => 'Membenarkan carian dan semakan baca-sahaja rekod warisan MPSP_Bangunan dalam iNDECS Bangunan.',
            ],
        ])->each(function (array $permission) use ($now): void {
            DB::table('core.permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        });

        $grants = [
            'intake_officer' => ['perancang.pkm.access', 'perancang.pkm.register'],
            'planning_officer' => ['perancang.pkm.access', 'perancang.pkm.register', 'perancang.pkm.review'],
            'senior_planning_officer' => ['perancang.pkm.access', 'perancang.pkm.register', 'perancang.pkm.review'],
        ];

        foreach ($grants as $roleId => $permissions) {
            foreach ($permissions as $permissionName) {
                DB::table('core.role_permissions')->updateOrInsert(
                    [
                        'role_id' => $roleId,
                        'permission_name' => $permissionName,
                        'department_id' => 'JPBD',
                        'application_type_id' => 'PKM',
                    ],
                    ['updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        foreach (['building_officer', 'senior_building_officer'] as $roleId) {
            DB::table('core.role_permissions')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'permission_name' => 'bangunan.legacy.view',
                    'department_id' => 'JBANG',
                    'application_type_id' => null,
                ],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        foreach (['perancang.pkm.access', 'perancang.pkm.register', 'perancang.pkm.review', 'bangunan.legacy.view'] as $permissionName) {
            DB::table('core.role_permissions')->updateOrInsert(
                [
                    'role_id' => 'system_admin',
                    'permission_name' => $permissionName,
                    'department_id' => null,
                    'application_type_id' => null,
                ],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }
    }
}
