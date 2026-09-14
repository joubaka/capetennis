<?php

namespace Tests\Unit;

use App\Services\EventBriefAiService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventBriefAiServiceTest extends TestCase
{
    #[Test]
    public function it_uses_the_jta_gemini_contract_and_returns_a_structured_draft(): void
    {
        config()->set('services.gemini', [
            'api_key' => 'test-key',
            'model' => 'test-model',
            'model_fast' => 'test-fast-model',
            'attempts' => 2,
            'timeout' => 50,
            'verify_ssl' => true,
        ]);

        $draft = [
            'name' => 'Cape Junior Open',
            'start_date' => '2026-10-18',
            'end_date' => '2026-10-20',
            'event_type_id' => 4,
            'information' => '<h3>Tournament format</h3><p>Junior singles tournament.</p><script>alert(1)</script>',
            'venue_notes' => 'Bellville Tennis Club',
            'entryFee' => 350,
            'deadline' => 7,
            'withdrawal_deadline' => null,
            'organizer' => 'Cape Tennis',
            'email' => 'events@example.test',
            'published' => false,
            'signUp' => true,
            'review_notes' => ['Confirm the withdrawal deadline.'],
        ];

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($draft, JSON_THROW_ON_ERROR)]]],
                ]],
            ]),
        ]);

        $result = app(EventBriefAiService::class)->extract(
            'Cape Junior Open is at Bellville from 18 to 20 October 2026.',
            [['id' => 4, 'name' => 'Individual']],
            123
        );

        $this->assertSame('<h3>Tournament format</h3><p>Junior singles tournament.</p>', $result['information']);
        $this->assertStringNotContainsString('script', $result['information']);
        $this->assertSame(collect($draft)->except('information')->all(), collect($result)->except('information')->all());

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains($request->url(), '/models/test-fast-model:generateContent?key=test-key')
                && $payload['generationConfig']['responseMimeType'] === 'application/json'
                && $payload['generationConfig']['responseSchema']['type'] === 'OBJECT'
                && $payload['generationConfig']['temperature'] === 0.1
                && $payload['generationConfig']['maxOutputTokens'] === 2048
                && str_contains($payload['contents'][0]['parts'][0]['text'], 'Individual')
                && str_contains($payload['contents'][0]['parts'][0]['text'], 'untrusted source material');
        });
    }

    #[Test]
    public function it_rejects_an_event_type_not_offered_by_cape_tennis(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.model_fast', 'test-fast-model');

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'name' => null, 'start_date' => null, 'end_date' => null,
                        'event_type_id' => 999, 'information' => null, 'venue_notes' => null,
                        'entryFee' => null, 'deadline' => null, 'withdrawal_deadline' => null,
                        'organizer' => null, 'email' => null, 'published' => false,
                        'signUp' => false, 'review_notes' => [],
                    ], JSON_THROW_ON_ERROR)]]],
                ]],
            ]),
        ]);

        $result = app(EventBriefAiService::class)->extract(
            'An event notice with no recognizable Cape Tennis event type.',
            [['id' => 4, 'name' => 'Individual']],
            123
        );

        $this->assertNull($result['event_type_id']);
    }
}
