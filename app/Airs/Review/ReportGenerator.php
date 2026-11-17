<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\FindingStatus;
use App\Airs\Models\GeneratedReport;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;

final class ReportGenerator
{
    public function __construct(private readonly FindingAggregator $aggregator) {}

    public function generate(ReviewCase $case, ReviewVersion $version): GeneratedReport
    {
        $case->load(['files.pages', 'files.classifications' => fn ($query) => $query->where('review_version_id', $version->id)]);
        $version->load(['profile', 'findings' => fn ($query) => $query
            ->where('is_superseded', false)
            ->with(['rule', 'evidence.file'])]);

        $summary = $this->aggregator->aggregate($version);
        $inventory = $case->files->where('submission_number', $version->check_number)->where('extension', '!=', 'zip')->map(function ($file) use ($version): array {
            $classification = $file->classifications->firstWhere('review_version_id', $version->id);

            return [
                'id' => $file->id,
                'name' => $file->original_name,
                'type' => $classification?->primary_type ?? 'UNKNOWN',
                'pages' => (int) $file->page_count,
                'readability' => $file->pages->pluck('content_type')->unique()->implode(', '),
                'confidence' => $classification ? (float) $classification->confidence : 0,
                'status' => $file->processing_status,
                'source_document_id' => $file->source_document_id,
            ];
        })->values()->all();

        $findingRows = $version->findings->map(fn ($finding): array => [
            'id' => $finding->id,
            'rule_id' => $finding->rule_id,
            'title' => $this->bmRuleTitle($finding->rule_id, $finding->rule->title),
            'section' => $this->displaySection($finding->rule_id, $finding->rule->section),
            'requirement' => $this->bmRequirement($finding->rule_id, $finding->rule->requirement),
            'source' => array_filter([
                'document' => $finding->rule->source_document,
                'page' => $finding->rule->source_page,
                'section' => $finding->rule->source_section,
            ]),
            'status' => $finding->status->value,
            'confidence' => (float) $finding->confidence,
            'finding' => $this->bmFinding($finding->rule_id, $finding->status->value, $finding->finding, $finding->warnings ?? []),
            'extracted_values' => $finding->extracted_values,
            'applicability_reason' => data_get($finding->extracted_values, 'applicability.reason'),
            'applicability_source' => data_get($finding->extracted_values, 'applicability.source'),
            'manual_review_reason' => $this->bmManualReason($finding->manual_review_reason),
            'warnings' => $finding->warnings,
            'calibrated_with_officer_reference' => in_array('OFFICER_REFERENCE_CALIBRATION_APPLIED', $finding->warnings ?? [], true),
            'standard_comment' => $this->bmStandardComment($finding->rule_id, $finding->rule->standard_comment),
            'evidence' => $finding->evidence->map(fn ($evidence): array => [
                'id' => $evidence->id,
                'document' => $evidence->file->original_name,
                'page' => $evidence->page_number,
                'quote_or_value' => $evidence->quote_or_value,
                'type' => $evidence->evidence_type,
                'confidence' => $evidence->confidence === null ? null : (float) $evidence->confidence,
                'file_id' => $evidence->review_file_id,
                'source_document_id' => $evidence->file->source_document_id,
            ])->all(),
        ])->all();

        $sections = collect($findingRows)->groupBy('section')->map(fn ($rows, $section): array => [
            'title' => $section,
            'findings' => $rows->values()->all(),
        ])->values()->all();
        $applicabilityMatrix = collect($findingRows)->map(fn (array $finding): array => [
            'rule_id' => $finding['rule_id'],
            'title' => $finding['title'],
            'section' => $finding['section'],
            'applicable' => $finding['status'] !== FindingStatus::NotApplicable->value,
            'status' => $finding['status'],
            'reason' => $finding['applicability_reason'],
            'source' => $finding['applicability_source'],
        ])->values()->all();
        $score = $this->applicationScore($findingRows);
        $sectionChecklist = $this->sectionChecklist($sections);
        $officerCalibrated = collect($findingRows)->where('calibrated_with_officer_reference', true);

        $manualStatuses = [FindingStatus::ManualReview->value, FindingStatus::OfficerManualReview->value];
        $summary['overall_state_label'] = $this->bmOverallState((string) ($summary['overall_state'] ?? ''));
        $report = [
            'case' => [
                'id' => $case->id,
                'client_reference' => $case->client_reference,
                'source_upload' => $case->source_upload_name,
                'selected_lighting_types' => $case->selected_lighting_types ?? [],
                'processed_at' => now()->toIso8601String(),
                'documents' => $case->file_count,
                'pages' => $case->page_count,
            ],
            'matrix_review_version_id' => $version->id,
            'matrix_usage' => app(MatrixUsageReport::class)->forVersion($version),
            'application_profile' => $version->profile?->profile_json ?? [],
            'profile_conflicts' => $version->profile?->conflicts_json ?? [],
            'document_inventory' => $inventory,
            'summary' => $summary,
            'application_score' => $score,
            'section_checklist' => $sectionChecklist,
            'applicability_matrix' => $applicabilityMatrix,
            'sections' => $sections,
            'manual_review_items' => collect($findingRows)->whereIn('status', $manualStatuses)->values()->all(),
            'cross_document_comparisons' => collect($findingRows)->filter(fn (array $finding): bool => str_starts_with((string) $finding['rule_id'], 'LJUP-XDOC-'))->values()->all(),
            'officer_reference_calibration' => [
                'applied' => $officerCalibrated->isNotEmpty(),
                'items' => $officerCalibrated->count(),
                'label' => 'Diselaraskan dengan semakan manual pegawai bagi pakej Billion Site yang sepadan tepat.',
            ],
            'warnings' => $case->warnings ?? [],
            'disclaimer' => 'Laporan ini ialah saringan awal berbantukan AI berdasarkan pakej digital yang dimuat naik. Laporan ini bukan kelulusan rasmi. Pegawai berkuasa masih bertanggungjawab terhadap pengesahan rasmi, bayaran, kesahihan dokumen, ukuran kejuruteraan dan keputusan muktamad.',
            'versions' => [
                'application' => $version->application_version ?? config('airs.application_version'),
                'rule_pack' => $version->rule_pack.'-'.$version->rule_version,
                'prompt' => $version->prompt_version,
                'report' => $version->report_version,
                'provider' => $version->ai_provider,
                'text_model' => $version->text_model,
                'vision_model' => $version->vision_model,
            ],
        ];
        $findingsHash = hash('sha256', json_encode($findingRows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        // Advisory score and shadow outcome only; never changes the iNDECS application.
        $version->forceFill(['score' => (int) $score['score'], 'shadow_outcome' => ReadinessScorer::shadowOutcome((int) $score['score'])])->save();

        return GeneratedReport::updateOrCreate(
            ['review_version_id' => $version->id, 'findings_hash' => $findingsHash],
            [
                'report_version' => $version->report_version,
                'language' => 'ms',
                'report_json' => $report,
            ],
        );
    }

    /**
     * This is a readiness score for officer triage. It is not an approval score.
     *
     * @param  list<array<string, mixed>>  $findings
     */
    private function applicationScore(array $findings): array
    {
        return ReadinessScorer::score($findings);
    }

    /**
     * @param  list<array{title: string, findings: list<array<string, mixed>>}>  $sections
     * @return list<array<string, mixed>>
     */
    private function sectionChecklist(array $sections): array
    {
        return collect($sections)->map(fn (array $section): array => [
            'title' => $section['title'],
            'total_items' => count($section['findings']),
            ...ReadinessScorer::sectionScore($section['findings']),
        ])->values()->all();
    }

    private function displaySection(string $ruleId, string $section): string
    {
        if (str_starts_with($ruleId, 'LJUP-SPC-') || in_array($ruleId, ['LJUP-XDOC-001', 'LJUP-XDOC-008'], true)) {
            return 'LJUP Khusus';
        }

        return 'LJUP Umum';
    }

    private function bmOverallState(string $state): string
    {
        return match ($state) {
            'APPLICATION_INCOMPLETE' => 'Permohonan belum lengkap',
            'REVIEW_COMPLETED_PENDING_MANUAL_VERIFICATION' => 'Saringan selesai, menunggu semakan manual',
            'REVIEW_COMPLETED_WITH_COMMENTS' => 'Saringan selesai dengan ulasan',
            'NO_MATERIAL_ISSUES_DETECTED_BY_AI' => 'Tiada isu utama dikesan oleh AI',
            default => $state,
        };
    }

    private function bmRuleTitle(string $ruleId, string $fallback): string
    {
        return [
            'LJUP-SUB-001' => 'Set pelan fizikal dan lipatan A4',
            'LJUP-SUB-002' => 'Pelan Kebenaran Merancang yang diluluskan',
            'LJUP-SUB-003' => 'Pelan bangunan sebagai dokumen sokongan',
            'LJUP-SUB-004' => 'Pelan Jalan dan Parit',
            'LJUP-SUB-005' => 'Pengesahan kedudukan tiang oleh TNB',
            'LJUP-SUB-006' => 'Borang semakan LJUP',
            'LJUP-SUB-007' => 'Empat gambar tapak cadangan',
            'LJUP-SUB-008' => 'Dokumen bayaran pengemukaan pertama',
            'LJUP-SUB-009' => 'Dokumen bayaran pengemukaan kali kedua dan seterusnya',
            'LJUP-SUB-010' => 'Maklumat, tandatangan dan cop Orang Yang Mengemukakan',
            'LJUP-GEN-A-005' => 'Tajuk cadangan lengkap',
            'LJUP-GEN-A-007' => 'Simbol dan warna yang ditetapkan',
            'LJUP-GEN-A-010' => 'Arah lampu jalan',
            'LJUP-GEN-A-011' => 'Pelan lokasi dan tapak cadangan',
            'LJUP-GEN-A-012' => 'Jadual tiang dan lampu',
            'LJUP-GEN-GAL-OTH-004' => 'Cadangan fotometrik lengkap',
            'LJUP-SPC-C-001' => 'Lampu jalan sedia ada di jalan utama',
            'LJUP-SPC-C-002' => 'Lampu sedia ada di kawasan bersebelahan',
            'LJUP-SPC-C-003' => 'Jalan masuk utama dan lampu sedia ada',
            'LJUP-SPC-C-004' => 'Reka bentuk pemasangan yang diterima',
            'LJUP-SPC-C-005' => 'Had kerja yang diluluskan',
            'LJUP-SPC-C-006' => 'Pindaan dalam pengemukaan semula',
            'LJUP-SPC-C-007' => 'Pencahayaan di lokasi bahaya dan tumpuan',
            'LJUP-SPC-C-008' => 'Jarak dari bahu jalan dan laluan Universal Design',
            'LJUP-SPC-C-009' => 'Ringkasan dan perincian fotometrik',
            'LJUP-SPC-D-TNB-001' => 'Larangan pemasangan lampu pada dinding',
            'LJUP-SPC-D-TNB-002' => 'Kedudukan tiang baharu dan sedia ada',
            'LJUP-SPC-D-TNB-003' => 'Jarak dan susunan tiang TNB',
            'LJUP-SPC-D-GAL-001' => 'Perincian sistem tiang galvani atau solar',
            'LJUP-XDOC-001' => 'Padanan kuantiti dan watt lampu antara dokumen',
            'LJUP-XDOC-002' => 'Padanan identiti projek antara dokumen',
            'LJUP-XDOC-006' => 'Lampiran yang diisytihar benar-benar dikemukakan',
            'LJUP-XDOC-007' => 'Amaun bayaran dan pusingan pengemukaan',
            'LJUP-XDOC-008' => 'Padanan kebolehgunaan borang dengan sistem lampu',
        ][$ruleId] ?? $fallback;
    }

    private function bmRequirement(string $ruleId, string $fallback): string
    {
        return [
            'LJUP-SUB-001' => 'Empat set pelan dan lipatan mengikut ukuran A4 hendaklah disahkan secara fizikal.',
            'LJUP-SUB-002' => 'Satu salinan Pelan Kebenaran Merancang yang diluluskan hendaklah dikemukakan.',
            'LJUP-SUB-003' => 'Pelan Bangunan yang diluluskan, pelan tapak sahaja, diperlukan jika tiada PKM berlulus.',
            'LJUP-SUB-004' => 'Pelan Jalan dan Parit yang diluluskan, atau perakuan Jurutera Kompeten C&S berserta surat pengesahan, hendaklah dikemukakan.',
            'LJUP-SUB-005' => 'Cop atau pengesahan TNB bagi kedudukan tiang hendaklah dikenal pasti dan disahkan oleh pegawai.',
            'LJUP-SUB-006' => 'Borang LJUP Umum dan LJUP Khusus yang berkaitan hendaklah dikemukakan.',
            'LJUP-SUB-007' => 'Empat gambar tapak sebenar dari sudut berbeza hendaklah dikemukakan; pernyataan sahaja tidak mencukupi.',
            'LJUP-SUB-008' => 'Dokumen bayaran RM250 bagi pengemukaan pertama diperlukan jika berkaitan; daftar/tuntutan bayaran bukan bukti bayaran rasmi.',
            'LJUP-SUB-009' => 'Dokumen bayaran RM500 diperlukan bagi pengemukaan kali kedua dan seterusnya jika berkaitan.',
            'LJUP-SUB-010' => 'Nama, alamat, tandatangan dan cop Orang Yang Mengemukakan hendaklah kelihatan; kesahihan perlu disahkan secara manual.',
            'LJUP-GEN-A-005' => 'Tajuk cadangan hendaklah menyatakan nama skim, nombor lot, daerah dan mukim secara konsisten.',
            'LJUP-GEN-A-007' => 'Simbol dan warna yang ditetapkan hendaklah digunakan bagi lampu/tiang sedia ada dan cadangan.',
            'LJUP-GEN-A-010' => 'Arah setiap lampu cadangan hendaklah ditunjukkan dengan jelas di atas pelan.',
            'LJUP-GEN-A-011' => 'Pelan lokasi hendaklah disediakan dan tapak cadangan hendaklah ditanda dengan jelas.',
            'LJUP-GEN-A-012' => 'Jumlah tiang, jenis tiang, jenis lampu dan watt lampu hendaklah dinyatakan.',
            'LJUP-GEN-GAL-OTH-004' => 'Cadangan fotometrik lengkap hendaklah mengandungi tinggi tiang, saiz arm, jarak antara tiang dan watt lampu.',
            'LJUP-SPC-C-001' => 'Semua kedudukan tiang lampu sedia ada di jalan utama sepanjang hadapan lot pembangunan hendaklah ditunjukkan.',
            'LJUP-SPC-C-002' => 'Kedudukan lampu sedia ada di kawasan bersebelahan hendaklah ditunjukkan bersama tinggi, panjang arm dan watt lampu.',
            'LJUP-SPC-C-003' => 'Jalan masuk dari jalan utama ke tapak pembangunan serta lampu jalan sedia ada hendaklah ditunjukkan.',
            'LJUP-SPC-C-004' => 'Cadangan pemasangan dan reka bentuk tiang hendaklah mengikut garis panduan yang diterima.',
            'LJUP-SPC-C-005' => 'Cadangan lampu hendaklah berada dalam had kerja yang diluluskan dan isu bekalan perlu diambil kira.',
            'LJUP-SPC-C-006' => 'Pelan pengemukaan semula hendaklah menunjukkan pindaan kedudukan tiang dengan jelas.',
            'LJUP-SPC-C-007' => 'Lampu jalan perlu disediakan di lokasi berbahaya atau lokasi tumpuan seperti persimpangan, selekoh, surau, pondok bas dan kawasan permainan.',
            'LJUP-SPC-C-008' => 'Feeder pillar dan tiang lampu hendaklah mematuhi jarak dari bahu jalan dan tidak menghalang laluan Universal Design.',
            'LJUP-SPC-C-009' => 'Laporan fotometrik berbentuk ringkasan dan perincian hendaklah dikemukakan, termasuk tinggi tiang, saiz arm, jarak dan watt lampu.',
            'LJUP-SPC-D-TNB-001' => 'Lampu tidak dibenarkan dipasang pada dinding bagi cadangan tiang konkrit TNB.',
            'LJUP-SPC-D-TNB-002' => 'Kedudukan tiang lampu baharu, tiang bekalan elektrik dan tiang lampu sedia ada hendaklah ditunjukkan dengan jelas.',
            'LJUP-SPC-D-TNB-003' => 'Jarak lampu pada tiang TNB hendaklah 30 hingga 35 meter dan susunan tiang hendaklah sesuai dengan bilangan lorong.',
            'LJUP-SPC-D-GAL-001' => 'Perincian tiang galvani atau solar, feeder pillar, kabel dan lantern hendaklah disemak apabila sistem tersebut dicadangkan.',
            'LJUP-XDOC-001' => 'Kuantiti dan watt lampu hendaklah sepadan antara pelan lampu jalan dan laporan fotometrik.',
            'LJUP-XDOC-002' => 'Tajuk projek, fasa, lot, mukim dan pemaju hendaklah konsisten antara dokumen.',
            'LJUP-XDOC-006' => 'Lampiran yang dinyatakan dalam surat atau senarai semak hendaklah benar-benar wujud dalam pakej dokumen.',
            'LJUP-XDOC-007' => 'Amaun dokumen bayaran hendaklah sepadan dengan pusingan pengemukaan; status bayaran rasmi masih perlu disahkan.',
            'LJUP-XDOC-008' => 'Pilihan kebolehgunaan dalam borang perunding hendaklah sepadan dengan sistem lampu yang dicadangkan.',
        ][$ruleId] ?? $fallback;
    }

    private function bmStandardComment(string $ruleId, ?string $fallback): ?string
    {
        return [
            'LJUP-SUB-001' => 'Sahkan bilangan set fizikal dan lipatan A4.',
            'LJUP-SUB-002' => 'Kemukakan Pelan Kebenaran Merancang yang diluluskan.',
            'LJUP-SUB-003' => 'Sahkan dokumen pelan tapak yang berkaitan.',
            'LJUP-SUB-004' => 'Kemukakan bukti Pelan Jalan dan Parit yang berkaitan.',
            'LJUP-SUB-005' => 'Pengesahan pegawai terhadap cop/pengesahan TNB diperlukan.',
            'LJUP-SUB-006' => 'Kemukakan borang semakan LJUP yang berkaitan.',
            'LJUP-SUB-007' => 'Muat naik empat gambar tapak sebenar dari sudut berbeza.',
            'LJUP-SUB-008' => 'Sahkan yuran dan status bayaran melalui sumber berautoriti.',
            'LJUP-SUB-009' => 'Sahkan pusingan pengemukaan dan yuran berkaitan.',
            'LJUP-SUB-010' => 'Sahkan butiran dan kesahihan Orang Yang Mengemukakan.',
            'LJUP-GEN-A-005' => 'Lengkapkan dan selaraskan tajuk cadangan merentas dokumen.',
            'LJUP-GEN-A-007' => 'Tunjukkan petunjuk, simbol dan warna yang ditetapkan dengan jelas.',
            'LJUP-GEN-A-010' => 'Tunjukkan arah lampu dengan jelas.',
            'LJUP-GEN-A-011' => 'Sediakan pelan lokasi dan tanda tapak cadangan.',
            'LJUP-GEN-A-012' => 'Sediakan jadual tiang dan lampu yang lengkap.',
            'LJUP-GEN-GAL-OTH-004' => 'Kemukakan cadangan fotometrik yang lengkap dan selaras.',
            'LJUP-SPC-C-001' => 'Tunjukkan tiang lampu sedia ada di jalan utama.',
            'LJUP-SPC-C-002' => 'Labelkan butiran lampu sedia ada di kawasan bersebelahan.',
            'LJUP-SPC-C-003' => 'Tunjukkan jalan masuk utama dan lampu sedia ada dengan jelas.',
            'LJUP-SPC-C-004' => 'Pengesahan pegawai diperlukan bagi penerimaan reka bentuk teknikal.',
            'LJUP-SPC-C-005' => 'Sahkan had kerja dan susunan bekalan.',
            'LJUP-SPC-C-006' => 'Tunjukkan pindaan pengemukaan semula dengan jelas.',
            'LJUP-SPC-C-007' => 'Pertimbangan kejuruteraan pegawai diperlukan untuk kecukupan pencahayaan.',
            'LJUP-SPC-C-008' => 'Sahkan jarak sebenar dan kelegaan laluan Universal Design.',
            'LJUP-SPC-C-009' => 'Kemukakan butiran fotometrik yang lengkap dan konsisten.',
            'LJUP-SPC-D-TNB-001' => 'Pastikan cadangan tidak menggunakan pemasangan lampu pada dinding.',
            'LJUP-SPC-D-TNB-002' => 'Bezakan tiang lampu baharu, tiang bekalan dan tiang lampu sedia ada.',
            'LJUP-SPC-D-TNB-003' => 'Semak jarak 30–35 meter dan susunan tiang mengikut bilangan lorong.',
            'LJUP-SPC-D-GAL-001' => 'Gunakan seksyen ini hanya untuk sistem tiang galvani, solar atau feeder pillar.',
            'LJUP-XDOC-001' => 'Selaraskan watt dan kuantiti lampu antara pelan dan laporan fotometrik.',
            'LJUP-XDOC-002' => 'Selesaikan percanggahan identiti projek antara dokumen.',
            'LJUP-XDOC-006' => 'Muat naik semua lampiran yang diisytiharkan; pernyataan sahaja tidak mencukupi.',
            'LJUP-XDOC-007' => 'Sahkan pusingan pengemukaan, amaun dan status bayaran secara berasingan.',
            'LJUP-XDOC-008' => 'Selaraskan pilihan kebolehgunaan borang dengan reka bentuk yang dicadangkan.',
        ][$ruleId] ?? $fallback;
    }

    private function bmFinding(string $ruleId, string $status, string $fallback, array $warnings = []): string
    {
        if (in_array('OFFICER_REFERENCE_CALIBRATION_APPLIED', $warnings, true)) {
            return $fallback;
        }

        return match ($status) {
            FindingStatus::Complied->value => 'Dokumen yang berkaitan telah ditemui dalam pakej digital. Perkara ini masih tertakluk kepada pengesahan pegawai jika melibatkan kesahihan, ukuran atau kelulusan rasmi.',
            FindingStatus::NotComplied->value => 'Keperluan ini belum ditunaikan berdasarkan semakan digital yang dijalankan.',
            FindingStatus::NotDetected->value => 'Maklumat langsung bagi keperluan ini tidak dapat dikesan dalam pakej digital yang dimuat naik.',
            FindingStatus::NotApplicable->value => 'Keperluan ini dinilai sebagai tidak berkaitan berdasarkan maklumat permohonan yang dikesan.',
            FindingStatus::OfficerManualReview->value => 'Perlu pengesahan pegawai bagi perkara rasmi seperti kesahihan, kelulusan, bayaran, cop atau tandatangan.',
            FindingStatus::ManualReview->value => 'Perlu semakan manual kerana maklumat digital tidak mencukupi untuk membuat kesimpulan yang selamat.',
            default => $fallback,
        };
    }

    private function bmManualReason(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return $reason;
        }
        $lowerReason = strtolower($reason);

        return match (true) {
            str_contains($lowerReason, 'payment') || str_contains($lowerReason, 'paid') => 'Status bayaran rasmi perlu disemak melalui rekod berautoriti.',
            str_contains($lowerReason, 'authenticity') || str_contains($lowerReason, 'official') => 'Kesahihan dokumen, cop, tandatangan atau kelulusan perlu disahkan oleh pegawai.',
            str_contains($lowerReason, 'inferred') => 'Sebahagian nilai diperoleh melalui padanan antara dokumen dan perlu disahkan secara manual.',
            str_contains($lowerReason, 'schema') => 'Respons AI tidak memenuhi skema berstruktur dan tidak digunakan sebagai kesimpulan.',
            default => 'Perlu semakan manual.',
        };
    }
}
