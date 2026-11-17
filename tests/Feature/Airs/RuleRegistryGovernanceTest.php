<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Models\ReviewRule;
use App\Airs\Review\RuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class RuleRegistryGovernanceTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_configured_rule_pack_syncs_governance_metadata(): void
    {
        config(['airs.rule_version' => 'v2']); // governance of the published v2 pack
        $rules = app(RuleRepository::class)->syncConfiguredPack();

        $galvanised = $rules->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001');
        $tnb = $rules->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002');
        $photos = $rules->firstWhere('rule_id', 'LJUP-SUB-007');

        self::assertNotNull($galvanised);
        self::assertNotNull($tnb);
        self::assertNotNull($photos);
        self::assertSame('DITERBITKAN', $galvanised->lifecycle_status);
        self::assertContains('BESI', $galvanised->applicability_tags);
        self::assertContains('SOLAR', $galvanised->applicability_tags);
        self::assertNotContains('KONKRIT', $galvanised->applicability_tags);
        self::assertContains('KONKRIT', $tnb->applicability_tags);
        self::assertTrue($photos->evidence_required);
        self::assertSame('UMUM', $photos->category);
    }

    public function test_published_rule_version_cannot_be_changed_in_place(): void
    {
        config(['airs.rule_version' => 'v2']);
        $repository = app(RuleRepository::class);
        $repository->syncConfiguredPack();

        ReviewRule::query()
            ->where('rule_pack', 'ljup')
            ->where('rule_version', 'v2')
            ->where('rule_id', 'LJUP-SPC-D-TNB-002')
            ->firstOrFail()
            ->forceFill(['rule_hash' => str_repeat('0', 64)])
            ->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Published knowledge rule cannot be changed in-place');

        $repository->syncConfiguredPack();
    }
}
