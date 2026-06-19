<?php

namespace Tests\Unit;

use App\Mail\AnnouncementMail;
use App\Mail\BulkEventMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

class BulkMailableTest extends TestCase
{
    /**
     * Test that BulkEventMail does NOT implement ShouldQueue.
     * This is critical - implementing ShouldQueue would cause double-queuing
     * since the mailable is sent from within SendBulkEmailJob (which is already queued).
     */
    public function test_bulk_event_mail_does_not_implement_should_queue()
    {
        $mailable = new BulkEventMail(
            subjectLine: 'Test Subject',
            body: '<p>Test Body</p>',
            fromName: 'Test Sender',
            replyToAddress: 'test@example.com'
        );

        $this->assertNotInstanceOf(
            ShouldQueue::class,
            $mailable,
            'BulkEventMail must NOT implement ShouldQueue to prevent double-queuing and rate limit bypass'
        );
    }

    /**
     * Test that AnnouncementMail does NOT implement ShouldQueue.
     * This is critical - implementing ShouldQueue would cause double-queuing
     * since the mailable is sent from within SendBulkEmailJob (which is already queued).
     */
    public function test_announcement_mail_does_not_implement_should_queue()
    {
        $mailable = new AnnouncementMail([
            'event' => 'Test Event',
            'announcement' => 'Test announcement body',
        ]);

        $this->assertNotInstanceOf(
            ShouldQueue::class,
            $mailable,
            'AnnouncementMail must NOT implement ShouldQueue to prevent double-queuing and rate limit bypass'
        );
    }

    /**
     * Test that BulkEventMail builds correctly with subject and reply-to.
     */
    public function test_bulk_event_mail_builds_with_correct_headers()
    {
        $mailable = new BulkEventMail(
            subjectLine: 'Tournament Update',
            body: '<p>Important tournament information</p>',
            fromName: 'Cape Tennis',
            replyToAddress: 'tournament@example.com'
        );

        $built = $mailable->build();

        $this->assertEquals('Tournament Update', $built->subject);
        $this->assertNotEmpty($built->replyTo, 'Reply-to should be set');
    }

    /**
     * Test that AnnouncementMail builds with correct subject.
     */
    public function test_announcement_mail_builds_with_event_subject()
    {
        $mailable = new AnnouncementMail([
            'event' => 'Summer Championship 2024',
            'announcement' => 'Registration is now open',
        ]);

        $envelope = $mailable->envelope();

        $this->assertEquals('Announcement – Summer Championship 2024', $envelope->subject);
    }
}
