<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\EventCommunicationBatch;
use App\Services\EventCommunicationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class EventEmailReviewGuardTest extends TestCase
{
    private function event(bool $trial, bool $team = false): Event
    {
        $event = new class extends Event
        {
            public bool $trial = false;
            public bool $team = false;
            public function isTeam(): bool { return $this->team; }
            public function isInterprovincialTrials(): bool { return $this->trial; }
        };
        $event->id = 241;
        $event->trial = $trial;
        $event->team = $team;

        return $event;
    }

    private function send(Event $event, bool $reviewed = false): void
    {
        Mail::mailer('array')->send(['html' => new HtmlString('<p>Exact approved content</p>')], ['event' => $event, 'event_mail_reviewed' => $reviewed], function ($message) {
            $message->to('parent@example.test', 'Parent')->from('sender@example.test')->subject('Registration confirmed');
        });
    }

    public function test_interpro_automatic_mail_is_staged_and_never_reaches_transport(): void
    {
        $event = $this->event(true);
        $service = $this->mock(EventCommunicationService::class);
        $service->shouldReceive('draftFixed')->once()->withArgs(function ($actual, $source, $recipients, $subject) use ($event) {
            $this->assertSame($event, $actual);
            $this->assertStringStartsWith('transaction-mail:241:', $source);
            $this->assertSame('Registration confirmed', $subject);
            $this->assertSame('parent@example.test', $recipients[0]['email']);
            $this->assertStringContainsString('Exact approved content', $recipients[0]['html']);

            return true;
        })->andReturn(new EventCommunicationBatch);
        $this->send($event);

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_approved_interpro_mail_bypasses_review_guard(): void
    {
        $this->mock(EventCommunicationService::class)->shouldNotReceive('draftFixed');
        $this->send($this->event(true), true);

        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_ordinary_individual_mail_is_unchanged(): void
    {
        $this->mock(EventCommunicationService::class)->shouldNotReceive('draftFixed');
        $this->send($this->event(false));

        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_team_automatic_mail_is_also_staged_before_transport(): void
    {
        $this->mock(EventCommunicationService::class)->shouldReceive('draftFixed')->once()->andReturn(new EventCommunicationBatch);
        $this->send($this->event(false, true));

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
