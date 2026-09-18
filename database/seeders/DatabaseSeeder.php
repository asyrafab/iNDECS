<?php

namespace Database\Seeders;

use App\Models\ApplicationType;
use App\Models\Department;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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

        Department::query()->updateOrCreate(
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
    }
}
