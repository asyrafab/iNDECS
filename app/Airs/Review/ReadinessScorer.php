<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\FindingStatus;

/**
 * Single source of truth for the "skor kesediaan" (readiness score) shown to officers.
 *
 * Thresholds (per management decision):
 *   Scores are advisory triage indexes only. All findings and officer actions remain available.
 *   Existing status points are retained for compatibility pending officer calibration.
 */
final class ReadinessScorer
{
    public const LOW_READINESS_BAND = 50;

    public const PASS_THRESHOLD = 90;

    public static function statusPoints(string $status): int
    {
        return match ($status) {
            FindingStatus::Complied->value => 100,
            FindingStatus::ManualReview->value => 65,
            FindingStatus::OfficerManualReview->value => 55,
            FindingStatus::NotDetected->value => 25,
            FindingStatus::NotComplied->value => 0,
            default => 0,
        };
    }

    public static function label(int $score): string
    {
        return match (true) {
            $score > self::PASS_THRESHOLD => 'Kesediaan dokumen tinggi — pengesahan pegawai diperlukan',
            $score >= self::LOW_READINESS_BAND => 'Perlu Semakan',
            default => 'Kesediaan dokumen rendah — perlu semakan pegawai',
        };
    }

    public static function suggestion(int $score): string
    {
        return match (true) {
            $score > self::PASS_THRESHOLD => 'Indeks triage tinggi. Semua dapatan kritikal, item manual dan bukti masih perlu disahkan oleh pegawai.',
            $score >= self::LOW_READINESS_BAND => 'Permohonan memerlukan semakan lanjut oleh pegawai bagi item yang belum selesai sebelum sebarang keputusan dibuat.',
            default => 'Semak kekurangan dan semua dapatan yang dipaparkan. Skor ini bukan keputusan penolakan.',
        };
    }

    /**
     * Shadow outcome only: records what the configured threshold would have decided.
     * It never rejects an application, changes its state or hides findings.
     */
    public static function shadowOutcome(int $score): string
    {
        return $score < (int) config('airs.score_threshold') ? 'BELOW_THRESHOLD' : 'MEETS_THRESHOLD';
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings  each item must contain a 'status' key
     * @return array{score:int, label:string, suggestion:string, basis:string}
     */
    public static function score(array $findings): array
    {
        $scored = collect($findings)->reject(fn (array $finding): bool => ($finding['status'] ?? null) === FindingStatus::NotApplicable->value);
        $total = $scored->count();

        if ($total === 0) {
            return [
                'score' => 0,
                'label' => 'Tiada item boleh dijaringkan',
                'suggestion' => 'Skor belum boleh diberikan sehingga item semakan berkaitan dijana.',
                'basis' => 'Item tidak berkaitan dikecualikan. Skor ini bukan keputusan kelulusan.',
            ];
        }

        $points = $scored->sum(fn (array $finding): int => self::statusPoints((string) $finding['status']));
        $score = (int) round($points / $total);

        return [
            'score' => $score,
            'label' => self::label($score),
            'suggestion' => self::suggestion($score),
            'basis' => 'Indeks triage legasi: purata mata status item berkaitan. Bukan kebarangkalian pematuhan atau keputusan rasmi; item manual dan kritikal mesti disemak.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     * @return array{score:?int, label:string, suggestion:string, counts:array<string,int>}
     */
    public static function sectionScore(array $findings): array
    {
        $findingsCollection = collect($findings);
        $scored = $findingsCollection->reject(fn (array $finding): bool => ($finding['status'] ?? null) === FindingStatus::NotApplicable->value);
        $score = $scored->isEmpty()
            ? null
            : (int) round($scored->sum(fn (array $finding): int => self::statusPoints((string) $finding['status'])) / $scored->count());
        $counts = $findingsCollection->countBy('status')->all();

        return [
            'score' => $score,
            'label' => $score === null ? 'Tidak berkaitan' : self::label($score),
            'suggestion' => $score === null ? 'Tiada item semakan berkaitan dalam seksyen ini.' : self::suggestion($score),
            'counts' => [
                FindingStatus::Complied->value => $counts[FindingStatus::Complied->value] ?? 0,
                FindingStatus::NotComplied->value => $counts[FindingStatus::NotComplied->value] ?? 0,
                FindingStatus::NotApplicable->value => $counts[FindingStatus::NotApplicable->value] ?? 0,
                FindingStatus::NotDetected->value => $counts[FindingStatus::NotDetected->value] ?? 0,
                FindingStatus::ManualReview->value => $counts[FindingStatus::ManualReview->value] ?? 0,
                FindingStatus::OfficerManualReview->value => $counts[FindingStatus::OfficerManualReview->value] ?? 0,
            ],
        ];
    }
}
