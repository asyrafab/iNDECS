<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Enums\AmiraMood;
use App\Models\ApplicantDraft;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the applicant's "Fail saya" board and AMIRA's opening state.
 * Only the owner's preparation drafts exist so far; formal files join the
 * action/closed sections in later increments.
 */
final class PortalBoard
{
    public const ACTIVE_LIMIT = 20;

    /**
     * @return array{sections: array{action: Collection, active: Collection, closed: Collection}, draftCount: int, todos: list<array{text: string, href: string}>, mood: AmiraMood, greeting: string}
     */
    public function for(User $user, bool $justSaved = false): array
    {
        $drafts = ApplicantDraft::query()->ownedBy($user);
        $todos = [];
        if (! $user->email_verified_at) {
            $todos[] = ['text' => 'Sahkan emel anda untuk mula menyediakan permohonan.', 'href' => route('portal.account')];
        }

        return [
            'sections' => [
                'action' => collect(),
                'active' => (clone $drafts)->with('applicationType')
                    ->orderByDesc('updated_at')->orderByDesc('id')->limit(self::ACTIVE_LIMIT)->get(),
                'closed' => collect(),
            ],
            'draftCount' => (clone $drafts)->count(),
            'todos' => $todos,
            'mood' => $todos !== [] ? AmiraMood::Alert : ($justSaved ? AmiraMood::Happy : AmiraMood::Calm),
            'greeting' => 'Selamat datang, '.rtrim(trim($user->name), '.').'.',
        ];
    }
}
