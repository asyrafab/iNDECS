<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Amira\ChatPanel;
use App\Services\Amira\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The PSP's conversation with AMIRA in the Kaunter Maya panel (ADR-369). Owner-only; foreign drafts are 404. */
final class AmiraChatController extends Controller
{
    public function __construct(private readonly ConversationService $conversations, private readonly ChatPanel $panel) {}

    public function thread(Request $request, ?string $draft = null): Response
    {
        $chat = $this->panel->forPage($request->user(), $this->draft($request, $draft), $request->boolean('form')) ?? abort(404);

        return response()->view('partials.amira-thread', ['chat' => $chat]);
    }

    public function ask(Request $request, ?string $draft = null): Response|JsonResponse
    {
        $owned = $this->draft($request, $draft);
        abort_unless(config('amira.enabled'), 404);
        // The portal renders HTML errors by default; the chat box is a background request, so answer in JSON.
        try {
            $question = $request->input('question');
            if ($question !== null && ! is_string($question)) {
                throw ValidationException::withMessages(['question' => 'Soalan mesti berupa teks.']);
            }
            $this->conversations->ask($request->user(), $owned, (string) $question);
        } catch (ConflictHttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (HttpException $exception) {
            // e.g. an unverified account: the PSP must see why, not a generic error.
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first(), 'errors' => $exception->errors()], 422);
        }

        return response()->view('partials.amira-thread', ['chat' => $this->panel->forPage($request->user(), $owned, $request->boolean('form'))], 201);
    }

    /** One-time agreement (ADR-369): recorded once with an audit entry; agreeing again keeps the first date. */
    public function consent(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->amira_consented_at === null) {
            $user->forceFill(['amira_consented_at' => now()])->save();
            AuditLog::query()->create(['auditable_type' => User::class, 'auditable_id' => (string) $user->id, 'event' => 'amira_consent_given',
                'actor_id' => $user->id, 'before_values' => null, 'after_values' => ['agreement' => 'amira-2026-11-06'], 'ip_address' => $request->ip()]);
        }

        return redirect()->back()->with('status', 'Terima kasih. AMIRA kini boleh membantu menggunakan maklumat akaun anda.');
    }

    private function draft(Request $request, ?string $draft): ?ApplicantDraft
    {
        return $draft === null ? null : ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draft);
    }
}
