<?php

namespace Tests\Feature\Portal;

use App\Models\ApplicationType;
use App\Models\Department;
use App\Models\User;
use App\Services\PublicIdentityService;

trait BuildsApplicants
{
    protected function seedPortalTypes(): void
    {
        Department::query()->create(['id' => 'JPBD', 'name' => 'Jabatan Perancang Bandar', 'is_active' => true]);
        ApplicationType::query()->create(['id' => 'PKM', 'name' => 'Kebenaran Merancang', 'primary_department_id' => 'JPBD', 'is_active' => true]);
    }

    protected function applicant(string $email = 'applicant@example.test', bool $verified = true, string $name = 'Pemohon Ujian'): User
    {
        $user = app(PublicIdentityService::class)->create($email, $name, 'long-test-password');
        // AMIRA tests opt in to the one-time AI agreement (ADR-369) by defining amiraConsentByDefault().
        $consent = method_exists($this, 'amiraConsentByDefault') && $this->amiraConsentByDefault();
        $user->forceFill(['email_verified_at' => $verified ? now() : null, 'amira_consented_at' => $consent ? now() : null])->save();

        return $user;
    }

    protected function data(): array
    {
        return ['application_type_id' => 'PKM', 'title' => 'Rumah di Lot 101', 'site_address' => 'Mukim 6, Seberang Perai', 'notes' => 'Catatan persediaan'];
    }
}
