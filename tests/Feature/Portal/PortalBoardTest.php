<?php

namespace Tests\Feature\Portal;

use App\Enums\AmiraMood;
use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\Portal\PortalBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBoardTest extends TestCase
{
    use BuildsApplicants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPortalTypes();
    }

    public function test_unverified_user_gets_verify_todo_and_alert_mood(): void
    {
        $board = app(PortalBoard::class)->for($this->applicant(verified: false));

        $this->assertSame(AmiraMood::Alert, $board['mood']);
        $this->assertSame('Sahkan emel anda untuk mula menyediakan permohonan.', $board['todos'][0]['text']);
        $this->assertSame(route('portal.account'), $board['todos'][0]['href']);
    }

    public function test_verified_user_with_drafts_is_calm_and_sees_only_own_drafts(): void
    {
        $owner = $this->applicant();
        $other = $this->applicant('other@example.test');
        $this->draftFor($owner);
        $this->draftFor($owner);
        $this->draftFor($other);

        $board = app(PortalBoard::class)->for($owner);

        $this->assertSame(AmiraMood::Calm, $board['mood']);
        $this->assertSame(2, $board['draftCount']);
        $this->assertCount(2, $board['sections']['active']);
        $this->assertTrue($board['sections']['active']->every(fn ($draft) => $draft->user_id === $owner->id));
        $this->assertCount(0, $board['sections']['action']);
        $this->assertCount(0, $board['sections']['closed']);
        $this->assertSame([], $board['todos']);
    }

    public function test_just_saved_makes_amira_happy(): void
    {
        $this->assertSame(AmiraMood::Happy, app(PortalBoard::class)->for($this->applicant(), justSaved: true)['mood']);
    }

    public function test_greeting_names_the_user(): void
    {
        $this->assertSame('Selamat datang, Ar. Lim.', app(PortalBoard::class)->for($this->applicant(name: 'Ar. Lim'))['greeting']);
    }

    private function draftFor(User $user): ApplicantDraft
    {
        $draft = new ApplicantDraft($this->data());
        $draft->user_id = $user->id;
        $draft->save();

        return $draft;
    }
}
