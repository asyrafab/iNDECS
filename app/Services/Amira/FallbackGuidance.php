<?php

declare(strict_types=1);

namespace App\Services\Amira;

use App\Enums\PrecheckLabel;
use App\Models\AmiraConversation;
use App\Services\Portal\PortalBoard;
use App\Services\Precheck\DraftChecklist;

/** Scripted reply used whenever AMIRA or the model cannot answer (ADR-369). Built only from iNDECS records. */
final class FallbackGuidance
{
    public const OPENING = 'Maaf, saya tidak dapat berbual sekarang.';

    public const CLOSING = 'Untuk soalan teknikal, hubungi Jabatan Bangunan MBSP.';

    public function __construct(private readonly DraftChecklist $checklists, private readonly PortalBoard $board) {}

    public function for(AmiraConversation $conversation): string
    {
        return self::OPENING.' '.$this->nextStep($conversation).' '.self::CLOSING;
    }

    private function nextStep(AmiraConversation $conversation): string
    {
        $draft = $conversation->draft;
        if ($draft === null) {
            $todo = $this->board->for($conversation->user)['todos'][0] ?? null;

            return $todo !== null ? 'Langkah seterusnya: '.$todo['text'].'.' : 'Tiada tindakan diperlukan hari ini.';
        }
        $list = $this->checklists->for($draft);
        if (! $list['available']) {
            return 'Senarai semak untuk jenis permohonan ini belum tersedia.';
        }
        foreach ($list['items'] as $item) {
            if ($item['is_required'] && ! in_array($item['label'], [PrecheckLabel::Attached, PrecheckLabel::Pending], true)) {
                return 'Langkah seterusnya: '.$item['name'].' ('.$item['label']->value.').';
            }
        }

        return 'Semua dokumen wajib telah dilampirkan.';
    }
}
