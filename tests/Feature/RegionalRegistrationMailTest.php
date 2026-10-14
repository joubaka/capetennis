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

    public function test_foreign_region_and_unauthorized_user_are_rejected_while_changed_dates_get_a_fresh_review(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $input = $this->input($event, $actor, $region);
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['registration_deadline' => '2026-11-01']))
            ->assertOk()->assertSee('Registration closing date and deadline: 01 November 2026');
        $foreign = EventRegion::forceCreate(['event_id' => Event::factory()->create()->id, 'region_id' => TeamRegion::create(['region_name' => 'Foreign'])->id]);
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['event_region_id' => $foreign->id]))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('backend.regional-registration-mail.compose', $event))->assertForbidden();
        $this->assertDatabaseCount('event_communication_batches', 1);
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

    public function test_email_deadline_times_are_generated_retained_when_editing_and_changed_times_get_a_fresh_review(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $this->clothing($region);
        $options = ['event_region_id' => $region->id, 'include_clothing' => true,
            'registration_deadline' => '2026-10-18', 'registration_deadline_time' => '17:00',
            'clothing_deadline' => '2026-10-16', 'clothing_deadline_time' => '18:30'];
        $input = app(RegionalRegistrationMessage::class)->draft($event->fresh(), $actor, $options) + $options + ['intent' => 'preview'];
        $this->assertStringContainsString('18 October 2026 at 17:00 (South African time)', $input['body']);
        $this->assertStringContainsString('16 October 2026 at 18:30 (South African time)', $input['body']);
        $before = $event->fresh()->getAttributes();
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['intent' => 'generate']))
            ->assertOk()->assertSee('value="17:00"', false)->assertSee('value="18:30"', false);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk()
            ->assertSee('18 October 2026 at 17:00 (South African time)');
        $batch = EventCommunicationBatch::sole();
        $this->assertSame('17:00', $batch->options['registration_deadline_time']);
        $this->assertSame('18:30', $batch->options['clothing_deadline_time']);
        $this->get(route('backend.regional-registration-mail.compose', ['event' => $event, 'edit_batch' => $batch->id]))
            ->assertOk()->assertSee('value="2026-10-18"', false)->assertSee('value="2026-10-16"', false)
            ->assertSee('value="17:00"', false)->assertSee('value="18:30"', false);
        foreach (['registration_deadline_time', 'clothing_deadline_time'] as $field) {
            $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, [$field => '20:00']))
                ->assertOk()->assertSee('20:00 (South African time)');
        }
        $this->assertSame($before, $event->fresh()->getAttributes());
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_times_and_time_without_date_are_rejected_and_registration_default_uses_local_closing_time(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $input = $this->input($event, $actor, $region);
        foreach (['registration_deadline_time', 'clothing_deadline_time'] as $field) {
            foreach (['24:00', '17:99', '5pm', '17:00:01'] as $time) {
                $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, [$field => $time]))
                    ->assertSessionHasErrors($field);
            }
            $dateField = str_replace('_time', '', $field);
            $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, [$field => '17:00', $dateField => null]))
                ->assertSessionHasErrors($dateField);
        }
        $defaultTime = $event->registrationClosesAt()?->timezone('Africa/Johannesburg')->format('H:i');
        $this->get(route('backend.regional-registration-mail.compose', $event))->assertOk()
            ->assertSee('name="registration_deadline_time" value="'.$defaultTime.'"', false);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_changed_deadlines_are_synchronized_server_side_without_clothing_catalogue_or_losing_manual_edits(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $input = $this->input($event, $actor, $region);
        $input['body'] .= "\n\nBring your tennis shoes and a water bottle.";
        $input['registration_deadline'] = '2026-10-18';
        $input['registration_deadline_time'] = '21:00';
        $input['clothing_deadline'] = '2026-10-16';
        $input['clothing_deadline_time'] = '18:00';
        $before = $event->fresh()->getAttributes();
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk()
            ->assertSee('Registration closing date and deadline: 18 October 2026 at 21:00 (South African time)')
            ->assertSee('Clothing orders closing date and deadline: 16 October 2026 at 18:00 (South African time)')
            ->assertSee('Bring your tennis shoes and a water bottle.');
        $batch = EventCommunicationBatch::sole();
        $this->assertStringNotContainsString('Clothing orders are open', $batch->body);
        $this->assertStringNotContainsString('Clothing catalogue', $batch->body);
        $this->assertStringNotContainsString('31 October 2026', $batch->body);
        $this->assertStringNotContainsString('02 November 2026', $batch->body);
        $this->assertSame(1, substr_count($batch->body, 'Registration closing date and deadline:'));
        $this->assertSame(1, substr_count($batch->body, 'Clothing orders closing date and deadline:'));
        $this->assertSame($before, $event->fresh()->getAttributes());
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_legacy_deadline_lines_are_replaced_once_and_blank_dates_remove_only_managed_lines(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $body = "Dear parents,\n\nMy custom introduction.\nRegistration deadline: old value\nRegistration deadline: duplicate\nClothing order deadline: old clothing value\n\nPlease keep this paragraph.\n\nKind regards,\nOffice";
        $message = app(RegionalRegistrationMessage::class);
        $updated = $message->synchronizeDeadlines($body, ['registration_deadline' => '2026-10-18', 'registration_deadline_time' => '17:00',
            'clothing_deadline' => '2026-10-16', 'clothing_deadline_time' => '18:00']);
        $this->assertSame(1, substr_count($updated, 'Registration closing date and deadline:'));
        $this->assertSame(1, substr_count($updated, 'Clothing orders closing date and deadline:'));
        $this->assertStringNotContainsString('old value', $updated);
        $this->assertStringNotContainsString('duplicate', $updated);
        $cleared = $message->synchronizeDeadlines($updated, ['registration_deadline' => null, 'clothing_deadline' => null]);
        $this->assertStringNotContainsString('closing date and deadline:', $cleared);
        $this->assertStringContainsString('My custom introduction.', $cleared);
        $this->assertStringContainsString('Please keep this paragraph.', $cleared);
        $draft = $message->draft($event, $actor, ['event_region_id' => $region->id, 'include_clothing' => false,
            'registration_deadline' => null, 'clothing_deadline' => null]);
        $this->assertStringNotContainsString('closing date and deadline:', $draft['body']);
    }

    public function test_super_user_composer_and_omitted_sender_fields_use_assigned_event_administrator(): void
    {
        [$event, $admin, $region] = $this->scenario();
        $admin->update(['name' => 'Regional organiser', 'email' => 'regional-organiser@example.test']);
        $event->update(['email' => $admin->email]);
        Role::findOrCreate('super-user', 'web');
        $operator = User::factory()->create(['name' => 'Super operator', 'email' => 'super-operator@example.test'])->assignRole('super-user');
        $this->actingAs($operator);
        $this->get(route('backend.regional-registration-mail.compose', $event))->assertOk()
            ->assertSee('value="Regional organiser"', false)->assertSee('value="regional-organiser@example.test"', false)
            ->assertDontSee('value="super-operator@example.test"', false);
        $input = $this->input($event, $operator, $region);
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['intent' => 'generate']))
            ->assertOk()->assertSee('value="Regional organiser"', false)->assertSee('value="regional-organiser@example.test"', false);
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $this->assertSame($admin->name, $batch->options['from_name']);
        $this->assertSame($admin->email, $batch->options['reply_to']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_custom_reviewed_sender_pair_survives_editing_and_regeneration(): void
    {
        [$event, $actor, $region] = $this->scenario();
        $input = $this->input($event, $actor, $region) + ['from_name' => 'Tournament office', 'reply_to' => 'tournament-office@example.test'];
        $this->post(route('backend.regional-registration-mail.preview', $event), $input)->assertOk();
        $batch = EventCommunicationBatch::sole();
        $this->get(route('backend.regional-registration-mail.compose', ['event' => $event, 'edit_batch' => $batch->id]))
            ->assertOk()->assertSee('value="Tournament office"', false)->assertSee('value="tournament-office@example.test"', false);
        $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, ['intent' => 'generate']))
            ->assertOk()->assertSee('value="Tournament office"', false)->assertSee('value="tournament-office@example.test"', false);
        foreach (['from_name' => '', 'reply_to' => ''] as $field => $value) {
            $this->post(route('backend.regional-registration-mail.preview', $event), array_replace($input, [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('event_communication_batches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_event_sender_selection_is_deterministic_and_preserves_name_and_email_pair(): void
    {
        [$event, $firstAdmin] = $this->scenario();
        Role::findOrCreate('super-user', 'web');
        $firstAdmin->assignRole('super-user');
        $other = User::factory()->create(['name' => 'Second event admin', 'email' => 'second-admin@example.test']);
        $other->adminEvents()->attach($event);
        $event->update(['email' => $other->email]);
        $this->assertSame(['from_name' => $other->name, 'reply_to' => $other->email], \App\Services\CommunicationSender::eventDefaults($event, $firstAdmin));
        $event->update(['email' => 'event-contact@example.test']);
        $this->assertSame(['from_name' => $other->name, 'reply_to' => $other->email], \App\Services\CommunicationSender::eventDefaults($event, $firstAdmin));
        $event->admins()->detach();
        $event->update(['organizer' => 'Event contact person']);
        $this->assertSame(['from_name' => 'Event contact person', 'reply_to' => 'event-contact@example.test'], \App\Services\CommunicationSender::eventDefaults($event, $firstAdmin));
        $event->update(['email' => null, 'organizer' => null]);
        $this->assertSame(['from_name' => $firstAdmin->name, 'reply_to' => $firstAdmin->email], \App\Services\CommunicationSender::eventDefaults($event, $firstAdmin));
        $this->assertSame(['from_name' => $firstAdmin->name, 'reply_to' => $firstAdmin->email], \App\Services\CommunicationSender::resolve([], $firstAdmin));
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
