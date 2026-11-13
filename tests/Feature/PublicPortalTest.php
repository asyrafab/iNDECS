<?php

namespace Tests\Feature;

use App\Models\ApplicantDraft;
use App\Models\ApplicationType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Portal\BuildsApplicants;
use Tests\TestCase;

class PublicPortalTest extends TestCase
{
    use BuildsApplicants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortalTypes();
    }

    public function test_verified_applicant_can_save_and_resume_a_draft_without_formal_side_effects(): void
    {
        Bus::fake();
        $user = $this->applicant();
        $this->actingAs($user)->get(route('portal.home'))->assertOk()->assertSee('Belum ada fail');
        $this->get(route('portal.drafts.create'))->assertOk()->assertSee('Maklumat asas cadangan');
        $this->post(route('portal.drafts.store'), $this->data() + [
            'user_id' => 999, 'current_status' => 'accepted', 'version' => 99,
        ])->assertRedirect();
        $draft = ApplicantDraft::query()->firstOrFail();
        $this->assertSame($user->id, $draft->user_id);
        $this->assertSame(1, $draft->version);
        $this->get(route('portal.home'))->assertOk()->assertSee('Rumah di Lot 101');
        $this->get(route('portal.drafts.index'))->assertOk()->assertSee('Rumah di Lot 101');
        $this->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee('Rumah di Lot 101');
        $this->put(route('portal.drafts.update', $draft), array_replace($this->data(), ['title' => 'Cadangan dikemas kini', 'version' => 1]))->assertRedirect(route('portal.drafts.edit', $draft));
        $this->assertSame(2, $draft->fresh()->version);
        $revisions = DB::table('core.applicant_draft_revisions')->orderBy('version')->get();
        $this->assertCount(2, $revisions);
        $this->assertSame('Rumah di Lot 101', json_decode($revisions[0]->snapshot, true)['title']);
        $this->assertSame('Cadangan dikemas kini', json_decode($revisions[1]->snapshot, true)['title']);
        $this->assertSame($user->id, $revisions[1]->actor_id);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('core.accepted_submissions', 0);
        $this->assertDatabaseCount('airs.review_versions', 0);
        Bus::assertNothingDispatched();
    }

    public function test_drafts_are_private_on_dashboard_list_read_and_update(): void
    {
        $owner = $this->applicant();
        $this->actingAs($owner)->post(route('portal.drafts.store'), $this->data());
        $draft = ApplicantDraft::query()->firstOrFail();
        $other = $this->applicant('other@example.test');
        $this->actingAs($other)->get(route('portal.home'))->assertOk()->assertDontSee('Rumah di Lot 101');
        $this->get(route('portal.drafts.index'))->assertOk()->assertDontSee('Rumah di Lot 101');
        $this->get(route('portal.drafts.edit', $draft))->assertNotFound();
        $this->put(route('portal.drafts.update', $draft), [])->assertNotFound();
        $this->assertSame(1, $draft->fresh()->version);
        $this->assertDatabaseCount('core.applicant_draft_revisions', 1);
    }

    public function test_application_chooser_filters_types_and_prefills_only_an_available_type(): void
    {
        ApplicationType::query()->create(['id' => 'PL', 'name' => 'Unreleased landscape intake', 'primary_department_id' => 'JPBD', 'is_active' => true]);
        ApplicationType::query()->create(['id' => 'PB', 'name' => 'Inactive building intake', 'primary_department_id' => 'JPBD', 'is_active' => false]);
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('Fail saya')->assertSee('Permohonan baharu')
            ->assertDontSee('Semak Permohonan')->assertDontSee('id="portal-check"', false)
            ->assertSee(route('portal.drafts.create', ['type' => 'PKM']), false)
            ->assertDontSee('Unreleased landscape intake')->assertDontSee('Inactive building intake')
            ->assertDontSee('id="sidebar"', false);
        $this->get(route('portal.drafts.create', ['type' => 'PKM']))->assertOk()
            ->assertViewHas('draft', fn ($draft) => $draft->application_type_id === 'PKM');
        foreach (['PL', 'PB', 'unknown'] as $type) {
            $this->get(route('portal.drafts.create', ['type' => $type]))->assertOk()
                ->assertViewHas('draft', fn ($draft) => $draft->application_type_id === null);
        }
        $this->assertDatabaseCount('core.applicant_drafts', 0);
    }

    public function test_empty_board_shows_invitation_not_empty_sections(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('Belum ada fail')->assertDontSee('data-section="active"', false);
    }

    public function test_unverified_home_shows_alert_amira_and_verify_notice(): void
    {
        $this->actingAs($this->applicant(verified: false))->get(route('portal.home'))->assertOk()
            ->assertSee('data-mood="alert"', false)
            ->assertSee('Sahkan emel anda untuk mula menyediakan permohonan.')
            ->assertSee('Sahkan emel anda sebelum memulakan draf.');
    }

    public function test_draft_rows_are_labelled_as_unsent_drafts(): void
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.store'), $this->data());
        $this->get(route('portal.home'))->assertOk()->assertSee('data-section="active"', false)
            ->assertSee('Draf · PKM')->assertSee('Belum dihantar kepada MBSP')->assertSee('Rumah di Lot 101');
    }

    public function test_draft_editor_uses_counter_and_amira_is_happy_after_save(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.drafts.create'))->assertOk()
            ->assertSee('class="counter"', false)->assertSee('data-mood="calm"', false);
        $this->followingRedirects()->post(route('portal.drafts.store'), $this->data())->assertOk()
            ->assertSee('class="counter"', false)->assertSee('data-mood="happy"', false);
    }

    public function test_public_library_documents_appear_for_their_type_and_download(): void
    {
        $doc = $this->libraryDocument('Garis panduan PKM', 'public');
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertSee('Garis panduan PKM')->assertSee(route('portal.library.download', $doc), false)->assertSee('PDF · v1');
        $response = $this->get(route('portal.library.download', $doc))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_internal_or_retired_documents_are_hidden_and_404(): void
    {
        $internal = $this->libraryDocument('Nota dalaman', 'internal');
        $retired = $this->libraryDocument('Garis lama', 'public');
        app(\App\Services\Library\LibraryService::class)->retire($this->libraryAuthor(), $retired);
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()
            ->assertDontSee('Nota dalaman')->assertDontSee('Garis lama');
        $this->get(route('portal.library.download', $internal))->assertNotFound();
        $this->get(route('portal.library.download', $retired))->assertNotFound();
    }

    public function test_type_without_documents_keeps_belum_tersedia(): void
    {
        $this->actingAs($this->applicant())->get(route('portal.home'))->assertOk()->assertSee('Belum tersedia');
    }

    public function test_revision_snapshots_cannot_be_updated_or_deleted(): void
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.store'), $this->data());
        foreach (['update', 'delete'] as $operation) {
            try {
                DB::transaction(function () use ($operation): void {
                    $query = DB::table('core.applicant_draft_revisions');
                    $operation === 'update' ? $query->update(['snapshot' => '{}']) : $query->delete();
                });
                $this->fail('Revision mutation must be rejected.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', $exception->errorInfo[0]);
            }
        }
        $this->assertDatabaseCount('core.applicant_draft_revisions', 1);
    }

    public function test_stale_save_preserves_input_and_never_overwrites_newer_revision(): void
    {
        $this->actingAs($this->applicant())->post(route('portal.drafts.store'), $this->data());
        $draft = ApplicantDraft::query()->firstOrFail();
        $this->put(route('portal.drafts.update', $draft), $this->data() + ['version' => 1])->assertRedirect();
        $this->from(route('portal.drafts.edit', $draft))->put(route('portal.drafts.update', $draft), array_replace($this->data(), ['title' => 'Stale changes', 'version' => 1]))
            ->assertRedirect(route('portal.drafts.edit', $draft))->assertSessionHasErrors('version')->assertSessionHasInput('title', 'Stale changes');
        $this->assertSame('Rumah di Lot 101', $draft->fresh()->title);
        $this->assertSame(2, $draft->fresh()->version);
        $this->assertDatabaseCount('core.applicant_draft_revisions', 2);
        $this->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee('Stale changes')->assertSee('name="version" value="1"', false);
    }

    public function test_unverified_accounts_have_account_access_but_cannot_use_draft_editor(): void
    {
        $user = $this->applicant();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->get(route('portal.home'))->assertOk()->assertSee('Sahkan emel anda');
        $this->get(route('portal.account'))->assertOk()->assertSee('Hantar semula pengesahan');
        $this->get(route('portal.drafts.create'))->assertForbidden();
        $this->post(route('portal.drafts.store'), $this->data())->assertForbidden();
        $this->assertDatabaseCount('core.applicant_drafts', 0);
    }

    public function test_email_link_guests_can_prepare_drafts_and_claim_account_on_account_page(): void
    {
        $user = $this->applicant();
        $user->forceFill(['account_type' => 'guest', 'has_password' => false])->save();
        $this->actingAs($user)->post(route('portal.drafts.store'), $this->data())->assertRedirect();
        $this->get(route('portal.account'))->assertOk()->assertSee('Daftar akaun tetap');
    }

    public function test_anonymous_staff_and_inactive_users_cannot_access_portal(): void
    {
        foreach (['portal.home', 'portal.account', 'portal.drafts.index', 'portal.drafts.create'] as $route) {
            $this->get(route($route))->assertRedirect(route('portal.login'));
        }
        $this->post(route('portal.drafts.store'), $this->data())->assertRedirect(route('portal.login'));
        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('portal.drafts.create'))->assertForbidden();
        $this->post(route('portal.drafts.store'), $this->data())->assertForbidden();
        $user = $this->applicant();
        $user->forceFill(['is_active' => false])->save();
        $this->actingAs($user)->get(route('portal.home'))->assertForbidden();
    }

    public function test_validation_rejects_unavailable_types_and_oversized_fields(): void
    {
        $this->actingAs($this->applicant());
        foreach (['PL', 'missing'] as $type) {
            $this->post(route('portal.drafts.store'), array_replace($this->data(), ['application_type_id' => $type]))->assertSessionHasErrors('application_type_id');
        }
        $this->post(route('portal.drafts.store'), array_replace($this->data(), ['title' => '  ', 'notes' => str_repeat('a', 5001), 'site_address' => str_repeat('a', 2001)]))
            ->assertSessionHasErrors(['title', 'notes', 'site_address']);
        ApplicationType::query()->whereKey('PKM')->update(['is_active' => false]);
        $this->post(route('portal.drafts.store'), $this->data())->assertSessionHasErrors('application_type_id');
        ApplicationType::query()->whereKey('PKM')->update(['is_active' => true]);
        Department::query()->whereKey('JPBD')->update(['is_active' => false]);
        $this->post(route('portal.drafts.store'), $this->data())->assertSessionHasErrors('application_type_id');
        $this->assertDatabaseCount('core.applicant_drafts', 0);
    }

    private ?User $libraryAuthor = null;

    private function libraryAuthor(): User
    {
        return $this->libraryAuthor ??= User::factory()->create(['department_id' => 'JPBD', 'role' => 'penolong_pengarah']);
    }

    private function libraryDocument(string $title, string $visibility): \App\Models\LibraryDocument
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        return app(\App\Services\Library\LibraryService::class)->create($this->libraryAuthor(), [
            'title' => $title, 'category' => 'garis_panduan', 'visibility' => $visibility, 'application_type_ids' => ['PKM'],
        ], \Illuminate\Http\UploadedFile::fake()->createWithContent('dokumen.pdf', "%PDF-1.4\n%%EOF\n"));
    }

    public function test_draft_quota_is_per_owner_and_list_is_paginated(): void
    {
        $user = $this->applicant();
        for ($i = 0; $i < 100; $i++) {
            $draft = new ApplicantDraft($this->data());
            $draft->user_id = $user->id;
            $draft->save();
        }
        $this->actingAs($user)->post(route('portal.drafts.store'), $this->data())->assertSessionHasErrors('title');
        $this->get(route('portal.drafts.index'))->assertOk()->assertViewHas('drafts', fn ($drafts) => $drafts->count() === 15 && $drafts->total() === 100);
        $this->actingAs($this->applicant('other@example.test'))->post(route('portal.drafts.store'), $this->data())->assertRedirect();
        $this->assertDatabaseCount('core.applicant_drafts', 101);
    }

}
