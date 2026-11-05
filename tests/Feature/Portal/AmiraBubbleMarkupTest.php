<?php

namespace Tests\Feature\Portal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmiraBubbleMarkupTest extends TestCase
{
    use BuildsApplicants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortalTypes();
    }

    public function test_bubble_has_static_fallback_and_script(): void
    {
        $html = $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()->getContent();

        $this->assertStringContainsString('role="img" aria-label="AMIRA"', $html);
        $this->assertStringContainsString(asset('js/amira-bubble.js'), $html);
        $this->assertMatchesRegularExpression('/data-mood="(calm|happy|think|concern|alert)"/', $html);
        $this->assertFileExists(public_path('js/amira-bubble.js'));
    }

    public function test_bubble_script_reads_colours_from_tokens_only(): void
    {
        $script = file_get_contents(public_path('js/amira-bubble.js'));

        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $script, 'Colours must come from tokens.css');
        foreach (['--color-accent-400', '--status-ai', '--status-attention', '--color-neutral-500', '--color-amira-highlight'] as $token) {
            $this->assertStringContainsString($token, $script);
        }
        $this->assertStringContainsString('prefers-reduced-motion', $script);
    }
}
