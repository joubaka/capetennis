<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{AlternateReplacementService, DrawSetupGuide, FlexibleMonradService};
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Gate};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeadOfficeSetupCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $flexible = true): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-10-30']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'U/19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'Completed singles draw', 'published' => false]);
        $draw->settings()->create(['workflow' => $flexible ? 'feed_in_monrad' : 'round_robin']);
        $registrations = Registration::factory()->count($flexible ? 4 : 2)->create();
        foreach ($registrations as $registration) {
            $registration->players()->attach(Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2009-01-01'])->id);
            $registration->categoryEvents()->attach($category->id, ['status' => 'active', 'payment_status_id' => 1]);
        }
        if ($flexible) {
            $slots = [];
            foreach (['aa', 'ab', 'ba', 'bb'] as $i => $path) $slots[$path] = ['type' => 'player', 'id' => $registrations[$i]->id];
            app(FlexibleMonradService::class)->save($draw, ['size' => 4, 'slots' => $slots], 0);
            app(FlexibleMonradService::class)->generate($draw, 1);
        } else {
            $group = $draw->groups()->create(['name' => 'A']);
            $group->registrations()->attach($registrations->pluck('id'));
            $draw->registrations()->attach($registrations->pluck('id'));
            Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
                'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
        }
        $venue = Venue::forceCreate(['name' => 'Completion test courts']);
        foreach ($draw->drawFixtures()->get() as $i => $fixture) {
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => (string) ($i + 1), 'time' => '2026-10-30 09:00:00', 'duration_minutes' => 60]);
        }
        $draw->update(['published' => true]);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        return [$event, $draw->fresh(), $registrations];
    }

    private function overview(Event $event, Draw $draw, bool $complete): array
    {
        $response = $this->get(route('headOffice.show', $event))->assertOk();
        $loaded = $response->viewData('event')->draws->firstWhere('id', $draw->id);
        $this->assertSame($complete, $loaded->setup_complete);
        $guide = app(DrawSetupGuide::class)->state($draw->fresh());
        $this->assertSame($complete, $guide['complete']);
        if ($complete) $this->assertSame(5, $guide['next'], 'Completed guide retains its review-step fallback.');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $row = '//article[@data-draw-id="'.$draw->id.'"]';
        $this->assertSame($complete ? 0 : 1, $xpath->query($row.'/div[contains(@class,"draw-overview-actions")]/a[@data-draw-guide-open and normalize-space(.)="Continue setup"]')->length);
        $this->assertSame($complete ? 1 : 0, $xpath->query($row.'//ul[contains(@class,"dropdown-menu")]//a[@data-draw-guide-open and normalize-space(.)="Review setup"]')->length);
        $this->assertSame(1, $xpath->query($row.'//a[@data-draw-guide-open and @href="'.route('draw.guide.show', [$event, $draw]).'"]')->length);
        return [$loaded, $response];
    }

    public function test_complete_draw_reviews_in_menu_but_missing_unpublished_or_changed_schedule_continues_setup(): void
    {
        [$event, $draw] = $this->scenario();
        $before = [];
        foreach (['draws', 'flexible_monrad_draws', 'fixtures', 'order_of_plays', 'published_schedule_assignments'] as $table) $before[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        $this->overview($event, $draw, true);
        $this->overview($event, $draw, true);
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->map(fn ($row) => (array) $row)->all(), $table.' was mutated by overview GET');
        $slot = OrderOfPlay::firstOrFail();
        $saved = $slot->getAttributes();
        $slot->delete();
        $this->overview($event, $draw, false);
        OrderOfPlay::forceCreate($saved);
        $draw->update(['published' => false]);
        $this->overview($event, $draw, false);
        $draw->update(['published' => true, 'oop_published' => false]);
        $this->overview($event, $draw, false);
        $draw->update(['oop_published' => true]);
        OrderOfPlay::whereKey($saved['id'])->update(['time' => '2026-10-30 10:00:00']);
        $this->overview($event, $draw, false);
    }

    public function test_private_alternate_is_complete_until_its_actual_unpaid_source_withdraws(): void
    {
        [$event, $draw, $registrations] = $this->scenario();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event->update(['eventType' => $type]);
        $player = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2009-01-01']);
        $source = Registration::factory()->create(); $source->players()->attach($player->id);
        $entry = CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id,
            'registration_id' => $source->id, 'status' => 'active', 'payment_status_id' => 2]);
        $incoming = app(AlternateReplacementService::class)->replace($draw->fresh(), $registrations[0]->id, $player->id, true, auth()->id());
        $this->assertSame('private', $incoming->competition_replacement['entry_mode']);
        [$loaded] = $this->overview($event, $draw, true);
        $this->assertSame(0, $loaded->pending_late_withdrawal_count);
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $recordBefore = $draw->fresh()->flexibleMonrad->getAttributes();
        $fixturesBefore = $draw->drawFixtures()->get()->map->getAttributes()->all();
        [$loaded] = $this->overview($event, $draw, false);
        $this->assertSame(1, $loaded->pending_late_withdrawal_count);
        $this->assertSame($recordBefore, $draw->fresh()->flexibleMonrad->getAttributes());
        $this->assertSame($fixturesBefore, $draw->drawFixtures()->get()->map->getAttributes()->all());
        $this->assertTrue($draw->registrations()->where('registrations.id', $incoming->id)->exists(), 'GET must not reconcile the withdrawal.');
        $this->assertSame(2, (int) $entry->fresh()->payment_status_id);
    }

    public function test_saved_round_robin_group_changes_make_setup_unfinished(): void
    {
        [$event, $draw] = $this->scenario(false);
        $this->overview($event, $draw, true);
        $draw->groups()->firstOrFail()->registrations()->attach(Registration::factory()->create()->id);
        $this->overview($event, $draw, false);
    }

    public function test_private_trial_alternate_without_any_entry_is_not_a_pending_withdrawal(): void
    {
        [$event, $draw, $registrations] = $this->scenario();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event->update(['eventType' => $type]);
        $player = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2009-01-01']);
        $incoming = app(AlternateReplacementService::class)->replace($draw->fresh(), $registrations[0]->id, $player->id, true, auth()->id());
        $this->assertNull($incoming->competition_replacement['source_registration_id']);
        [$loaded] = $this->overview($event, $draw, true);
        $this->assertSame(0, $loaded->pending_late_withdrawal_count);
    }

    public function test_completion_projection_reads_minimal_event_fixtures_instead_of_full_workspace_state(): void
    {
        [$event] = $this->scenario();
        $response = $this->get(route('headOffice.show', $event))->assertOk();
        $draws = $response->viewData('event')->draws;
        $scheduleStates = $draws->mapWithKeys(fn ($draw) => [$draw->id => $draw->schedule_publication_state]);
        DB::enableQueryLog(); DB::flushQueryLog();
        $summaries = app(DrawSetupGuide::class)->overview($draws, $scheduleStates);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertTrue($summaries->first()['complete']);
        $this->assertLessThan(20, $queries->count());
        $this->assertFalse($queries->contains(fn ($sql) => str_contains($sql, 'draw_audit_logs')), 'Full workspace state and start-history rendering are unnecessary for setup completion.');
        $this->assertFalse($queries->contains(fn ($sql) => preg_match('/^select \* from ["`]fixture_results["`]/i', $sql) === 1), 'Only result counts are needed, not scoring rows.');
    }

    public function test_setup_links_remain_authorized_and_other_events_remain_inaccessible(): void
    {
        [$event, $draw] = $this->scenario();
        Gate::define('event.manage', fn () => false);
        $response = $this->get(route('headOffice.show', $event))->assertOk();
        $document = new \DOMDocument; @$document->loadHTML($response->getContent());
        $this->assertSame(0, (new \DOMXPath($document))->query('//article[@data-draw-id="'.$draw->id.'"]//a[@data-draw-guide-open]')->length);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->get(route('headOffice.show', $event))->assertForbidden();
    }

    public function test_overview_marks_malformed_saved_graph_unfinished_without_loading_workspace_or_mutating_it(): void
    {
        [$event, $draw] = $this->scenario();
        $record = $draw->flexibleMonrad;
        $graph = $record->graph; $graph['players'] = 'invalid legacy snapshot';
        $record->update(['graph' => $graph]);
        $response = $this->get(route('headOffice.show', $event))->assertOk();
        $this->assertFalse($response->viewData('event')->draws->firstWhere('id', $draw->id)->setup_complete);
        $this->assertSame($graph, $record->fresh()->graph);
        $record->update(['graph' => 'invalid top-level legacy snapshot']);
        $response = $this->get(route('headOffice.show', $event))->assertOk();
        $this->assertFalse($response->viewData('event')->draws->firstWhere('id', $draw->id)->setup_complete);
        $this->assertSame('invalid top-level legacy snapshot', $record->fresh()->graph);
    }
}
