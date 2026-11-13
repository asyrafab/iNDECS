<?php

declare(strict_types=1);

namespace App\Airs\Enums;

enum EvaluationMethod: string
{
    case Deterministic = 'DETERMINISTIC';
    case AiText = 'AI_TEXT';
    case AiVision = 'AI_VISION';
    case CrossDocument = 'CROSS_DOCUMENT';
    case Manual = 'MANUAL';
}
