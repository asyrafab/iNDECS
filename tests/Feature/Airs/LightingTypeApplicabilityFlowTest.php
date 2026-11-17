<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\CheckClearance;
use App\Airs\CheckService;
use App\Airs\Contracts\AiProvider;
use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Enums\FindingStatus;
use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ReviewFinding;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class LightingTypeApplicabilityFlowTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_all_lighting_type_combinations_route_only_relevant_rules_to_ai(): void
    {
        $this->bindDocumentProcessor();
        $provider = $this->bindSpyProvider();

        $cases = [
            'besi' => [['BESI'], true, false],
            'konkrit' => [['KONKRIT'], false, true],
            'solar' => [['SOLAR'], true, false],
            'besi_konkrit' => [['BESI', 'KONKRIT'], true, true],
            'besi_solar' => [['BESI', 'SOLAR'], true, false],
            'konkrit_solar' => [['KONKRIT', 'SOLAR'], true, true],
            'besi_konkrit_solar' => [['BESI', 'KONKRIT', 'SOLAR'], true, true],
        ];

        foreach ($cases as $label => [$types, $expectsGalvanised, $expectsTnb]) {
            $provider->evaluatedRuleIds = [];
            Storage::fake('local');
            Queue::fake();

            $application = $this->ljupApplication();
            $this->ljupDocument($application, "%PDF-1.4\n% {$label}\n%%EOF", "pelan-{$label}.pdf");
            $version = app(CheckService::class)->start($application, new CheckClearance('paid', 'TEST-'.$label, CarbonImmutable::now()->subMinute()),
                $types, $application->currentDocuments()->get());

            app()->call([new RunAirsCheck($version->id), 'handle']);
            $version->refresh();
            $evaluated = $provider->evaluatedRuleIds;

            $this->assertRuleRoute($expectsGalvanised, $evaluated, 'LJUP-SPC-D-GAL-001', $label);
            $this->assertRuleRoute($expectsTnb, $evaluated, 'LJUP-SPC-D-TNB-002', $label);

            self::assertSame(
                $expectsGalvanised ? FindingStatus::ManualReview->value : FindingStatus::NotApplicable->value,
                ReviewFinding::query()->where('review_version_id', $version->id)->where('rule_id', 'LJUP-SPC-D-GAL-001')->firstOrFail()->status->value,
                "{$label}: galvanised/solar finding status",
            );
            self::assertSame(
                $expectsTnb ? FindingStatus::ManualReview->value : FindingStatus::NotApplicable->value,
                ReviewFinding::query()->where('review_version_id', $version->id)->where('rule_id', 'LJUP-SPC-D-TNB-002')->firstOrFail()->status->value,
                "{$label}: TNB/concrete finding status",
            );

            $matrix = $version->reports()->firstOrFail()->report_json['applicability_matrix'];
            self::assertNotEmpty(collect($matrix)->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001')['reason'] ?? null);
            self::assertNotEmpty(collect($matrix)->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002')['reason'] ?? null);
        }
    }

    /**
     * @param  list<string>  $evaluated
     */
    private function assertRuleRoute(bool $expected, array $evaluated, string $ruleId, string $label): void
    {
        if ($expected) {
            self::assertContains($ruleId, $evaluated, "{$label}: {$ruleId} should be evaluated by AI");

            return;
        }

        self::assertNotContains($ruleId, $evaluated, "{$label}: {$ruleId} should not be sent to AI");
    }

    private function bindDocumentProcessor(): void
    {
        $this->app->instance(DocumentProcessor::class, new class implements DocumentProcessor
        {
            public function process(string $filePath, string $outputDirectory): array
            {
                if (! is_dir($outputDirectory)) {
                    mkdir($outputDirectory, 0777, true);
                }
                $text = 'Pelan lampu jalan. Jadual: 23 unit 150 W dan 7 unit 180 W.';
                $textPath = $outputDirectory.DIRECTORY_SEPARATOR.'page-1.txt';
                $preview = $outputDirectory.DIRECTORY_SEPARATOR.'page-1-preview.jpg';
                $thumbnail = $outputDirectory.DIRECTORY_SEPARATOR.'page-1-thumb.jpg';
                file_put_contents($textPath, $text);
                file_put_contents($preview, 'preview');
                file_put_contents($thumbnail, 'thumbnail');

                return [
                    'page_count' => 1,
                    'is_encrypted' => false,
                    'warnings' => [],
                    'pages' => [[
                        'page' => 1,
                        'width' => 842,
                        'height' => 595,
                        'rotation' => 0,
                        'content_type' => 'TEXT_RICH',
                        'embedded_text_path' => $textPath,
                        'thumbnail_path' => $thumbnail,
                        'preview_path' => $preview,
                        'high_res_path' => null,
                        'page_hash' => hash('sha256', $text),
                        'regions' => [],
                    ]],
                ];
            }
        });
    }

    private function bindSpyProvider(): AiProvider
    {
        $provider = new class implements AiProvider
        {
            /** @var list<string> */
            public array $evaluatedRuleIds = [];

            public function classifyDocument(array $input): array
            {
                return [
                    'primary_type' => 'LJUP_PLAN',
                    'secondary_tags' => [],
                    'confidence' => 0.95,
                    'reason' => 'Test lighting plan.',
                    'evidence_page_numbers' => [1],
                    'warnings' => [],
                    '_meta' => $this->meta(['task' => 'classify']),
                ];
            }

            public function extractApplicationProfile(array $input): array
            {
                return [
                    'profile' => [
                        'lighting_system' => 'UNKNOWN',
                        'submission_round' => 'UNKNOWN',
                        'lamp_schedules' => [],
                        'declared_attachments' => [],
                        'actual_document_types' => ['LJUP_PLAN'],
                        'payments' => [],
                    ],
                    'confidence_summary' => [],
                    'conflicts' => [],
                    '_meta' => $this->meta(['task' => 'profile']),
                ];
            }

            public function evaluateRule(array $input): array
            {
                $ruleId = (string) data_get($input, 'rule.rule_id');
                $this->evaluatedRuleIds[] = $ruleId;

                return [
                    'status' => 'PERLU_SEMAKAN_MANUAL',
                    'confidence' => 0.55,
                    'finding' => 'Spy provider recorded an applicable AI rule.',
                    'extracted_values' => [],
                    'manual_review_reason' => 'Test spy provider does not make semantic claims.',
                    'warnings' => ['SPY_PROVIDER'],
                    'evidence' => [],
                    '_meta' => $this->meta(['task' => 'evaluate', 'rule_id' => $ruleId]),
                ];
            }

            public function generateReport(array $input): array
            {
                return ['narrative' => 'Spy report', '_meta' => $this->meta(['task' => 'report'])];
            }

            private function meta(array $raw): array
            {
                return [
                    'raw_response' => json_encode($raw, JSON_THROW_ON_ERROR),
                    'provider_request_id' => null,
                    'latency_ms' => 0,
                    'input_tokens' => null,
                    'output_tokens' => null,
                    'model' => 'spy-provider',
                ];
            }
        };

        $this->app->instance(AiProvider::class, $provider);

        return $provider;
    }
}
