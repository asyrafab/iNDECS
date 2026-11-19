<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\MeetingMember;
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

    /** LJUP (Jabatan Kejuruteraan) permissions: access, review, documents.configure, fee_waive. */
    public function canPerformLjup(string $action): bool
    {
        return $this->is_active && $this->account_type === 'staff'
            // Scoped by the officer's own department (ADR-307), like PKM and Bangunan.
            && app(CorePermissionService::class)->userCan($this, 'kejuruteraan.ljup.'.$action, $this->department_id, 'LJUP');
    }

    public function canAccessKejuruteraan(): bool
    {
        return $this->canPerformLjup('access');
    }

    /** PL (Jabatan Landskap) permissions: access, register, review, documents.upload, documents.verify, documents.configure. */
    public function canPerformLandskap(string $action): bool
    {
        return $this->is_active && $this->account_type === 'staff'
            && app(CorePermissionService::class)->userCan($this, 'landskap.pl.'.$action, $this->department_id, 'PL');
    }

    public function canAccessLandskap(): bool
    {
        return $this->canPerformLandskap('access');
    }

    public function canPerformBangunan(string $action, string $type): bool
    {
        return in_array($type, BangunanApplication::TYPES, true)
            && app(CorePermissionService::class)->userCan($this, 'bangunan.application.'.$action, $this->department_id, $type);
    }

    /** ADR-362: only a Penolong Pengarah changes their own department's library. */
    public function canManageLibrary(string $departmentId): bool
    {
        return $this->role === 'penolong_pengarah'
            && $this->department_id === $departmentId
            && app(CorePermissionService::class)->userCan($this, 'library.manage');
    }

    public function canViewLibrary(): bool
    {
        return app(CorePermissionService::class)->userCan($this, 'library.view');
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

    public function canPerformOsc(string $permission, ?string $departmentId = null): bool
    {
        if (! $this->is_active || $this->account_type !== 'staff') {
            return false;
        }

        return app(CorePermissionService::class)->userCan($this, $permission, $departmentId ?? $this->department_id, null);
    }

    /**
     * Opens the eOSC+ area: holders of osc.meeting.view, and active staff who are a member, presenter or
     * proposer of any meeting. Which meetings and items they see is still decided per record by OscAccess.
     */
    public function canAccessOsc(): bool
    {
        if (! $this->is_active || $this->account_type !== 'staff') {
            return false;
        }

        return $this->canPerformOsc('osc.meeting.view') || $this->isOscParticipant();
    }

    public function isOscParticipant(): bool
    {
        return MeetingMember::query()->where('user_id', $this->id)->exists()
            || AgendaItem::query()->where(fn ($items) => $items->where('presenter_id', $this->id)->orWhere('proposed_by', $this->id))->exists();
    }

    /** Vector tiles serve the meeting map and the boundary field on registration forms. */
    public function canUseGisTiles(): bool
    {
        if (! $this->is_active || $this->account_type !== 'staff') {
            return false;
        }
        if ($this->canAccessOsc() || $this->canPerformPkm('perancang.pkm.register') || $this->canPerformLandskap('register')) {
            return true;
        }
        foreach (BangunanApplication::TYPES as $type) {
            if ($this->canPerformBangunan('register', $type)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessStaffWorkspace(): bool
    {
        return $this->canAccessPkm() || $this->canAccessBangunan() || $this->canAccessBangunanLegacy()
            || $this->canAccessKejuruteraan() || $this->canAccessLandskap() || $this->canAccessOsc()
            || $this->canManageLibrary((string) $this->department_id);
    }

    /** Default landing route after sign-in or when opening `/`, in workspace priority order. */
    public function staffHomeRoute(): string
    {
        return match (true) {
            $this->canAccessBangunan() => route('bangunan.dashboard'),
            $this->canAccessPkm() => route('dashboard'),
            $this->canAccessLandskap() => route('landskap.dashboard'),
            $this->canAccessKejuruteraan() => route('kejuruteraan.dashboard'),
            $this->canAccessBangunanLegacy() => route('bangunan.legacy.index'),
            ! $this->canAccessOsc() && $this->canViewLibrary() => route('library.index'),
            default => route('osc.meetings.index'),
        };
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
        $canAccess = match ($application->application_type_id) {
            'PKM' => $this->canAccessPkm(),
            'PL' => $application->responsible_department_id === 'JLAND' && $this->canAccessLandskap(),
            default => $application->responsible_department_id === 'JBANG' && $this->canPerformBangunan('access', $application->application_type_id),
        };

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
            'amira_consented_at' => 'datetime',
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
