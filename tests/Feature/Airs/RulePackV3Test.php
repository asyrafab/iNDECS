<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Review\RuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rule pack v3: payment is handled by the iNDECS portal, so the payment-document rules are retired. */
final class RulePackV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_v3_retires_payment_document_rules_and_keeps_the_rest(): void
    {
        $v2 = app(RuleRepository::class)->syncPack('ljup', 'v2');
        $v3 = app(RuleRepository::class)->syncPack('ljup', 'v3');

        $this->assertNotContains('LJUP-SUB-008', $v3->pluck('rule_id')->all());
        $this->assertNotContains('LJUP-SUB-009', $v3->pluck('rule_id')->all());
        $this->assertCount($v2->count() - 2, $v3);
    }

    public function test_v3_is_the_configured_default(): void
    {
        $this->assertSame('v3', config('airs.rule_version'));
    }
}
