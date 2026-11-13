<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Models\ProfileFieldReview;

final class MatrixProfileProjection
{
    public const FIELDS = ['project_title', 'developer', 'consultant', 'lot_mukim', 'district', 'phase', 'our_reference', 'submission_date', 'drawing_revision'];

    public function apply(array $report): array
    {
        $versionId = $report['matrix_review_version_id'] ?? null;
        if (! $versionId) return $report;
        $reviews = ProfileFieldReview::query()->where('review_version_id', $versionId)->orderBy('revision')->get()->groupBy('field');
        $report['profile_field_reviews'] = [];
        $changed = false;
        foreach ($reviews as $field => $entries) {
            $latest = $entries->last();
            $report['profile_field_reviews'][$field] = ['disposition' => $latest->disposition, 'value' => $latest->value, 'revision' => $latest->revision, 'reason' => $latest->reason, 'actor_id' => $latest->actor_id];
            $original = $report['application_profile'][$field] ?? null;
            $value = $latest->disposition === 'REJECTED' ? null : $latest->value;
            $report['application_profile'][$field] = $value;
            if ($value !== $original) $changed = true;
        }
        $report['profile_dependencies_stale'] = $changed;
        if ($changed) {
            // Conservative invalidation: applicability can itself depend on corrected identity/context.
            foreach ($report['sections'] ?? [] as $sectionIndex => $section) foreach ($section['findings'] ?? [] as $findingIndex => $finding) {
                $report['sections'][$sectionIndex]['findings'][$findingIndex]['original_status'] = $finding['status'];
                $report['sections'][$sectionIndex]['findings'][$findingIndex]['status'] = 'PERLU_SEMAKAN_MANUAL';
                $report['sections'][$sectionIndex]['findings'][$findingIndex]['manual_review_reason'] = 'Maklumat profil dibetulkan; dapatan dan kebolehgunaan perlu disemak semula.';
            }
            $report['application_score'] = ['score' => null, 'label' => 'Skor ditangguhkan: profil dibetulkan', 'suggestion' => 'Semak semula dapatan berdasarkan profil yang dibetulkan.', 'basis' => 'Dapatan asal dan sejarah pembetulan dikekalkan.'];
            foreach ($report['section_checklist'] ?? [] as $index => $section) {
                $report['section_checklist'][$index]['score'] = null;
                $report['section_checklist'][$index]['label'] = 'Dapatan perlu disemak semula';
                $report['section_checklist'][$index]['suggestion'] = 'Profil telah dibetulkan oleh pegawai.';
            }
            foreach ($report['applicability_matrix'] ?? [] as $index => $item) {
                $report['applicability_matrix'][$index]['applicable'] = null;
                $report['applicability_matrix'][$index]['status'] = 'PERLU_SEMAKAN_MANUAL';
                $report['applicability_matrix'][$index]['reason'] = 'Kebolehgunaan perlu disahkan semula selepas pembetulan profil.';
            }
            foreach ($report['cross_document_comparisons'] ?? [] as $index => $item) {
                $report['cross_document_comparisons'][$index]['status'] = 'PERLU_SEMAKAN_MANUAL';
            }
            $report['summary']['overall_state'] = 'PROFILE_CORRECTION_REQUIRES_REVIEW';
            $report['summary']['overall_state_label'] = 'Profil dibetulkan — dapatan perlu disemak semula';
            $report['warnings'][] = 'PROFILE_DEPENDENCIES_STALE';
        }
        return $report;
    }
}
