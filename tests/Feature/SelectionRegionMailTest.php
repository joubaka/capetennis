<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventCommunicationBatch, EventRegion, EventType, Player, Team, TeamPlayer, TeamRegion, TeamResultSelectionDraft, User};
use App\Services\SelectionRegionMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectionRegionMailTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Bus::fake(); Mail::fake();
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Team tournament', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => false, 'team_result_settings' => ['team_size' => 1, 'reserve_count' => 1]]);
        $actor->adminEvents()->attach($event); $this->actingAs($actor);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $regions = collect(['One', 'Two'])->map(function ($name) use ($event, $category) {
            $region = TeamRegion::create(['region_name' => $name]);
            DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id]);
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]);
            foreach ([1, 2] as $rank) {
                $player = Player::factory()->create(['email' => strtolower($name).'@example.test', 'userId' => null]);
                TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 0]);
            }
            return $region;
        });
        $draft = TeamResultSelectionDraft::create(['event_id' => $event->id, 'group_key' => '13-boys', 'version' => 1,
            'region_ids' => $regions->pluck('id')->all(), 'formats' => [], 'selected_keys' => ['a', 'b'], 'reasons' => ['a' => 'Private note'],
            'snapshot' => [['id' => 'a', 'name' => 'Main Child', 'region' => 'Two', 'draft_position' => 1], ['id' => 'b', 'name' => 'Reserve Child', 'region' => 'One', 'draft_position' => 2]], 'updated_by' => $actor->id]);
        return [$event, $actor, $regions, $draft];
    }

    public function test_region_filter_deduplicates_contacts_and_reviews_all_teams_and_reserves_before_idempotent_send(): void
    {
        [$event, , $regions] = $this->scenario();
        $draft = app(SelectionRegionMessage::class)->draft($event);
        $this->get(route('backend.selection-region-mail.compose', $event))->assertOk()->assertSee('Select all participating regions')->assertSee('Reserve 1: Reserve Child')->assertDontSee('Private note');
        $this->post(route('backend.selection-region-mail.preview', $event), $draft + ['selection_region_ids' => [$regions[0]->id]])->assertOk()->assertSee('Review emails');
        $batch = EventCommunicationBatch::sole();
        $this->assertCount(1, $batch->recipients);
        $this->assertSame('one@example.test', $batch->recipients[0]['email']);
        $this->assertCount(2, $batch->recipients[0]['player_keys']);
        $this->assertStringContainsString('Main Child (Two)', $batch->body);
        $this->assertStringContainsString('Reserve 1: Reserve Child', $batch->body);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->get(route('backend.selection-region-mail.compose', ['event' => $event, 'edit_batch' => $batch->id]))->assertOk()->assertSee($batch->subject);
        foreach ([1, 2] as $attempt) $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertFalse($event->fresh()->published);
        Mail::assertNothingSent();
    }

    public function test_all_regions_and_extra_bcc_are_reviewed_and_receive_one_copy_each(): void
    {
        [$event, , $regions] = $this->scenario();
        $input = app(SelectionRegionMessage::class)->draft($event) + [
            'selection_region_ids' => $regions->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'bcc_emails' => "EXTRA@example.test; extra@example.test\nother@example.test, ONE@example.test",
            'from_name' => 'Tournament office', 'reply_to' => 'office@example.test',
        ];
        $this->post(route('backend.selection-region-mail.preview', $event), $input)->assertOk()->assertSee('Extra BCC addresses')->assertSee('extra@example.test');
        $batch = EventCommunicationBatch::sole();
        $this->assertCount(4, $batch->recipients);
        $this->assertEqualsCanonicalizing(['extra@example.test', 'other@example.test'], collect($batch->recipients)->where('kind', 'bcc')->pluck('email')->all());
        $this->get(route('backend.selection-region-mail.compose', ['event' => $event, 'edit_batch' => $batch->id]))->assertOk()->assertSee('extra@example.test');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        foreach ([1, 2] as $attempt) $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1, 'bcc_emails' => 'unreviewed@example.test'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bulk_email_logs', 4);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'unreviewed@example.test']);

        // Exercise the real job with an in-memory transport; no external mail is sent.
        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->andReturn('array');
        $mailer = Mail::mailer('array');
        $mailer->getSymfonyTransport()->flush();
        foreach (\App\Models\BulkEmailLog::all() as $log) (new \App\Jobs\SendBulkEmailJob($log->id))->handle();
        $sent = $mailer->getSymfonyTransport()->messages();
        $this->assertCount(4, $sent);
        $bccAddresses = [];
        foreach ($sent as $message) {
            $email = $message->getOriginalMessage();
            $this->assertSame('Tournament office', $email->getFrom()[0]->getName());
            $this->assertSame('office@example.test', $email->getReplyTo()[0]->getAddress());
            $this->assertSame([], $email->getCc());
            if ($email->getBcc()) {
                $this->assertSame([], $email->getTo());
                $this->assertCount(1, $email->getBcc());
                $bccAddresses[] = $email->getBcc()[0]->getAddress();
                $this->assertStringNotContainsString('Bcc:', $message->toString());
            } else {
                $this->assertCount(1, $email->getTo());
            }
        }
        $this->assertEqualsCanonicalizing(['extra@example.test', 'other@example.test'], $bccAddresses);
        $this->assertDatabaseCount('bulk_email_logs', 4);
    }

    public function test_invalid_or_excessive_bcc_is_rejected_before_review(): void
    {
        [$event, , $regions] = $this->scenario();
        $input = app(SelectionRegionMessage::class)->draft($event) + ['selection_region_ids' => [$regions[0]->id]];
        foreach (['valid@example.test, invalid', implode(',', array_map(fn ($i) => "copy{$i}@example.test", range(1, 51)))] as $bcc) {
            $this->post(route('backend.selection-region-mail.preview', $event), $input + ['bcc_emails' => $bcc])->assertRedirect()->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_changed_selections_or_contacts_require_a_fresh_review_and_foreign_regions_are_rejected(): void
    {
        [$event, , $regions, $saved] = $this->scenario();
        $input = app(SelectionRegionMessage::class)->draft($event) + ['selection_region_ids' => [$regions[0]->id]];
        $this->post(route('backend.selection-region-mail.preview', $event), $input)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $snapshot = $saved->snapshot;
        $snapshot[0]['name'] = 'Changed Main Child';
        $saved->update(['version' => 2, 'snapshot' => $snapshot]);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $input = app(SelectionRegionMessage::class)->draft($event) + ['selection_region_ids' => [999999]];
        $this->post(route('backend.selection-region-mail.preview', $event), $input)->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('backend.selection-region-mail.compose', $event))->assertForbidden();
    }

    public function test_linked_parent_contacts_are_included_and_contact_changes_invalidate_approval(): void
    {
        [$event, , $regions] = $this->scenario();
        $player = Player::where('email', 'one@example.test')->firstOrFail();
        $parent = User::factory()->create(['email' => 'parent@example.test']);
        $player->users()->attach($parent);
        $input = app(SelectionRegionMessage::class)->draft($event) + ['selection_region_ids' => [$regions[0]->id], 'from_name' => 'Tournament office', 'reply_to' => 'office@example.test'];
        $this->post(route('backend.selection-region-mail.preview', $event), $input)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $this->assertEqualsCanonicalizing(['one@example.test', 'parent@example.test'], array_column($batch->recipients, 'email'));
        $this->get(route('backend.selection-region-mail.compose', ['event' => $event, 'edit_batch' => $batch->id]))->assertOk()->assertSee('Tournament office')->assertSee('office@example.test');
        $parent->update(['email' => 'changed@example.test']);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_missing_contacts_require_acknowledgment_and_shared_preview_does_not_grant_mail_access(): void
    {
        [$event, $actor, $regions] = $this->scenario();
        Player::where('email', 'one@example.test')->firstOrFail()->update(['email' => null]);
        $input = app(SelectionRegionMessage::class)->draft($event) + ['selection_region_ids' => [$regions[0]->id]];
        $this->post(route('backend.selection-region-mail.preview', $event), $input)->assertOk()->assertSee('Missing contacts');
        $batch = EventCommunicationBatch::sole();
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('acknowledge_missing');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $viewer = User::factory()->create();
        \App\Models\SelectionPreviewGrant::create(['event_id' => $event->id, 'user_id' => $viewer->id, 'granted_by' => $actor->id]);
        $this->actingAs($viewer)->get(route('backend.selection-region-mail.compose', $event))->assertForbidden();
        $this->post(route('backend.selection-region-mail.preview', $event), $input)->assertForbidden();
    }

    public function test_finalized_snapshot_includes_every_group_and_ignores_later_live_draft_edits(): void
    {
        [$event, $actor, $regions, $saved] = $this->scenario();
        $group = $saved->getAttributes();
        $group['snapshot'] = $saved->snapshot;
        $group['selected_keys'] = $saved->selected_keys;
        $group['region_ids'] = $saved->region_ids;
        $group['formats'] = [];
        $group['reasons'] = $saved->reasons;
        $group['draft_version'] = 1;
        $girls = $group;
        $girls['snapshot'] = [['id' => 'c', 'name' => 'Girl Main', 'region' => 'Two', 'draft_position' => 1]];
        $girls['selected_keys'] = ['c'];
        $final = \App\Models\EventSelectionFinalization::create(['event_id' => $event->id, 'finalized_by' => $actor->id,
            'draft_versions' => ['13-boys' => 1, '13-girls' => 1], 'groups' => ['13-boys' => $group, '13-girls' => $girls],
            'fingerprint' => str_repeat('a', 64), 'team_size' => 1, 'reserve_count' => 1, 'finalized_at' => now()]);
        $saved->update(['snapshot' => [], 'selected_keys' => [], 'version' => 2]);
        $draft = app(SelectionRegionMessage::class)->draft($event);
        $this->assertStringContainsString('Finalized selected teams', $draft['body']);
        $this->assertStringContainsString('Main Child', $draft['body']);
        $this->assertStringContainsString('Reserve 1: Reserve Child', $draft['body']);
        $this->assertStringContainsString('u/13 girls', $draft['body']);
        $this->assertStringContainsString('Girl Main', $draft['body']);
        $this->assertStringNotContainsString('Private note', $draft['body']);
        $this->post(route('backend.selection-region-mail.preview', $event), $draft + ['selection_region_ids' => [$regions[0]->id]])->assertOk();
        $batch = EventCommunicationBatch::sole();
        app(\App\Services\EventSelectionFinalizationService::class)->reopen($event, $actor, $final->id, 'Review changes');
        $this->assertSame($draft['selection_fingerprint'], app(SelectionRegionMessage::class)->draft($event->fresh())['selection_fingerprint']);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bulk_email_logs', 1);
    }
}
