<?php

namespace App\Models;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'development_case_id',
    'application_type_id',
    'application_reference',
    'external_reference',
    'title',
    'description',
    'submission_date',
    'submission_year',
    'responsible_department_id',
    'current_stage',
    'current_status',
    'assigned_to',
    'assigned_at',
    'due_date',
    'priority',
    'row_version',
])]
class Application extends Model
{
    use HasUlids;

    public function scopePkm(Builder $query): Builder
    {
        return $query->where('application_type_id', 'PKM');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'system_admin' || blank($user->district)) {
            return $query;
        }

        return $query->where(function (Builder $districtQuery) use ($user): void {
            $districtQuery
                ->whereHas('sites', fn (Builder $siteQuery) => $siteQuery->where('district', $user->district))
                ->orWhereHas('lots', fn (Builder $lotQuery) => $lotQuery->where('district', $user->district));
        });
    }

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }

    public function developmentCase(): BelongsTo
    {
        return $this->belongsTo(DevelopmentCase::class);
    }

    public function responsibleDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function pkm(): HasOne
    {
        return $this->hasOne(PkmApplication::class);
    }

    public function applicationParties(): HasMany
    {
        return $this->hasMany(ApplicationParty::class);
    }

    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'application_parties')
            ->withPivot(['role', 'is_primary', 'submitted_snapshot'])
            ->withTimestamps();
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'application_sites')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function lots(): BelongsToMany
    {
        return $this->belongsToMany(Lot::class, 'application_lots')->withTimestamps();
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(StageHistory::class)->latest();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function currentDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->where('is_current', true);
    }

    public function completenessReviews(): HasMany
    {
        return $this->hasMany(PkmCompletenessReview::class)->latest('cycle_number');
    }

    public function latestCompletenessReview(): HasOne
    {
        return $this->hasOne(PkmCompletenessReview::class)->ofMany('cycle_number', 'max');
    }

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'assigned_at' => 'datetime',
            'due_date' => 'date',
            'current_stage' => ApplicationStage::class,
            'current_status' => ApplicationStatus::class,
            'row_version' => 'integer',
        ];
    }
}
