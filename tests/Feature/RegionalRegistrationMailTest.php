<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, ClothingItemType, ClothingSize, Event, EventCommunicationBatch, EventRegion, EventType, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\RegionalRegistrationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegionalRegistrationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_regional_roster_supports_editable_congratulations_and_exact_review_before_idempotent_queue(): void
    {
        [$event, $actor, $region, $team] = $this->scenario();
        $parent = User::factory()->create(['email' => 'parent@example.test']);
        $team->team_players->first()->player->users()->attach($parent);
        $this->get(route('backend.regional-registration-mail.compose', ['event' => $event, 'region_id' => $region->region_id]))
            ->assertOk()->assertSee('Congratulations on being selected for Platteland')
            ->assertSee('for the email only')->assertSee('Clothing catalogue checked and ready');
        $input = $this->input($event, $actor, $region);
        $input['body'] .= "\nCustom organiser instructions";
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk()
            ->assertSee('parent@example.test')->assertSee('Custom organiser instructions');
        $batch = EventCommunicationBatch::sole();
        $this->assertEqualsCanonicalizing(['shared@example.test', 'parent@example.test', 'imported@example.test'], array_column($batch->recipients, 'email'));
        $this->assertStringContainsString('31 October 2026', $batch->body);
        $this->assertStringNotContainsString('Clothing catalogue', $batch->body);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $before = $event->fresh()->getAttributes();
        $this->get(route('backend.regional-registration-mail.compose', ['event' => $event, 'edit_batch' => $batch->id]))
            ->assertOk()->assertSee('Custom organiser instructions');
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token])->assertSessionHasErrors('confirm_send');
        foreach ([1, 2] as $attempt) $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bulk_email_logs', 3);
        $this->assertSame($before, $event->fresh()->getAttributes());
        Mail::assertNothingSent();
    }

    public function test_clothing_requires_checked_option_open_enabled_region_and_uses_authoritative_customer_prices(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $item = $this->clothing($region);
        $input = $this->input($event, $actor, $region, true);
        $this->assertStringContainsString('Shirt — R275.00; sizes: Small', $input['body']);
        $this->assertStringContainsString('02 November 2026', $input['body']);
        $this->assertStringNotContainsString('\\n', $input['body']);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $item->update(['final_amount' => 290]);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $region->region->update(['clothing_order' => 0]);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertSessionHasErrors('include_clothing');
        $without = $this->input($event, $actor, $region, false);
        $this->assertStringNotContainsString('Shirt', $without['body']);
    }

    public function test_closed_or_unpublished_event_and_changed_contacts_require_fresh_review(): void
    {
        [$event, $actor, $region, $team] = $this->scenario();
        $input = $this->input($event, $actor, $region);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $team->team_players->first()->player->update(['email' => 'changed@example.test']);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $event->update(['published' => false]);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertSessionHasErrors('preview');
        $event->update(['published' => true, 'signUp' => false]);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_foreign_region_unauthorized_user_and_changed_dates_cannot_skip_fresh_review(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $input = $this->input($event, $actor, $region);
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['registration_deadline' => '2026-11-01']))->assertSessionHasErrors('preview');
        $foreign = EventRegion::forceCreate(['event_id' => Event::factory()->create()->id, 'region_id' => TeamRegion::create(['region_name' => 'Foreign'])->id]);
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['event_region_id' => $foreign->id]))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('backend.regional-registration-mail.compose', $event))->assertForbidden();
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_suggested_message_regeneration_adds_clothing_without_creating_a_batch_or_changing_deadlines(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $this->clothing($region);
        $before = $event->fresh()->getAttributes();
        $input = $this->input($event, $actor, $region, true);
        $input['intent'] = 'generate';
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk()->assertSee('R275.00')->assertSee('31 October 2026');
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertSame($before, $event->fresh()->getAttributes());
        Mail::assertNothingSent();
    }

    public function test_profile_imported_and_linked_reserves_and_their_parents_are_excluded_and_rank_changes_invalidate_review(): void
    {
        [$event, $actor, $region, $team] = $this->scenario();
        $reserve = Player::factory()->create(['email' => 'reserve@example.test', 'userId' => null]);
        $reserve->users()->attach(User::factory()->create(['email' => 'reserve-parent@example.test']));
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $reserve->id, 'rank' => 4]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => 4, 'pay_status' => 0, 'player_profile' => $reserve->id,
            'name' => 'Linked', 'surname' => 'Reserve', 'email' => 'old-import@example.test']);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => 5, 'pay_status' => 0, 'name' => 'Imported', 'surname' => 'Reserve', 'email' => 'import-reserve@example.test']);
        $input = $this->input($event, $actor, $region);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk()
            ->assertDontSee('reserve@example.test')->assertDontSee('reserve-parent@example.test')->assertDontSee('import-reserve@example.test');
        $batch = EventCommunicationBatch::sole();
        $this->assertCount(2, $batch->recipients);
        $team->update(['num_team_members' => 2]);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    private function input(Event $event, User $actor, EventRegion $region, bool $clothing = false): array
    {
        $options = ['event_region_id' => $region->id, 'include_clothing' => $clothing,
            'registration_deadline' => '2026-10-31', 'clothing_deadline' => '2026-11-02'];
        return app(RegionalRegistrationMessage::class)->draft($event->fresh(), $actor, $options) + $options + ['intent' => 'preview'];
    }

    private function clothing(EventRegion $region): ClothingItemType
    {
        $region->region->update(['clothing_admin' => 1, 'clothing_order' => 1]);
        $item = ClothingItemType::create(['region_id' => $region->region_id, 'item_type_name' => 'Shirt', 'price' => 260, 'final_amount' => 275, 'cost_price' => 100]);
        ClothingSize::create(['item_type' => $item->id, 'size' => 'Small', 'ordering' => 1]);
        return $item;
    }

    private function scenario(): array
    {
        Bus::fake(); Mail::fake();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Regional team event', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type, 'signUp' => true, 'published' => true, 'deadline' => 0]);
        $actor->adminEvents()->attach($event);
        $this->actingAs($actor);
        $region = EventRegion::forceCreate(['event_id' => $event->id, 'region_id' => TeamRegion::create(['region_name' => 'Platteland', 'clothing_admin' => 0, 'clothing_order' => 0])->id]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id,
            'region_id' => $region->region_id, 'published' => true, 'noProfile' => false, 'num_team_members' => 3]);
        foreach ([1, 2] as $rank) TeamPlayer::create(['team_id' => $team->id,
            'player_id' => Player::factory()->create(['email' => 'shared@example.test', 'userId' => null])->id, 'rank' => $rank, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Player', 'email' => 'imported@example.test', 'rank' => 3, 'pay_status' => 0]);
        $hidden = Team::factory()->create(['category_event_id' => $team->category_event_id, 'region_id' => $region->region_id, 'published' => false]);
        TeamPlayer::create(['team_id' => $hidden->id, 'player_id' => Player::factory()->create(['email' => 'hidden@example.test', 'userId' => null])->id, 'rank' => 1]);
        return [$event, $actor, $region, $team];
    }
}
