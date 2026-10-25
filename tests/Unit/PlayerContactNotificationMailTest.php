<?php

namespace Tests\Unit;

use App\Mail\MastersInvitationMail;
use App\Mail\TeamSelectionInvitationMail;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use Tests\TestCase;

class PlayerContactNotificationMailTest extends TestCase
{
    public function test_notification_copies_do_not_depend_on_clothing_ordering_availability(): void
    {
        $policy = new \App\Services\TeamSelection\TeamSelectionClothingMailPolicy;
        $this->assertTrue($policy->includesClothing(['kind' => 'invitation', 'campaign' => ['include_clothing' => true]]));
        $this->assertFalse($policy->includesClothing(['kind' => 'invitation',
            'campaign' => ['include_clothing' => true, 'notification_only' => true]]));
    }

    public function test_team_contact_copy_contains_no_invitation_decision_or_payment_links(): void
    {
        $event = new Event(['name' => 'Contact event', 'published' => true]);
        $event->id = 12;
        $import = (new TeamSelectionImport)->setRelation('event', $event);
        $invitation = (new TeamSelectionInvitation)->setRelation('selectionImport', $import)
            ->setRelation('player', new Player(['name' => 'Contact', 'surname' => 'Player']));
        $mail = new TeamSelectionInvitationMail($invitation, 'replacement', ['notification_only' => true,
            'message' => 'Schedule updated <script>unsafe</script>']);
        $html = $mail->render();

        $this->assertStringContainsString(route('events.show', $event), $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('action=decline', $html);
        $this->assertStringNotContainsString('team-selection', $html);
        $this->assertStringNotContainsString('signature=', $html);
        $this->assertStringNotContainsString('Register and pay', $html);
    }

    public function test_masters_decline_copy_has_no_confirmation_link_and_hides_unpublished_event(): void
    {
        $event = new Event(['name' => 'Private event', 'published' => false]);
        $event->id = 13;
        $batch = (new MastersInvitationBatch)->setRelation('event', $event);
        $invitation = (new MastersInvitation)->setRelation('batch', $batch)
            ->setRelation('player', new Player(['name' => 'Contact', 'surname' => 'Player']));
        $html = (new MastersInvitationMail($invitation, 'declined', ['notification_only' => true]))->render();

        $this->assertStringContainsString('Your unavailability has been recorded.', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringNotContainsString('confirm-decline', $html);
        $this->assertStringNotContainsString('signature=', $html);
    }
}
