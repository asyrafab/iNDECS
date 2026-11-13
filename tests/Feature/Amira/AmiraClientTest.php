<?php

namespace Tests\Feature\Amira;

use App\Services\Amira\AmiraClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AmiraClientTest extends TestCase
{
    private const TOKEN = 'amiratesttoken_0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config(['amira.enabled' => true, 'amira.url' => 'http://127.0.0.1:8770', 'amira.token' => self::TOKEN]);
    }

    private function request(): array
    {
        return ['subject' => str_repeat('a', 64), 'locale' => 'ms-MY', 'question' => 'Hai', 'history' => [],
            'context' => ['page' => 'home', 'application_type' => null, 'particulars' => null, 'checklist' => [],
                'options' => ['application_types' => [], 'work_types' => [], 'building_categories' => [], 'site_conditions' => []], 'sources' => []]];
    }

    private function reply(array $overrides = []): array
    {
        return ['data' => array_replace(['reply' => 'Hai juga.', 'source_ids' => ['S1'], 'proposal' => ['work_type' => 'tambahan'],
            'mentions_outcome' => false, 'model' => 'qwen/qwen3.5-9b', 'ai_generated' => true, 'official_decision' => false], $overrides)];
    }

    public function test_sends_token_and_exact_keys(): void
    {
        Http::fake(['127.0.0.1:8770/api/v1/conversation' => Http::response($this->reply())]);
        $reply = app(AmiraClient::class)->ask($this->request());
        $this->assertSame('Hai juga.', $reply->reply);
        $this->assertSame(['S1'], $reply->sourceIds);
        $this->assertSame(['work_type' => 'tambahan'], $reply->proposal);
        $this->assertFalse($reply->mentionsOutcome);
        $this->assertSame('qwen/qwen3.5-9b', $reply->model);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && array_keys($request->data()) === ['subject', 'locale', 'question', 'history', 'context']);
    }

    public function test_error_status_throws(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'MODEL_UNAVAILABLE']], 502)]);
        $this->expectException(RuntimeException::class);
        app(AmiraClient::class)->ask($this->request());
    }

    public function test_wrong_shape_throws(): void
    {
        Http::fake(['*' => Http::response($this->reply(['source_ids' => 'S1']))]);
        $this->expectException(RuntimeException::class);
        app(AmiraClient::class)->ask($this->request());
    }

    public function test_disabled_throws_without_calling(): void
    {
        Http::fake();
        config(['amira.enabled' => false]);
        try {
            app(AmiraClient::class)->ask($this->request());
            $this->fail('Disabled client called AMIRA.');
        } catch (RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_plain_http_to_a_remote_host_is_refused(): void
    {
        Http::fake();
        config(['amira.url' => 'http://amira.example.com']);
        $this->expectException(RuntimeException::class);
        app(AmiraClient::class)->ask($this->request());
    }
}
