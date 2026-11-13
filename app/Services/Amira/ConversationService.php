<?php

declare(strict_types=1);

namespace App\Services\Amira;

use App\Jobs\AnswerAmiraMessage;
use App\Models\AmiraConversation;
use App\Models\AmiraMessage;
use App\Models\ApplicantDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Accepts a PSP question for AMIRA within the limits of ADR-369 and queues the answer. */
final class ConversationService
{
    public function __construct(private readonly FallbackGuidance $fallback) {}

    public function ask(User $user, ?ApplicantDraft $draft, string $text): AmiraConversation
    {
        abort_unless($user->email_verified_at, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum berbual dengan AMIRA.');
        abort_unless($user->amira_consented_at, 403, 'Sila baca dan setuju dengan persetujuan AMIRA sebelum berbual.');
        if ($draft !== null && $draft->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(ApplicantDraft::class, [$draft->id]);
        }
        $question = trim($text);
        $max = (int) config('amira.max_chars', 1000);
        if ($question === '' || mb_strlen($question) > $max) {
            throw ValidationException::withMessages(['question' => $question === '' ? 'Tulis soalan anda dahulu.' : "Soalan terlalu panjang (maksimum {$max} aksara)."]);
        }

        return DB::transaction(function () use ($user, $draft, $question): AmiraConversation {
            // Serialise per PSP so the pending check and the daily limit cannot be raced.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $conversation = AmiraConversation::for($user, $draft);
            if ($conversation->pending()) {
                throw new ConflictHttpException('Tunggu AMIRA selesai menjawab.');
            }
            // A stale unanswered question (its worker never ran) is closed with fallback guidance before the next one.
            if ($conversation->awaiting() !== null) {
                $conversation->messages()->create(['role' => 'amira', 'body' => $this->fallback->for($conversation), 'fallback' => true]);
            }
            $limit = (int) config('amira.daily_limit', 30);
            $zone = (string) config('app.display_timezone', 'Asia/Kuala_Lumpur');
            $today = AmiraMessage::query()->where('role', 'psp')
                ->whereIn('conversation_id', AmiraConversation::query()->where('user_id', $user->id)->select('id'))
                ->where('created_at', '>=', now($zone)->startOfDay()->utc())->count();
            if ($today >= $limit) {
                throw ValidationException::withMessages(['question' => "Had {$limit} soalan sehari telah dicapai. Cuba lagi esok."]);
            }
            $conversation->messages()->create(['role' => 'psp', 'body' => $question]);
            $conversation->forceFill(['last_message_at' => now()])->save();
            AnswerAmiraMessage::dispatch($conversation->id)->afterCommit();

            return $conversation;
        });
    }
}
