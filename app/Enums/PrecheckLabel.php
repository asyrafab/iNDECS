<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Free pre-check labels (integration contract §3, ADR-363). Preparation labels only:
 * never an approval, rejection, score or technical finding.
 */
enum PrecheckLabel: string
{
    case Attached = 'Dilampirkan';
    case Missing = 'Belum dilampirkan';
    case Unreadable = 'Tidak dapat dibaca';
    case NeedsConfirmation = 'Perlu pengesahan pemohon';
    case Pending = 'Sedang disemak';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Attached => 'status-compliant',
            self::Missing => 'status-pending',
            self::Unreadable => 'status-critical',
            self::NeedsConfirmation => 'status-attention',
            self::Pending => 'status-info',
        };
    }

    public function needsAction(): bool
    {
        return $this === self::Unreadable || $this === self::NeedsConfirmation;
    }
}
