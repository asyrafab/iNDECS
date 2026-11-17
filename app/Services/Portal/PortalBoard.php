<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\AmiraMood;
use App\Enums\PaidCheckState;
use App\Enums\PrecheckLabel;
use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Support\Collection;

/**
 * Builds the applicant's "Fail saya" board and AMIRA's opening state.
 * Drafts with pre-check items needing the PSP move to "Perlu tindakan anda" (ADR-363);
 * formal files join the action/closed sections in later increments.
 */
final class PortalBoard
{
    public const ACTIVE_LIMIT = 20;

    public function __construct(private readonly DraftChecklist $checklists) {}

    /**
     * @return array{sections: array{action: Collection, active: Collection, closed: Collection}, draftCount: int, todos: list<array{text: string, href: string}>, progress: array<string, array{ready: int, total: int}>, mood: AmiraMood, greeting: string}
     */
    public function for(User $user, bool $justSaved = false, ?ApplicantDraft $focus = null): array
    {
        $drafts = ApplicantDraft::query()->ownedBy($user);
        $todos = [];
        if (! $user->email_verified_at) {
            $todos[] = ['text' => 'Sahkan emel anda untuk mula menyediakan permohonan.', 'href' => route('portal.account')];
        }
        $shown = (clone $drafts)->with('applicationType')->orderByDesc('updated_at')->orderByDesc('id')->limit(self::ACTIVE_LIMIT)->get();
        $progress = [];
        $action = collect();
        $active = collect();
        foreach ($shown as $draft) {
            // Bounded: at most ACTIVE_LIMIT checklists per page view.
            $list = $this->checklists->for($draft);
            $needs = count(array_filter($list['items'], fn (array $item) => $item['label']->needsAction()));
            if ($list['available']) {
                $progress[$draft->id] = ['ready' => $list['required_ready'], 'total' => $list['required_total']];
            }
            $journey = $draft->journey()->first();
            if ($journey?->state === PaidCheckState::AwaitingApplicant) {
                $action->push($draft);
                $todos[] = ['text' => "{$draft->title}: keputusan semakan AIRS sedia", 'href' => route('portal.drafts.edit', $draft).'#semakan-airs'];
            } elseif ($journey !== null) {
                $active->push($draft);
            } elseif ($needs > 0) {
                $action->push($draft);
                $todos[] = ['text' => "{$draft->title}: {$needs} dokumen perlu tindakan", 'href' => route('portal.drafts.edit', $draft).'#dokumen'];
            } else {
                $active->push($draft);
            }
        }
        $focusList = $focus ? $this->checklists->for($focus) : null;
        $focusPending = $focusList !== null && collect($focusList['items'])->contains(fn (array $item) => $item['label'] === PrecheckLabel::Pending);

        return [
            'sections' => ['action' => $action, 'active' => $active, 'closed' => collect()],
            'draftCount' => (clone $drafts)->count(),
            'todos' => $todos,
            'progress' => $progress,
            'mood' => match (true) {
                $focusPending => AmiraMood::Think,
                $todos !== [] => AmiraMood::Alert,
                $justSaved || ($focusList['ready'] ?? false) => AmiraMood::Happy,
                default => AmiraMood::Calm,
            },
            'greeting' => 'Selamat datang, '.rtrim(trim($user->name), '.').'.',
        ];
    }
}
