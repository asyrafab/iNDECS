<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A department reference document (ADR-362). Changed only through LibraryService. */
class LibraryDocument extends Model
{
    use HasUlids;

    public const CATEGORIES = [
        'garis_panduan' => 'Garis panduan',
        'piawaian' => 'Piawaian',
        'undang_undang' => 'Undang-undang & peraturan',
        'senarai_semak' => 'Senarai semak',
        'contoh' => 'Contoh / sampel',
        'lain' => 'Lain-lain',
    ];

    public const VISIBILITIES = ['public' => 'Awam', 'internal' => 'Dalaman'];

    protected $table = 'core.library_documents';

    protected $guarded = ['*'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LibraryDocumentVersion::class, 'document_id')->orderByDesc('version');
    }

    public function currentVersion(): ?LibraryDocumentVersion
    {
        return LibraryDocumentVersion::query()->where('document_id', $this->id)->where('version', $this->current_version)->first();
    }

    public function applicationTypes(): BelongsToMany
    {
        return $this->belongsToMany(ApplicationType::class, 'core.library_document_application_types', 'document_id', 'application_type_id');
    }

    /** Active, public documents linked to an application type: what the public portal may offer. */
    public function scopePublicFor(Builder $query, string $applicationTypeId): Builder
    {
        return $query->where('status', 'active')->where('visibility', 'public')
            ->whereHas('applicationTypes', fn (Builder $types) => $types->whereKey($applicationTypeId))
            ->orderBy('category')->orderBy('title');
    }

    /** Active documents (public and internal) for staff-side reference by AIRS and AMIRA. */
    public function scopeForReference(Builder $query, string $departmentId, ?string $applicationTypeId = null): Builder
    {
        return $query->where('department_id', $departmentId)->where('status', 'active')
            ->when($applicationTypeId, fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereHas('applicationTypes', fn (Builder $types) => $types->whereKey($applicationTypeId))
                ->orDoesntHave('applicationTypes')));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    protected function casts(): array
    {
        return ['current_version' => 'integer', 'retired_at' => 'datetime'];
    }
}
