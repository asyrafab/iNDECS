<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One PSP's conversation with AMIRA on the home page (draft_id null) or on one draft (ADR-369). */
class AmiraConversation extends Model
{
    use HasUlids;

    protected $table = 'core.amira_conversations';

    protected $fillable = ['user_id', 'draft_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public static function for(User $user, ?ApplicantDraft $draft): self
    {
        return self::query()->firstOrCreate(['user_id' => $user->id, 'draft_id' => $draft?->id]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ApplicantDraft::class, 'draft_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AmiraMessage::class, 'conversation_id')->orderBy('id');
    }

    /** A question older than this without an answer is stale: its worker is gone, so it gets fallback guidance. */
    public static function staleAfterSeconds(): int
    {
        return (int) config('amira.timeout', 150) + 60;
    }

    /** The PSP's latest question when it has no reply yet (fresh or stale). */
    public function awaiting(): ?AmiraMessage
    {
        $last = AmiraMessage::query()->where('conversation_id', $this->id)->orderByDesc('id')->first();

        return $last?->role === 'psp' ? $last : null;
    }

    public static function isFresh(AmiraMessage $question): bool
    {
        return $question->created_at->gt(now()->subSeconds(self::staleAfterSeconds()));
    }

    /** A reply is pending while the PSP's latest question is unanswered and not yet stale. */
    public function pending(): bool
    {
        $question = $this->awaiting();

        return $question !== null && self::isFresh($question);
    }
}
