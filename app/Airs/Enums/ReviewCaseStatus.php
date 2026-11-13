<?php

declare(strict_types=1);

namespace App\Airs\Enums;

enum ReviewCaseStatus: string
{
    case Uploaded = 'UPLOADED';
    case Extracting = 'EXTRACTING';
    case Inventory = 'INVENTORY';
    case ExtractingText = 'EXTRACTING_TEXT';
    case Rendering = 'RENDERING';
    case Classifying = 'CLASSIFYING';
    case ExtractingProfile = 'EXTRACTING_PROFILE';
    case EvaluatingRules = 'EVALUATING_RULES';
    case Consolidating = 'CONSOLIDATING';
    case GeneratingReport = 'GENERATING_REPORT';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
