<?php

namespace Tests\Feature\Amira;

use App\Models\AmiraConversation;
use App\Models\AmiraMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

class PruneAmiraConversationsTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_conversations_idle_past_retention_are_deleted_with_their_messages(): void
    {
        $user = $this->applicant();
        $old = AmiraConversation::for($user, null);
        $old->messages()->create(['role' => 'psp', 'body' => 'Lama']);
        $old->forceFill(['last_message_at' => now()->subMonths(12)->subDay()])->save();
        $recent = AmiraConversation::for($user, $this->telDraft($user));
        $recent->messages()->create(['role' => 'psp', 'body' => 'Baru']);
        $recent->forceFill(['last_message_at' => now()->subMonths(11)])->save();

        $this->artisan('amira:prune')->expectsOutputToContain('1')->assertSuccessful();

        $this->assertNull(AmiraConversation::query()->find($old->id));
        $this->assertNotNull(AmiraConversation::query()->find($recent->id));
        $this->assertSame(['Baru'], AmiraMessage::query()->pluck('body')->all());
    }
}
