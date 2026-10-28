<?php

namespace App\Services;

use Carbon\CarbonInterface;

class PkmReferenceGenerator
{
    public function next(CarbonInterface $submissionDate): string
    {
        return app(ApplicationReferenceGenerator::class)->next('PKM', $submissionDate);
    }
}
