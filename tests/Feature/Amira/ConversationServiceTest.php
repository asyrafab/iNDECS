<?php

namespace Tests\Feature\Amira;

use App\Jobs\AnswerAmiraMessage;
use App\Models\AmiraConversation;
use App\Services\Amira\ConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Precheck\BuildsPrecheck;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use BuildsPrecheck;
    use RefreshDatabase;

    /** These tests act as a PSP who has accepted the AMIRA agreement. */
    protected function amiraConsentByDefault(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Queue::fake();
    }

    private function service(): ConversationService
    {
        return app(ConversationService::class);
    }

    /** Fill today's quota with answered PSP questions. */
    private function answered(AmiraConversation $conversation, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $conversation->messages()->create(['role' => 'psp', 'body' => "Soalan {$i}"]);
            $conversation->messages()->create(['role' => 'amira', 'body' => "Jawapan {$i}"]);
        }
    }

    public function test_ask_stores_the_question_and_queues_the_answer(): void
    {
        $user = $this->applicant();
        $conversation = $this->service()->ask($user, null, '  Hai AMIRA  ');
        $this->assertSame(['Hai AMIRA'], $conversation->messages()->pluck('body')->all());
        $this->assertNotNull($conversation->fresh()->last_message_at);
        Queue::assertPushedOn('amira', AnswerAmiraMessage::class, fn (AnswerAmiraMessage $job) => $job->conversationId === $conversation->id);
    }

    public function test_second_question_while_pending_is_a_conflict(): void
    {
        $user = $this->applicant();
        $this->service()->ask($user, null, 'Satu');
        try {
            $this->service()->ask($user, null, 'Dua');
            $this->fail('Second question accepted while pending.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_thirty_first_question_today_is_refused_but_yesterday_does_not_count(): void
    {
        $user = $this->applicant();
        $conversation = AmiraConversation::for($user, null);
        $this->travelTo(now()->subDay());
        $this->answered($conversation, 5);
        $this->travelBack();
        $this->answered($conversation, 29);
        $this->service()->ask($user, null, 'Ke-30');
        $conversation->messages()->create(['role' => 'amira', 'body' => 'Jawapan']);
        try {
            $this->service()->ask($user, null, 'Ke-31');
            $this->fail('Daily limit not enforced.');
        } catch (ValidationException $exception) {
            $this->assertSame('Had 30 soalan sehari telah dicapai. Cuba lagi esok.', $exception->errors()['question'][0]);
        }
    }

    public function test_empty_and_overlong_questions_are_refused(): void
    {
        $user = $this->applicant();
        foreach (['   ', str_repeat('a', 1001)] as $text) {
            try {
                $this->service()->ask($user, null, $text);
                $this->fail('Invalid question accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('question', $exception->errors());
            }
        }
        Queue::assertNothingPushed();
    }

    public function test_unverified_account_is_forbidden(): void
    {
        $this->expectException(HttpException::class);
        $this->service()->ask($this->applicant('baru@example.test', verified: false), null, 'Hai');
    }

    public function test_another_applicants_draft_is_not_found(): void
    {
        $draft = $this->telDraft($this->applicant('pemilik@example.test'));
        $this->expectException(ModelNotFoundException::class);
        $this->service()->ask($this->applicant('lain@example.test'), $draft, 'Hai');
    }
}
