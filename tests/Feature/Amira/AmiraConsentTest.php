<?php

namespace Tests\Feature\Amira;

use App\Models\AuditLog;
use App\Services\Amira\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

/** One-time agreement before AMIRA uses the PSP's information (owner, 2026-11-06). */
class AmiraConsentTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['amira.enabled' => true]);
        Queue::fake();
    }

    public function test_first_visit_shows_the_agreement_with_its_boundaries_and_locks_the_chat(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('data-amira-consent', false)
            ->assertSee('Persetujuan penggunaan maklumat oleh AMIRA')
            ->assertSee('kata laluan')
            ->assertSee('nombor kad pengenalan')
            ->assertSee('12 bulan')
            ->assertSee('Saya setuju')
            ->assertSee('Baca persetujuan')
            ->assertDontSee('data-ask-url', false);
    }

    public function test_agreeing_is_recorded_once_and_unlocks_the_chat(): void
    {
        $user = $this->applicant();
        $this->actingAs($user)->from(route('portal.home'))->post(route('portal.amira.consent'))->assertRedirect(route('portal.home'));
        $first = $user->fresh()->amira_consented_at;
        $this->assertNotNull($first);
        $this->assertTrue(AuditLog::query()->where('auditable_id', (string) $user->id)->where('event', 'amira_consent_given')->exists());
        $this->travel(1)->day();
        $this->post(route('portal.amira.consent'));
        $this->assertTrue($first->equalTo($user->fresh()->amira_consented_at));
        $this->get(route('portal.home'))->assertSee('data-ask-url', false)->assertDontSee('data-amira-consent', false);
    }

    public function test_chat_without_agreement_is_refused_with_the_reason(): void
    {
        $this->actingAs($this->applicant());
        $this->postJson(route('portal.amira.ask'), ['question' => 'Hai'])
            ->assertForbidden()->assertJson(['message' => 'Sila baca dan setuju dengan persetujuan AMIRA sebelum berbual.']);
    }

    public function test_service_refuses_without_agreement(): void
    {
        $this->expectException(HttpException::class);
        app(ConversationService::class)->ask($this->applicant(), null, 'Hai');
    }

    public function test_panel_no_longer_shows_the_retention_note_or_footer(): void
    {
        $user = $this->applicant();
        $user->forceFill(['amira_consented_at' => now()])->save();
        $this->actingAs($user)->get(route('portal.home'))->assertOk()
            ->assertDontSee('Jangan kongsi maklumat peribadi sensitif')
            ->assertDontSee('amira-counter-footer', false);
    }
}
