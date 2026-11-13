<?php

declare(strict_types=1);

namespace App\Airs\Enums;

enum FindingStatus: string
{
    case Complied = 'DITUNAIKAN';
    case NotComplied = 'BELUM_DITUNAIKAN';
    case NotApplicable = 'TIDAK_BERKAITAN';
    case NotDetected = 'TIDAK_DAPAT_DIKESAN';
    case ManualReview = 'PERLU_SEMAKAN_MANUAL';
    case OfficerManualReview = 'PERLU_SEMAKAN_MANUAL_PEGAWAI';
}
