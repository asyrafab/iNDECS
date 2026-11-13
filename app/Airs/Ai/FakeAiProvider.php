<?php

declare(strict_types=1);

namespace App\Airs\Ai;

use App\Airs\Contracts\AiProvider;

final class FakeAiProvider implements AiProvider
{
    public function classifyDocument(array $input): array
    {
        $name = mb_strtolower((string) ($input['filename'] ?? ''));
        $extension = mb_strtolower((string) ($input['extension'] ?? ''));
        $text = mb_strtolower(implode(' ', array_column($input['pages'] ?? [], 'text')));
        $search = $this->normaliseSearchText("{$name} {$text}");
        [$type, $confidence, $reason] = match (true) {
            str_contains($name, 'gambar tapak'), str_contains($name, 'site photo'), str_contains($name, 'site photograph') => ['SITE_PHOTOGRAPHS', 0.96, 'Site-photograph filename detected.'],
            preg_match('/ljup.{0,20}(khusus|special)|syarat.{0,10}khusus/', $search) === 1 => ['LJUP_SPECIAL_FORM', 0.90, 'Special LJUP review form terminology detected.'],
            preg_match('/ljup.{0,20}(umum|general)|keperluan.{0,10}umum/', $search) === 1 => ['LJUP_GENERAL_FORM', 0.90, 'General LJUP review form terminology detected.'],
            preg_match('/surat (permohonan|iringan)|cover letter|submission letter/', $search) === 1 => ['SUBMISSION_LETTER', 0.92, 'Submission-letter terminology or filename detected.'],
            preg_match('/borang pengemukaan pelan lampu jalan|sop\s*4\s*.?\s*ljup\/kjt\s+lamp\.?\s*1/', $search) === 1 => ['LJUP_SUBMISSION_CHECKLIST', 0.92, 'LJUP submission checklist terminology detected.'],
            preg_match('/photometric|fotometrik|dialux|lux report/', $search) === 1 => ['PHOTOMETRIC_REPORT', 0.95, 'Photometric terminology detected.'],
            preg_match('/bayaran|payment|resit|receipt|fi proses|rm\s*(250|500)/', $search) === 1 => ['PAYMENT_REGISTRATION_DEMAND', 0.90, 'Payment-related document; settlement is not independently established.'],
            preg_match('/kebenaran merancang|pelan km|planning permission|\bpkm\b/', $search) === 1 => ['PLANNING_PERMISSION_PLAN', 0.88, 'Planning-permission terminology detected.'],
            preg_match('/jalan (?:dan )?parit|jalan dan perparitan|road.{0,10}drainage|road & drainage/', $search) === 1 => ['ROAD_DRAINAGE_PLAN', 0.88, 'Road and drainage plan terminology detected.'],
            preg_match('/pelan bangunan|building plan/', $search) === 1 => ['BUILDING_PLAN', 0.86, 'Building-plan terminology detected.'],
            preg_match('/\btnb\b.{0,40}(sah|confirm|position|kedudukan)/', $search) === 1 => ['TNB_CONFIRMATION', 0.82, 'TNB confirmation terminology detected.'],
            preg_match('/pelan.{0,20}lampu|street.{0,10}light|lighting plan/', $search) === 1 => ['LJUP_PLAN', 0.86, 'Street-lighting plan terminology detected.'],
            preg_match('/\bljup\b/', $search) === 1 => ['LJUP_PLAN', 0.72, 'Generic LJUP filename detected; classified as a street-lighting plan for review, but officer confirmation is recommended.'],
            preg_match('/curriculum vitae|\bresume\b|bank statement|penyata bank|medical report|laporan perubatan|restaurant menu|menu restoran/', $search) === 1 => ['IRRELEVANT_TO_SELECTED_REVIEW', 0.95, 'The document is clearly unrelated to the selected plan review.'],
            default => ['UNKNOWN', 0.35, 'No reliable deterministic classification signal was detected.'],
        };

        return [
            'primary_type' => $type,
            'secondary_tags' => [],
            'confidence' => $confidence,
            'reason' => $reason,
            'evidence_page_numbers' => $input['page_count'] > 0 ? [1] : [],
            'warnings' => $type === 'UNKNOWN' ? ['LOW_CLASSIFICATION_CONFIDENCE'] : [],
            '_meta' => $this->meta(compact('type', 'confidence', 'reason')),
        ];
    }

    public function extractApplicationProfile(array $input): array
    {
        $schedules = [];
        $ljupPlansWithoutReadableSchedule = [];
        $declaredAttachments = [];
        $actualTypes = [];
        $payments = [];
        $visualProfileCandidates = [];

        foreach ($input['documents'] ?? [] as $document) {
            $text = implode("\n", array_map(
                fn (array $page): string => trim((string) ($page['text'] ?? '')."\n".(string) ($page['ocr_text'] ?? '')),
                $document['pages'] ?? [],
            ));
            $type = (string) ($document['document_type'] ?? 'UNKNOWN');
            $actualTypes[] = $type;
            if ($type === 'LJUP_GENERAL_FORM' && trim($text) === '') {
                $visualProfileCandidates[] = [
                    'file_id' => $document['file_id'] ?? null,
                    'filename' => $document['filename'] ?? null,
                    'reason' => 'Borang LJUP Umum berbentuk imej; profil kepala borang memerlukan OCR atau ekstraksi visual.',
                ];
            }
            if (preg_match('/gambar\s+tapak|site\s+photograph/i', $text) === 1) {
                $declaredAttachments[] = ['name' => 'SITE_PHOTOGRAPHS', 'file_id' => $document['file_id'], 'declared_only' => $type !== 'SITE_PHOTOGRAPHS'];
            }
            if (in_array($type, ['LJUP_PLAN', 'PHOTOMETRIC_REPORT'], true)) {
                $quantities = $this->extractLampQuantities($text);
                if ($quantities !== []) {
                    $schedules[] = [
                        'source_type' => $type,
                        'file_id' => $document['file_id'],
                        'page_number' => $this->schedulePage($document['pages'] ?? []),
                        'quantities' => $quantities,
                        'extraction_method' => 'embedded_text',
                        'requires_manual_verification' => false,
                    ];
                } elseif ($type === 'LJUP_PLAN') {
                    $ljupPlansWithoutReadableSchedule[] = [
                        'file_id' => $document['file_id'],
                        'page_number' => $document['pages'][0]['page_number'] ?? 1,
                    ];
                }
            }
            if ($type === 'PAYMENT_REGISTRATION_DEMAND') {
                preg_match('/RM\s*([0-9,.]+)/i', $text, $amount);
                $payments[] = [
                    'file_id' => $document['file_id'],
                    'document_kind' => 'PAYMENT_REGISTRATION_DEMAND',
                    'amount' => isset($amount[1]) ? (float) str_replace(',', '', $amount[1]) : null,
                    'paid_status' => 'UNVERIFIED',
                ];
            }
        }

        $hasPlanSchedule = false;
        $photometricSchedule = null;
        foreach ($schedules as $schedule) {
            if ($schedule['source_type'] === 'LJUP_PLAN') {
                $hasPlanSchedule = true;
            }
            if ($photometricSchedule === null && $schedule['source_type'] === 'PHOTOMETRIC_REPORT') {
                $photometricSchedule = $schedule;
            }
        }
        if (! $hasPlanSchedule && $photometricSchedule !== null && $ljupPlansWithoutReadableSchedule !== []) {
            $plan = $ljupPlansWithoutReadableSchedule[0];
            $schedules[] = [
                'source_type' => 'LJUP_PLAN',
                'file_id' => $plan['file_id'],
                'page_number' => $plan['page_number'],
                'quantities' => $photometricSchedule['quantities'],
                'extraction_method' => 'inferred_from_photometric_report',
                'source_note' => 'Pelan LJUP tiada teks terbaca; kuantiti dipadankan daripada laporan fotometrik yang boleh dibaca untuk semakan manual.',
                'requires_manual_verification' => true,
            ];
        }

        $profile = [
            'project_title' => null,
            'developer' => null,
            'lot_mukim' => null,
            'lighting_system' => in_array('LJUP_PLAN', $actualTypes, true) ? 'UNKNOWN' : null,
            'submission_round' => 'UNKNOWN',
            'profile_extraction_status' => $visualProfileCandidates === [] ? 'TEXT_EXTRACTED' : 'VISUAL_EXTRACTION_REQUIRED',
            'profile_extraction_notes' => $visualProfileCandidates,
            'lamp_schedules' => $schedules,
            'declared_attachments' => $declaredAttachments,
            'actual_document_types' => array_values(array_unique($actualTypes)),
            'payments' => $payments,
        ];

        return [
            'profile' => $profile,
            'confidence_summary' => [
                'application_header' => $visualProfileCandidates === [] ? 0.6 : 0.0,
                'lamp_schedules' => $schedules === [] ? 0.0 : 0.9,
            ],
            'conflicts' => [],
            '_meta' => $this->meta($profile),
        ];
    }

    public function evaluateRule(array $input): array
    {
        $evidence = $input['evidence'] ?? [];
        $result = [
            'status' => $evidence === [] ? 'TIDAK_DAPAT_DIKESAN' : 'PERLU_SEMAKAN_MANUAL',
            'confidence' => $evidence === [] ? 0.75 : 0.55,
            'finding' => $evidence === []
                ? 'No reliable evidence was detected in the uploaded digital package.'
                : 'Potentially relevant evidence was located and requires officer confirmation.',
            'extracted_values' => [],
            'manual_review_reason' => $evidence === [] ? null : 'The deterministic development provider does not make a semantic compliance claim.',
            'warnings' => ['FAKE_PROVIDER'],
            'evidence' => array_map(fn (array $item): array => [
                'file_id' => $item['file_id'],
                'page_number' => $item['page_number'] ?? null,
                'quote_or_value' => (string) ($item['quote_or_value'] ?? ''),
                'evidence_type' => (string) ($item['evidence_type'] ?? 'TEXT'),
                'confidence' => $item['confidence'] ?? 0.5,
            ], array_slice($evidence, 0, 3)),
        ];
        $result['_meta'] = $this->meta($result);

        return $result;
    }

    public function generateReport(array $input): array
    {
        return ['narrative' => 'Deterministic report', '_meta' => $this->meta(['narrative' => 'Deterministic report'])];
    }

    private function extractLampQuantities(string $text): array
    {
        $values = [];
        preg_match_all('/(150|180)\s*w(?:att)?\\b.{0,120}?quantity\s*:?\s*(\d{1,3})\s*(?:unit|units|nos?\.?|buah)?/is', $text, $labelled, PREG_SET_ORDER);
        foreach ($labelled as $match) {
            $values[$match[1]] = (int) $match[2];
        }
        preg_match_all('/quantity\s*:?\s*(\d{1,3})\s*(?:unit|units|nos?\.?|buah)?.{0,120}?(150|180)\s*w(?:att)?\\b/is', $text, $labelledReverse, PREG_SET_ORDER);
        foreach ($labelledReverse as $match) {
            $values[$match[2]] ??= (int) $match[1];
        }
        preg_match_all('/(\d{1,3})\s*(?:unit|units|nos?\.?|buah|x)\s*(?:of\s*)?(150|180)\s*w(?:att)?\\b/i', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $values[$match[2]] ??= (int) $match[1];
        }
        preg_match_all('/(150|180)\s*w(?:att)?\\b\s*(?:x|:|-)\s*(\d{1,3})\s*(?:unit|units|nos?\.?|buah)/i', $text, $reverse, PREG_SET_ORDER);
        foreach ($reverse as $match) {
            $values[$match[1]] ??= (int) $match[2];
        }
        ksort($values);

        return $values;
    }

    private function schedulePage(array $pages): ?int
    {
        foreach ($pages as $page) {
            if (preg_match('/(?:150|180)\s*w/i', (string) ($page['text'] ?? '')) === 1) {
                return (int) $page['page_number'];
            }
        }

        return $pages === [] ? null : (int) $pages[0]['page_number'];
    }

    private function normaliseSearchText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['_', '-', '.', '/', '\\'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function meta(array $raw): array
    {
        return [
            'raw_response' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'provider_request_id' => null,
            'latency_ms' => 0,
            'input_tokens' => null,
            'output_tokens' => null,
            'model' => 'fake-deterministic-v1',
        ];
    }
}
