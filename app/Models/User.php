<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\CorePermissionService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['department_id', 'district', 'role', 'is_active', 'name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'account_type' => 'staff',
        'is_active' => true,
        'has_password' => true,
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function canAccessPkm(): bool
    {
        return $this->canPerformPkm('perancang.pkm.access');
    }

    public function canPerformPkm(string $permission): bool
    {
        if (! $this->is_active || $this->account_type !== 'staff') {
            return false;
        }

        return app(CorePermissionService::class)->userCan($this, $permission, $this->department_id, 'PKM');
    }

    public function canPerformBangunan(string $action, string $type): bool
    {
        return in_array($type, BangunanApplication::TYPES, true)
            && app(CorePermissionService::class)->userCan($this, 'bangunan.application.'.$action, $this->department_id, $type);
    }

    public function canAccessBangunan(): bool
    {
        foreach (BangunanApplication::TYPES as $type) {
            if ($this->canPerformBangunan('access', $type)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessBangunanLegacy(): bool
    {
        if (! $this->is_active || $this->account_type !== 'staff') {
            return false;
        }

        return app(CorePermissionService::class)->userCan($this, 'bangunan.legacy.view', $this->department_id, null);
    }

    public function canAccessStaffWorkspace(): bool
    {
        return $this->canAccessPkm() || $this->canAccessBangunan() || $this->canAccessBangunanLegacy();
    }

    public function canAccessLegacyPermohonan(LegacyPermohonan $permohonan): bool
    {
        if (! $this->canAccessBangunanLegacy() || $permohonan->is_excluded_test_record) {
            return false;
        }

        if ($this->role === 'system_admin' || blank($this->district)) {
            return true;
        }

        return $permohonan->district === $this->district;
    }

    public function canAccessApplication(Application $application): bool
    {
        $canAccess = $application->application_type_id === 'PKM'
            ? $this->canAccessPkm()
            : ($application->responsible_department_id === 'JBANG' && $this->canPerformBangunan('access', $application->application_type_id));

        if (! $canAccess) {
            return false;
        }

        if ($this->role === 'system_admin' || blank($this->district)) {
            return true;
        }

        return $application->sites()->where('district', $this->district)->exists()
            || $application->lots()->where('district', $this->district)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'has_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isPublicAccount(): bool
    {
        return $this->is_active && in_array($this->account_type, ['public', 'guest'], true)
            && $this->staff_number === null && $this->department_id === null && $this->role === 'public_applicant';
    }
}
