<?php

namespace Tests\Unit;

use App\Mail\TeamSelectionInvitationMail;
use App\Models\Event;
use App\Models\Player;
use App\Models\TeamRegion;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use Tests\TestCase;

class TeamSelectionReplacementMailTest extends TestCase
{
    public function test_communications_compose_draft_has_event_player_deadline_and_registration_link_without_clothing(): void
    {
        $invitation = $this->invitation();
        $event = $invitation->selectionImport->event;
        $event->setRelation('venues', collect());
        $draft = app(\App\Services\EventCommunicationService::class)->replacementInvitationDraft($event, $invitation);

        $this->assertStringContainsString('Replacement invitation', $draft['subject']);
        $this->assertStringContainsString("following another player's withdrawal", $draft['body']);
        $this->assertStringContainsString('Regional Event', $draft['body']);
        $this->assertStringContainsString('New Player', $draft['body']);
        $this->assertStringContainsString('Please register by:', $draft['body']);
        $this->assertStringContainsString('#team-registration-3-4', $draft['body']);
        $this->assertStringNotContainsString('clothing', strtolower($draft['body']));
    }

    public function test_replacement_email_explains_vacancy_and_registration_without_original_announcement_or_clothing(): void
    {
        $invitation = $this->invitation();
        $campaign = [
            'message' => 'Original clothing announcement',
            'include_clothing' => true,
            'clothing_items' => [['name' => 'Regional shirt', 'price' => 250, 'sizes' => ['M']]],
            'event' => ['name' => 'Regional Event', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'venues' => ['Regional Courts']],
        ];
        $mail = new TeamSelectionInvitationMail($invitation, 'replacement', $campaign);
        $html = $mail->render();

        $this->assertStringContainsString("following another player's withdrawal", $html);
        $this->assertStringContainsString('Please complete your event registration', $html);
        $this->assertStringContainsString('Regional Event', $html);
        $this->assertStringContainsString('01 Dec 2026', $html);
        $this->assertStringContainsString('Regional Courts', $html);
        $this->assertStringContainsString('#team-registration-3-4', $html);
        $this->assertStringNotContainsString('clothing', strtolower($html));
        $this->assertStringNotContainsString('Regional shirt', $html);

        $original = (new TeamSelectionInvitationMail($invitation, 'invitation', $campaign))->render();
        $this->assertStringContainsString('Original clothing announcement', $original);
        $this->assertStringContainsString('Optional regional clothing', $original);
    }

    public function test_promotions_and_post_announcement_activations_use_replacement_kind_but_draft_activations_do_not(): void
    {
        $invitation = $this->invitation();
        $this->assertSame('invitation', $invitation->invitationMailKind());
        $invitation->snapshot_json = ['activation' => ['activated_at' => now()->toIso8601String(), 'pending_manual_invitation' => false]];
        $this->assertSame('invitation', $invitation->invitationMailKind());
        $invitation->response_deadline_override = now()->addDay();
        $this->assertSame('replacement', $invitation->invitationMailKind());
        $invitation->snapshot_json = [];
        $invitation->response_deadline_override = null;
        $invitation->promoted_from_id = 9;
        $this->assertSame('replacement', $invitation->invitationMailKind());
    }

    private function invitation(): TeamSelectionInvitation
    {
        $invitation = new TeamSelectionInvitation(['event_id' => 2, 'team_id' => 3, 'player_id' => 4]);
        $invitation->id = 1;
        $import = new TeamSelectionImport(['response_deadline' => now()->addDay()]);
        $import->setRelation('event', new Event(['name' => 'Regional Event', 'published' => true]));
        $import->event->id = 2;
        $invitation->setRelation('selectionImport', $import);
        $invitation->setRelation('player', new Player(['name' => 'New', 'surname' => 'Player']));
        $invitation->setRelation('region', new TeamRegion(['region_name' => 'Overberg']));

        return $invitation;
    }
}
