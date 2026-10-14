<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\InterprovincialTrials\RegistrationMessage;
use Tests\TestCase;

class InterproRegistrationMessageTest extends TestCase
{
    public function test_default_registration_email_uses_event_name_without_closing_dates(): void
    {
        $event = new Event(['name' => 'Interdistricts 2026', 'published' => true, 'signUp' => true,
            'start_date' => now()->addMonth(), 'deadline' => 7]);
        $draft = app(RegistrationMessage::class)->draft($event);
        $this->assertStringContainsString('Registration information', $draft['subject']);
        $this->assertStringContainsString('If your player is listed for registration', $draft['body']);
        $this->assertStringContainsString('Congratulations on being nominated for Interdistricts 2026!', $draft['body']);
        $this->assertStringNotContainsString('Registration closing date', $draft['body']);
        $this->assertStringNotContainsString($event->registrationClosesAt()->format('d F Y'), $draft['body']);
        $this->assertStringNotContainsString('Accept/Decline', $draft['body']);
    }

    public function test_communication_default_keeps_player_details_and_event_link_before_signoff(): void
    {
        $draft = app(RegistrationMessage::class)->communicationDraft(new Event(['name' => 'Trials']));
        $this->assertStringNotContainsString('nominated', $draft['body']);
        $this->assertStringContainsString('registration information for Trials', $draft['body']);
        $this->assertLessThan(strpos($draft['body'], 'Kind regards,'), strpos($draft['body'], '{players}'));
        $this->assertLessThan(strpos($draft['body'], 'Kind regards,'), strpos($draft['body'], '{url}'));
    }

    public function test_closed_or_unpublished_events_do_not_claim_registration_is_open(): void
    {
        foreach ([['published' => false, 'signUp' => true], ['published' => true, 'signUp' => false],
            ['published' => true, 'signUp' => true, 'start_date' => now()->subMonth(), 'deadline' => 7]] as $attributes) {
            $draft = app(RegistrationMessage::class)->draft(new Event(['name' => 'Trials', ...$attributes]));
            $this->assertStringContainsString('Registration is currently closed', $draft['body']);
            $this->assertStringNotContainsString('Registration is open', $draft['subject']);
        }
    }

    public function test_preview_and_emitted_invitation_share_clean_escaped_content_and_exact_link(): void
    {
        $data = ['recipientName' => '[recipient name]', 'messageBody' => "Dear players and parents,\n\nCustom <script> instructions\nOrganiser reminder: 30 October 2026\n\nKind regards,\nOrganiser", 'invitationUrl' => '#'];
        $html = view('emails.interprovincial-trials.invitation', $data)->render();
        $this->assertSame($html, view('emails.interprovincial-trials.invitation-preview', ['body' => $data['messageBody']])->render());
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Organiser reminder: 30 October 2026', $html);
        $this->assertSame(1, substr_count($html, 'Kind regards,'));
        $link = 'https://example.test/events/255?nomination=987';
        $html = view('emails.interprovincial-trials.invitation', [...$data, 'invitationUrl' => $link])->render();
        $this->assertStringContainsString('href="'.$link.'"', $html);
        $this->assertStringContainsString('View event</a>', $html);
        $this->assertStringNotContainsString('View event and respond', $html);
    }
}
