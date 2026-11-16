<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['application_type_id', 'title', 'site_address', 'notes', 'work_type', 'building_category', 'storeys', 'floor_area', 'site_conditions', 'lighting_types'])]
class ApplicantDraft extends Model
{
    use HasUlids;
    use SoftDeletes;

    /** Project particulars that drive the checklist rules (ADR-362/363). */
    public const PARTICULARS = ['work_type', 'building_category', 'storeys', 'floor_area', 'site_conditions'];

    protected $table = 'core.applicant_drafts';

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDraftDocument::class, 'draft_id');
    }

    /** Preparation ends once payment or the paid review journey starts. */
    public function removable(): bool
    {
        return $this->exists && ! $this->trashed()
            && ! $this->journey()->exists()
            && ! CheckPayment::query()->where('draft_id', $this->id)->whereIn('status', ['pending', 'paid'])->exists();
    }

    /** The paid AIRS check journey, once the applicant has paid. */
    public function journey(): HasOne
    {
        return $this->hasOne(PaidCheckJourney::class, 'draft_id');
    }

    /** @return array{work_type: ?string, building_category: ?string, storeys: ?int, floor_area: ?float, site_conditions: list<string>} */
    public function particulars(): array
    {
        return [
            'work_type' => $this->work_type,
            'building_category' => $this->building_category,
            'storeys' => $this->storeys,
            'floor_area' => $this->floor_area,
            'site_conditions' => array_values($this->site_conditions ?? []),
        ];
    }

    /** Lighting types an LJUP draft declares (keys of config('airs.lighting_types')); empty for other types. */
    public function lightingTypes(): array
    {
        return array_values($this->lighting_types ?? []);
    }

    protected function casts(): array
    {
        return ['version' => 'integer', 'title_provisional' => 'boolean', 'storeys' => 'integer', 'floor_area' => 'float', 'site_conditions' => 'array', 'lighting_types' => 'array'];
    }
}
