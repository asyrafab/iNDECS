<?php

declare(strict_types=1);

namespace App\Services\Amira;

use App\Models\AmiraConversation;
use App\Models\AmiraMessage;
use App\Models\ApplicantDraft;
use App\Models\User;
use Illuminate\Support\Collection;

/** What the Kaunter Maya panel needs to show the AMIRA conversation for one page (ADR-369). */
final class ChatPanel
{
    public const SHOWN = 50;

    private const PROPOSAL_ORDER = ['application_type', 'work_type', 'building_category', 'storeys', 'floor_area', 'site_conditions'];

    public function __construct(private readonly ContextBuilder $context) {}

    /**
     * $onForm: the page holds the draft form (new or saved draft), so "Guna butiran ini" fills it in place.
     *
     * @return array{page: string, fillsForm: bool, messages: Collection, pending: bool, threadUrl: string, askUrl: string, options: array, retentionMonths: int, maxChars: int}|null
     */
    public function forPage(User $user, ?ApplicantDraft $draft, bool $onForm = false): ?array
    {
        if (! config('amira.enabled')) {
            return null;
        }
        // Viewing never creates a conversation; the first question does.
        $conversation = AmiraConversation::query()->where('user_id', $user->id)
            ->when($draft, fn ($query) => $query->where('draft_id', $draft->id), fn ($query) => $query->whereNull('draft_id'))->first();
        $messages = $conversation === null ? collect() : AmiraMessage::query()->where('conversation_id', $conversation->id)
            ->orderByDesc('id')->limit(self::SHOWN)->get()->reverse()->values();

        return [
            'page' => $draft !== null ? 'draft' : 'home',
            'fillsForm' => $draft !== null || $onForm,
            'consented' => $user->amira_consented_at !== null,
            'messages' => $messages,
            'pending' => $messages->last()?->role === 'psp' && AmiraConversation::isFresh($messages->last()),
            'threadUrl' => $draft !== null ? route('portal.drafts.amira.thread', $draft) : route('portal.amira.thread', $onForm ? ['form' => 1] : []),
            'askUrl' => $draft !== null ? route('portal.drafts.amira.ask', $draft) : route('portal.amira.ask', $onForm ? ['form' => 1] : []),
            'options' => $messages->contains(fn (AmiraMessage $message) => $message->proposal !== null) ? $this->context->options() : [],
            'retentionMonths' => (int) config('amira.retention_months', 12),
            'maxChars' => (int) config('amira.max_chars', 1000),
        ];
    }

    /** The proposal in a fixed order, for links and the form filler. */
    public static function prefill(array $proposal): array
    {
        return array_filter(array_merge(array_fill_keys(self::PROPOSAL_ORDER, null), array_intersect_key($proposal, array_flip(self::PROPOSAL_ORDER))),
            fn ($value) => $value !== null && $value !== []);
    }

    /** @return list<array{0: string, 1: string}> Human-readable lines for the proposal card. */
    public static function describe(array $proposal, array $options): array
    {
        $proposal = self::prefill($proposal);
        $lines = [];
        $label = fn (string $set, string $code) => $options[$set][$code] ?? $code;
        if (isset($proposal['application_type'])) {
            $lines[] = ['Jenis permohonan', $proposal['application_type'].' · '.$label('application_types', $proposal['application_type'])];
        }
        if (isset($proposal['work_type'])) {
            $lines[] = ['Jenis kerja', $label('work_types', $proposal['work_type'])];
        }
        if (isset($proposal['building_category'])) {
            $lines[] = ['Kategori bangunan', $label('building_categories', $proposal['building_category'])];
        }
        if (isset($proposal['storeys'])) {
            $lines[] = ['Bilangan tingkat', (string) $proposal['storeys']];
        }
        if (isset($proposal['floor_area'])) {
            $lines[] = ['Keluasan lantai', $proposal['floor_area'].' m²'];
        }
        if (isset($proposal['site_conditions'])) {
            $lines[] = ['Keadaan tapak', implode(', ', array_map(fn ($code) => $label('site_conditions', $code), $proposal['site_conditions']))];
        }

        return $lines;
    }
}
