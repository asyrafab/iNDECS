<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OfficerTask extends Model
{
    use HasUlids;

    protected $table = 'core.officer_tasks';

    protected $guarded = ['id'];

    public const STATUSES = ['open' => 'Belum dimulakan', 'in_progress' => 'Sedang dilaksanakan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OfficerTaskAction::class, 'task_id')->orderByDesc('revision');
    }

    protected function casts(): array
    {
        return ['revision' => 'integer', 'assignee_id' => 'integer', 'due_date' => 'date', 'completed_at' => 'datetime'];
    }
}
