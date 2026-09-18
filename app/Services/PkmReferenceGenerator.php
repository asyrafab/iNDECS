<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PkmReferenceGenerator
{
    public function next(CarbonInterface $submissionDate): string
    {
        $year = (int) $submissionDate->format('Y');

        $number = DB::transaction(function () use ($year): int {
            DB::table('reference_sequences')->insertOrIgnore([
                'application_type_id' => 'PKM',
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('reference_sequences')
                ->where('application_type_id', 'PKM')
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $next = $sequence->last_number + 1;

            DB::table('reference_sequences')
                ->where('application_type_id', 'PKM')
                ->where('year', $year)
                ->update([
                    'last_number' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });

        return sprintf('PKM/%d/%05d', $year, $number);
    }
}
