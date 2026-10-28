<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class ApplicationReferenceGenerator
{
    public function next(string $type, CarbonInterface $submissionDate): string
    {
        $year = (int) $submissionDate->format('Y');

        return DB::transaction(function () use ($type, $year): string {
            DB::table('reference_sequences')->insertOrIgnore([
                'application_type_id' => $type, 'year' => $year, 'last_number' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $sequence = DB::table('reference_sequences')->where('application_type_id', $type)
                ->where('year', $year)->lockForUpdate()->firstOrFail();
            $next = $sequence->last_number + 1;
            DB::table('reference_sequences')->where('application_type_id', $type)->where('year', $year)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('%s/%d/%05d', $type, $year, $next);
        });
    }
}
