<?php

namespace Tests\Feature\Amira;

use App\Models\AmiraConversation;
use App\Models\AmiraMessage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

class ConversationStorageTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_one_conversation_per_user_and_page(): void
    {
        $user = $this->applicant();
        $draft = $this->telDraft($user);
        $home = AmiraConversation::for($user, null);
        $this->assertTrue($home->is(AmiraConversation::for($user, null)));
        $onDraft = AmiraConversation::for($user, $draft);
        $this->assertFalse($home->is($onDraft));
        $this->assertTrue($onDraft->is(AmiraConversation::for($user, $draft)));
        $this->assertSame(2, AmiraConversation::query()->count());
    }

    public function test_messages_cannot_be_edited(): void
    {
        $message = AmiraConversation::for($this->applicant(), null)->messages()->create(['role' => 'psp', 'body' => 'Hai']);
        try {
            AmiraMessage::query()->whereKey($message->id)->update(['body' => 'Diubah']);
            $this->fail('Message update was allowed.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }
    }

    public function test_deleting_a_conversation_removes_its_messages(): void
    {
        $conversation = AmiraConversation::for($this->applicant(), null);
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Hai']);
        $conversation->delete();
        $this->assertSame(0, AmiraMessage::query()->count());
    }

    public function test_pending_until_amira_replies(): void
    {
        $conversation = AmiraConversation::for($this->applicant(), null);
        $this->assertFalse($conversation->pending());
        $conversation->messages()->create(['role' => 'psp', 'body' => 'Soalan']);
        $this->assertTrue($conversation->pending());
        $conversation->messages()->create(['role' => 'amira', 'body' => 'Jawapan']);
        $this->assertFalse($conversation->pending());
    }
}
