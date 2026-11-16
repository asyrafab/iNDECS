<?php

namespace Tests\Feature\Portal;

use App\Models\ApplicantDraft;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DraftRemovalTest extends TestCase
{
    use BuildsApplicants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortalTypes();
    }

    private function draft(): ApplicantDraft
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.store'), $this->data())->assertRedirect();

        return ApplicantDraft::query()->sole();
    }

    public function test_owner_can_remove_draft_from_all_portal_surfaces_while_preserving_history(): void
    {
        $draft = $this->draft();
        foreach (['portal.home', 'portal.drafts.index', 'portal.drafts.edit'] as $route) {
            $this->get(route($route, $route === 'portal.drafts.edit' ? $draft : []))->assertOk()
                ->assertSee(route('portal.drafts.destroy', $draft), false)->assertSee('Buang draf');
        }
        $this->delete(route('portal.drafts.destroy', $draft), ['version' => 1])->assertRedirect(route('portal.home'));
        $this->assertSoftDeleted($draft);
        $this->assertDatabaseCount('core.applicant_draft_revisions', 1);
        $audit = AuditLog::query()->where('event', 'applicant_draft_removed')->sole();
        $this->assertSame($draft->user_id, $audit->actor_id);
        $this->assertSame($draft->id, $audit->auditable_id);
        $this->get(route('portal.home'))->assertOk()->assertSee('Belum ada fail')->assertDontSee($draft->title);
        $this->get(route('portal.drafts.index'))->assertOk()->assertDontSee($draft->title);
        $this->get(route('portal.drafts.edit', $draft))->assertNotFound();
        $this->put(route('portal.drafts.update', $draft), [])->assertNotFound();
        $this->get(route('portal.drafts.checklist', $draft))->assertNotFound();
        $this->delete(route('portal.drafts.destroy', $draft), ['version' => 1])->assertNotFound();
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_foreign_guest_and_staff_cannot_remove_draft(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->applicant('other@example.test'))->delete(route('portal.drafts.destroy', $draft), [])->assertNotFound();
        $staff = User::factory()->create(['account_type' => 'staff']);
        $this->actingAs($staff)->delete(route('portal.drafts.destroy', $draft), ['version' => 1])->assertForbidden();
        auth()->logout();
        $this->delete(route('portal.drafts.destroy', $draft), ['version' => 1])->assertRedirect(route('portal.login'));
        $this->assertFalse($draft->fresh()->trashed());
    }

    public function test_missing_or_stale_version_cannot_remove_draft(): void
    {
        $draft = $this->draft();
        $this->delete(route('portal.drafts.destroy', $draft), [])->assertSessionHasErrors('version');
        $draft->forceFill(['version' => 2])->save();
        $this->delete(route('portal.drafts.destroy', $draft), ['version' => 1])->assertSessionHasErrors('version');
        $this->assertFalse($draft->fresh()->trashed());
        $this->assertFalse(AuditLog::query()->where('event', 'applicant_draft_removed')->exists());
    }

    public function test_pending_and_paid_checks_protect_draft_even_if_page_was_open_before_payment(): void
    {
        $draft = $this->draft();
        $payment = new CheckPayment;
        $payment->forceFill(['draft_id' => $draft->id, 'user_id' => $draft->user_id, 'amount_sen' => 100,
            'reference' => 'PAY-TEST', 'status' => 'pending', 'gateway' => 'staging'])->save();
        foreach (['pending', 'paid'] as $status) {
            $payment->forceFill(['status' => $status, 'paid_at' => $status === 'paid' ? now() : null,
                'gateway_event_id' => $status === 'paid' ? 'evt-test' : null])->save();
            $this->delete(route('portal.drafts.destroy', $draft), ['version' => 1])->assertForbidden();
            $this->get(route('portal.home'))->assertOk()->assertDontSee('action="'.route('portal.drafts.destroy', $draft).'"', false);
            $this->get(route('portal.drafts.index'))->assertOk()->assertDontSee('action="'.route('portal.drafts.destroy', $draft).'"', false);
        }
        $this->assertFalse($draft->fresh()->trashed());
        $this->assertSame(1, DB::table('core.applicant_draft_revisions')->count());
    }
}
