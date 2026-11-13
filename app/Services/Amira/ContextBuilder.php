<?php

declare(strict_types=1);

namespace App\Services\Amira;

use App\Enums\PrecheckLabel;
use App\Models\AmiraConversation;
use App\Models\ApplicantDraft;
use App\Models\ApplicationType;
use App\Models\User;
use App\Services\Precheck\DraftChecklist;

/**
 * Builds the request AMIRA needs for one answer (ADR-369). It carries the signed-in PSP's own account and drafts
 * (owner decision, 2026-11-06), the current draft's checklist and public library excerpts. It never carries
 * credentials (password hash, tokens, Google subject), staff fields, document contents, or any other user's data.
 */
final class ContextBuilder
{
    /** AMIRA accepts 64 KiB; stay well inside it after JSON escaping. */
    public const MAX_REQUEST_BYTES = 60000;

    private const TEXT = 500;

    public function __construct(private readonly DraftChecklist $checklists, private readonly LibraryRetriever $library) {}

    /** @return array{request: array, sources: list<array{id: string, library_document_id: string, version: int, title: string, excerpt: string}>, options: array} */
    public function for(AmiraConversation $conversation): array
    {
        $messages = $conversation->messages()->get();
        $question = $messages->last();
        $history = $messages->slice(0, -1)->take(-1 * (int) config('amira.history', 6))->values()
            ->map(fn ($message) => ['role' => $message->role, 'text' => mb_substr($message->body, 0, 1500)])->all();
        $draft = $conversation->draft;
        $options = $this->options();
        $sources = $this->library->search($question->body, $draft?->application_type_id, (int) config('amira.sources', 4));
        $checklist = [];
        if ($draft !== null) {
            foreach (array_slice($this->checklists->for($draft)['items'], 0, 50) as $item) {
                $checklist[] = ['name' => mb_substr($item['name'], 0, 255), 'required' => (bool) $item['is_required'],
                    'label' => $item['label']->value, 'guidance' => $item['guidance'] !== null ? mb_substr($item['guidance'], 0, 2000) : null,
                    'file' => $item['document'] !== null ? mb_substr((string) $item['document']->original_name, 0, 255) : null];
            }
        }
        $type = $draft?->applicationType;

        $request = [
            'subject' => hash_hmac('sha256', 'amira:'.$conversation->user_id, (string) config('app.key')),
            'locale' => 'ms-MY',
            'question' => mb_substr($question->body, 0, (int) config('amira.max_chars', 1000)),
            'history' => $history,
            'context' => [
                'page' => $draft !== null ? 'draft' : 'home',
                'application_type' => $type !== null ? ['id' => $type->id, 'name' => mb_substr($type->name, 0, 255)] : null,
                'particulars' => $draft?->particulars(),
                'checklist' => $checklist,
                'options' => $options,
                'sources' => array_map(fn (array $source) => ['id' => $source['id'], 'title' => $source['title'], 'excerpt' => $source['excerpt']], $sources),
                'profile' => $this->profile($conversation->user),
                'files' => $this->files($conversation),
            ],
        ];

        return ['request' => $this->fit($request), 'sources' => $sources, 'options' => $options];
    }

    /** The PSP's own account details. Never the password hash, remember token, Google subject or staff fields. */
    public function profile(User $user): array
    {
        $zone = (string) config('app.display_timezone', 'Asia/Kuala_Lumpur');

        return [
            'name' => mb_substr((string) $user->name, 0, 255),
            'email' => mb_substr((string) $user->email, 0, 255),
            'email_verified' => $user->email_verified_at !== null,
            'district' => $user->district !== null && $user->district !== '' ? mb_substr((string) $user->district, 0, 100) : null,
            'member_since' => $user->created_at->timezone($zone)->toDateString(),
            'sign_in' => array_values(array_filter([$user->has_password ? 'password' : null, $user->google_subject ? 'google' : null])),
        ];
    }

    /**
     * The PSP's own drafts, newest first (D1…D20): title, address, notes, particulars, document progress and what is
     * still outstanding.
     *
     * @return array{total: int, drafts: list<array<string, mixed>>}
     */
    public function files(AmiraConversation $conversation): array
    {
        $owned = ApplicantDraft::query()->where('user_id', $conversation->user_id);
        $drafts = (clone $owned)->with('applicationType')->orderByDesc('updated_at')->orderByDesc('id')->limit(20)->get();
        $zone = (string) config('app.display_timezone', 'Asia/Kuala_Lumpur');

        return ['total' => (clone $owned)->count(), 'drafts' => $drafts->values()->map(function (ApplicantDraft $draft, int $i) use ($zone): array {
            $list = $this->checklists->for($draft);
            $outstanding = array_values(array_filter($list['items'], fn (array $item) => $item['is_required'] && $item['label'] !== PrecheckLabel::Attached));

            return ['ref' => 'D'.($i + 1), 'type' => (string) $draft->application_type_id,
                'type_name' => mb_substr((string) $draft->applicationType?->name, 0, 255),
                'title' => mb_substr((string) $draft->title, 0, self::TEXT),
                'site_address' => $this->text($draft->site_address),
                'notes' => $this->text($draft->notes),
                'particulars' => $this->describe($draft),
                'documents_ready' => $list['available'] ? $list['required_ready'] : null,
                'documents_required' => $list['available'] ? $list['required_total'] : null,
                'needs_action' => count(array_filter($list['items'], fn (array $item) => $item['label']->needsAction())),
                'outstanding' => array_map(fn (array $item) => ['name' => mb_substr($item['name'], 0, 255), 'label' => $item['label']->value],
                    array_slice($outstanding, 0, 10)),
                'updated' => $draft->updated_at->timezone($zone)->toDateString()];
        })->all()];
    }

    /** The codes a proposal may use: portal types and the configured particulars (config/kaunter.php). */
    public function options(): array
    {
        return [
            'application_types' => ApplicationType::query()->portal()->get()->mapWithKeys(fn ($type) => [$type->id => $type->name])->all(),
            'work_types' => config('kaunter.work_types', []),
            'building_categories' => config('kaunter.building_categories', []),
            'site_conditions' => config('kaunter.site_conditions', []),
        ];
    }

    /** Keep the request inside AMIRA's size limit: drop notes first, then older drafts, then checklist guidance. */
    private function fit(array $request): array
    {
        $size = fn () => strlen((string) json_encode($request));
        if ($size() > self::MAX_REQUEST_BYTES) {
            foreach ($request['context']['files']['drafts'] as $i => $draft) {
                $request['context']['files']['drafts'][$i]['notes'] = null;
            }
        }
        foreach ([10, 5] as $keep) {
            if ($size() > self::MAX_REQUEST_BYTES) {
                $request['context']['files']['drafts'] = array_slice($request['context']['files']['drafts'], 0, $keep);
            }
        }
        if ($size() > self::MAX_REQUEST_BYTES) {
            foreach ($request['context']['checklist'] as $i => $item) {
                $request['context']['checklist'][$i]['guidance'] = null;
            }
        }

        return $request;
    }

    private function text(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, self::TEXT);
    }

    private function describe(ApplicantDraft $draft): ?string
    {
        $p = $draft->particulars();
        $parts = array_filter([
            $p['work_type'] !== null ? (config('kaunter.work_types')[$p['work_type']] ?? $p['work_type']) : null,
            $p['building_category'] !== null ? (config('kaunter.building_categories')[$p['building_category']] ?? $p['building_category']) : null,
            $p['storeys'] !== null ? $p['storeys'].' tingkat' : null,
            $p['floor_area'] !== null ? $p['floor_area'].' m²' : null,
            ...array_map(fn ($code) => config('kaunter.site_conditions')[$code] ?? $code, $p['site_conditions']),
        ]);

        return $parts === [] ? null : mb_substr(implode('; ', $parts), 0, self::TEXT);
    }
}
