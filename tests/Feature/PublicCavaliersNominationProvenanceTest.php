<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Event, EventNomination, EventSelectionFinalization, EventSelectionRosterImport, EventType, NoProfileTeamPlayer, Player, Team, TeamRegion, User};
use App\Services\{CavaliersTrialCandidates, PlattelandFinalizedTrialCandidates, PublicCavaliersNominationProvenance};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicCavaliersNominationProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_nominees_follow_unregistered_names_without_changing_saved_order_or_entry_state(): void
    {
        [$event, $division] = $this->events();
        $otherDivision = $this->division($event, 'U12 Girls');
        $players = [];
        foreach (['Zoe', 'Amy', 'Beth', 'Cara', 'Dora', 'Eva', 'Faye'] as $name) {
            [$players[$name]] = $this->nominee($event, $division, $name);
        }
        $entry = function ($player, $entryDivision, array $state = []) {
            $registration = \App\Models\Registration::create([]);
            $registration->players()->attach($player);
            return \App\Models\CategoryEventRegistration::factory()->create([
                'registration_id' => $registration->id, 'category_event_id' => $entryDivision->id,
                'payment_status_id' => 1, 'status' => 'active', ...$state,
            ]);
        };
        $entry($players['Zoe'], $division);
        $entry($players['Amy'], $division);
        $entry($players['Beth'], $division, ['payment_status_id' => null]);
        $entry($players['Cara'], $division, ['withdrawn_at' => now()]);
        $entry($players['Dora'], $division, ['status' => 'withdrawn']);
        $entry($players['Eva'], $division, ['status' => 'cancelled']);
        $entry($players['Faye'], $otherDivision);
        $nominationsBefore = EventNomination::orderBy('id')->get()->map->getAttributes()->all();
        $entriesBefore = \App\Models\CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all();
        $page = $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Unregistered players first, then registered players. Each group is alphabetical by first name.');
        // Restrict the assertion to nomination rows, excluding the separate registered-player section.
        $document = new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//ul[contains(@class,"list-group-flush")]/li');
        $names = [];
        foreach ($rows as $row) $names[] = preg_replace('/ Register$/', '', trim(preg_replace('/\s+/', ' ', $row->textContent)));
        $this->assertSame(['Beth Example', 'Cara Example', 'Dora Example', 'Eva Example', 'Faye Example',
            'Amy Example Registered', 'Zoe Example Registered'], $names);
        $highlighted = $xpath->query('//ul[contains(@class,"list-group-flush")]/li[contains(@class,"cavaliers-registered-nominee")]');
        $this->assertCount(2, $highlighted);
        $this->assertStringContainsString('Amy Example', $highlighted->item(0)->textContent);
        $this->assertStringContainsString('Zoe Example', $highlighted->item(1)->textContent);
        $this->assertSame($nominationsBefore, EventNomination::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($entriesBefore, \App\Models\CategoryEventRegistration::orderBy('id')->get()->map->getAttributes()->all());
        if ($output = getenv('CT_PUBLIC_NOMINATION_QA_OUTPUT')) {
            if (! is_dir($output)) mkdir($output, 0777, true);
            file_put_contents($output.'/registered-last.html', $page->getContent());
        }
    }

    public function test_nomination_heading_counts_are_visible_only_to_this_events_admins_even_after_registration_closes(): void
    {
        [$event, $division] = $this->events();
        $this->nominee($event, $division, 'Visible');
        $hiddenDivision = $this->division($event, 'Hidden Girls');
        $this->nominee($event, $hiddenDivision, 'Unpublished');
        $hiddenDivision->update(['nominations_published' => false]);
        $url = route('events.show', $event);
        $headings = function ($page): array {
            preg_match_all('/<h3>(.*?)<\/h3>/s', $page->getContent(), $matches);
            return array_map(fn ($heading) => trim(preg_replace('/\s+/', ' ', strip_tags($heading))), $matches[1]);
        };
        $assertHeadings = function (bool $canCount) use ($url, $headings): void {
            $page = $this->get($url)->assertOk()->assertSee('Visible Example')->assertDontSee('Unpublished Example');
            $this->assertSame(['U11 Girls'.($canCount ? ' (1)' : ''), 'Hidden Girls'], $headings($page));
        };
        $assertHeadings(false);
        $normal = User::factory()->create();
        $this->actingAs($normal);
        $assertHeadings(false);
        $unrelatedAdmin = User::factory()->create();
        $unrelatedAdmin->assignRole(Role::findOrCreate('admin', 'web'));
        \App\Models\EventAdmin::create(['event_id' => Event::factory()->create()->id, 'user_id' => $unrelatedAdmin->id]);
        $this->actingAs($unrelatedAdmin);
        $assertHeadings(false);
        $convenor = User::factory()->create();
        \App\Models\EventConvenor::create(['event_id' => $event->id, 'user_id' => $convenor->id, 'role' => 'hoof']);
        $this->actingAs($convenor);
        $this->assertTrue($convenor->can('event.manage', $event));
        $assertHeadings(false);
        $manager = User::factory()->create();
        $manager->assignRole(Role::findOrCreate('admin', 'web'));
        \App\Models\EventAdmin::create(['event_id' => $event->id, 'user_id' => $manager->id]);
        $this->actingAs($manager);
        $assertHeadings(true);
        $event->update(['signUp' => false]);
        $assertHeadings(true);
        $super = User::factory()->create();
        $super->assignRole(Role::findOrCreate('super-user', 'web'));
        $this->actingAs($super);
        $assertHeadings(true);
    }

    private function events(): array
    {
        // Public rendering must never impersonate a source manager to get private provenance.
        foreach ([CavaliersTrialCandidates::class, PlattelandFinalizedTrialCandidates::class] as $private) {
            $this->app->bind($private, fn () => throw new \LogicException('Private candidate preview invoked publicly.'));
        }
        $type = EventType::forceCreate(['id' => 242, 'name' => 'Team source', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['id' => 252, 'eventType' => 5, 'published' => true, 'name' => 'Fictional Cavaliers Trials']);
        $division = $this->division($event, 'U11 Girls');
        $inter = Event::factory()->create(['id' => 242, 'eventType' => $type->id, 'published' => true, 'name' => 'Inter-Districts Tournament Primary Schools 2026']);
        $platteland = Event::factory()->create(['id' => 241, 'eventType' => $type->id, 'published' => true, 'name' => 'Platteland Tennis Tournament Primary Schools 2026']);
        return [$event, $division, $inter, $platteland];
    }

    private function division(Event $event, string $name): CategoryEvent
    {
        return CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $name])->id, 'nominations_published' => true]);
    }

    private function nominee(Event $event, CategoryEvent $division, string $name): array
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => 'Example']);
        return [$player, EventNomination::forceCreate(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => $player->id, 'roster_position' => 27])];
    }

    private function team(Event $source, string $regionName, Player $player, bool $imported = false, int $rank = 1, string $age = 'U10 Girls', bool $published = true): Team
    {
        $region = TeamRegion::create(['region_name' => $regionName, 'short_name' => $regionName]);
        $source->regions()->attach($region);
        $team = Team::factory()->create(['category_event_id' => $this->division($source, $age)->id, 'region_id' => $region->id,
            'name' => $regionName.' '.$age, 'published' => $published, 'noProfile' => $imported, 'num_team_members' => 10]);
        if ($imported) $team->team_players_no_profile()->create(['name' => $player->name, 'surname' => $player->surname, 'player_profile' => $player->id, 'rank' => $rank, 'pay_status' => 0]);
        else $team->team_players()->create(['player_id' => $player->id, 'rank' => $rank]);
        return $team;
    }

    public function test_public_badges_show_only_published_region_and_source_and_keep_alphabetical_names(): void
    {
        [$event, $division, $inter, $platteland] = $this->events();
        [$zoe, $zoeNominee] = $this->nominee($event, $division, 'Zoe');
        [$amy, $amyNominee] = $this->nominee($event, $division, 'Amy');
        $this->team($inter, 'Drakenstein', $zoe);
        $this->team($platteland, 'Weskus', $amy, imported: true);
        $page = $this->get(route('events.show', $event))->assertOk()->assertSeeInOrder(['Amy Example', 'Zoe Example'])
            ->assertSee('(Weskus · Platteland)')->assertSee('(Drakenstein · Inter-Districts)')->assertDontSee('roster_position', false);
        $expected = [$division->id => [$zoeNominee->id => ['region' => 'Drakenstein', 'source' => 'Inter-Districts'], $amyNominee->id => ['region' => 'Weskus', 'source' => 'Platteland']]];
        $page->assertViewHas('nominationProvenance', $expected);
        $publicBadgeHtml = $page->getContent();
        $this->assertSame(27, (int) $zoeNominee->fresh()->roster_position);
        $inter->update(['published' => false]);
        $page = $this->get(route('events.show', $event))->assertOk()->assertDontSee('(Drakenstein · Inter-Districts)');
        $page->assertViewHas('nominationProvenance', [$division->id => [$amyNominee->id => ['region' => 'Weskus', 'source' => 'Platteland']]]);
        $platteland->update(['published' => false]);
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', []);
        if ($output = getenv('CT_PUBLIC_NOMINATION_QA_OUTPUT')) {
            if (! is_dir($output)) mkdir($output, 0777, true);
            file_put_contents($output.'/public.html', $publicBadgeHtml);
        }
    }

    public function test_published_platteland_nominees_show_region_for_profile_imported_and_frozen_reserves_only(): void
    {
        [$event, $division, $inter, $platteland] = $this->events();
        $actor = User::factory()->create();
        $expected = [];
        foreach ([false, true] as $imported) {
            [$player, $nominee] = $this->nominee($event, $division, $imported ? 'ImportedReserve' : 'ProfileReserve');
            $this->team($platteland, 'PublishedRegion', $player, imported: $imported, rank: 11);
            $expected[$nominee->id] = ['region' => 'PublishedRegion', 'source' => 'Platteland'];
        }
        [$frozenPlayer, $frozenNominee] = $this->nominee($event, $division, 'FrozenReserve');
        $frozenTeam = $this->team($platteland, 'FrozenRegion', $frozenPlayer, imported: true);
        $final = EventSelectionFinalization::create(['event_id' => $platteland->id, 'finalized_by' => $actor->id,
            'draft_versions' => [], 'groups' => [], 'team_size' => 10, 'reserve_count' => 5,
            'fingerprint' => str_repeat('f', 64), 'finalized_at' => now()]);
        EventSelectionRosterImport::create(['event_id' => $platteland->id, 'finalization_id' => $final->id,
            'region_id' => $frozenTeam->region_id, 'imported_by' => $actor->id, 'imported_at' => now(),
            'groups' => ['10-girls' => ['team_id' => $frozenTeam->id, 'players' => [[
                'slot_id' => $frozenTeam->team_players_no_profile()->first()->id,
                'profile_id' => $frozenPlayer->id, 'status' => 'reserve',
            ]]]]]);
        $expected[$frozenNominee->id] = ['region' => 'FrozenRegion', 'source' => 'Platteland'];
        // A source member without a published target nomination is never projected.
        $this->team($platteland, 'NON_NOMINEE_REGION', Player::factory()->create(), rank: 11);
        $hiddenDivision = $this->division($event, 'U11 Girls');
        [$hiddenPlayer] = $this->nominee($event, $hiddenDivision, 'HiddenNomination');
        $hiddenDivision->update(['nominations_published' => false]);
        $this->team($platteland, 'HIDDEN_NOMINATION_REGION', $hiddenPlayer, rank: 11);
        $before = EventNomination::orderBy('id')->get()->map->getAttributes()->all();
        $page = $this->get(route('events.show', $event))->assertOk()
            ->assertViewHas('nominationProvenance', [$division->id => $expected])
            ->assertSee('(PublishedRegion · Platteland)')->assertSee('(FrozenRegion · Platteland)')
            ->assertDontSee('NON_NOMINEE_REGION')->assertDontSee('HIDDEN_NOMINATION_REGION');
        $this->assertSame($before, EventNomination::orderBy('id')->get()->map->getAttributes()->all());
        $platteland->update(['published' => false]);
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', []);
    }

    public function test_platteland_reserve_regions_still_require_unambiguous_published_owned_team_and_exact_identity(): void
    {
        [$event, $division, $inter, $platteland] = $this->events();
        foreach (['Hidden', 'WrongAge', 'WrongGender', 'Ambiguous', 'OtherEvent', 'Unlinked'] as $name) {
            [$player] = $this->nominee($event, $division, $name);
            $team = $this->team($platteland, 'WITHHELD_'.$name, $player, imported: true, rank: 11,
                age: $name === 'WrongAge' ? 'U11 Girls' : ($name === 'WrongGender' ? 'U10 Boys' : 'U10 Girls'),
                published: $name !== 'Hidden');
            if ($name === 'Ambiguous') $this->team($platteland, 'WITHHELD_OTHER_REGION', $player, rank: 11);
            if ($name === 'OtherEvent') {
                $other = Event::factory()->create(['eventType' => $inter->eventType, 'published' => true]);
                $team->update(['category_event_id' => $this->division($other, 'U10 Girls')->id]);
                $other->regions()->attach($team->region_id);
            }
            if ($name === 'Unlinked') $team->team_players_no_profile()->update(['player_profile' => null]);
        }
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', [])
            ->assertDontSee('WITHHELD_');
    }

    public function test_platteland_origin_region_takes_precedence_over_a_second_inter_districts_membership(): void
    {
        [$event, $division, $inter, $platteland] = $this->events();
        [$player, $nominee] = $this->nominee($event, $division, 'BothSources');
        $this->team($platteland, 'OriginRegion', $player);
        $this->team($inter, 'Plat', $player, imported: true);
        $expected = [$division->id => [$nominee->id => ['region' => 'OriginRegion', 'source' => 'Platteland']]];
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', $expected)
            ->assertSee('(OriginRegion · Platteland)')->assertDontSee('(Plat · Inter-Districts)');
        // A conflict in the preferred source cannot be hidden by falling back to Inter-Districts.
        $this->team($platteland, 'ConflictingOrigin', $player);
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', []);
    }

    public function test_only_qualifying_platteland_memberships_override_inter_districts_and_blank_preferred_regions_stay_hidden(): void
    {
        [$event, $division, $inter, $platteland] = $this->events();
        $expected = [];
        foreach (['Hidden', 'WrongGroup', 'Unlinked', 'BlankRegion'] as $name) {
            [$player, $nominee] = $this->nominee($event, $division, $name);
            $this->team($inter, 'InterRegion', $player);
            $team = $this->team($platteland, $name === 'BlankRegion' ? '' : 'PlattelandRegion', $player,
                imported: true, age: $name === 'WrongGroup' ? 'U10 Boys' : 'U10 Girls', published: $name !== 'Hidden');
            if ($name === 'Unlinked') $team->team_players_no_profile()->update(['player_profile' => null]);
            if ($name !== 'BlankRegion') $expected[$nominee->id] = ['region' => 'InterRegion', 'source' => 'Inter-Districts'];
        }
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', [$division->id => $expected]);
    }

    public function test_hidden_inter_districts_reserves_private_finalization_wrong_group_and_ambiguous_memberships_never_supply_badges(): void
    {
        [$event, $division, $inter, $platteland] = $this->events();
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate('super-user', 'web'));
        [$capacityReserve] = $this->nominee($event, $division, 'CapacityReserve');
        $this->team($inter, 'PRIVATE_CAPACITY_RESERVE', $capacityReserve, rank: 11);
        [$frozenReserve] = $this->nominee($event, $division, 'FrozenReserve');
        $frozenTeam = $this->team($inter, 'PRIVATE_FROZEN_RESERVE', $frozenReserve);
        $final = EventSelectionFinalization::create(['event_id' => $inter->id, 'finalized_by' => $actor->id, 'draft_versions' => [],
            'groups' => [], 'team_size' => 10, 'reserve_count' => 5, 'fingerprint' => str_repeat('f', 64), 'finalized_at' => now()]);
        EventSelectionRosterImport::create(['event_id' => $inter->id, 'finalization_id' => $final->id, 'region_id' => $frozenTeam->region_id,
            'imported_by' => $actor->id, 'imported_at' => now(), 'groups' => ['10-girls' => ['team_id' => $frozenTeam->id,
                'players' => [['slot_id' => $frozenTeam->team_players()->first()->id, 'profile_id' => $frozenReserve->id, 'status' => 'reserve']]]]]);
        [$hiddenTeam] = $this->nominee($event, $division, 'HiddenTeam');
        $this->team($inter, 'PRIVATE_HIDDEN_TEAM', $hiddenTeam, published: false);
        [$wrongAge] = $this->nominee($event, $division, 'WrongAge');
        $this->team($inter, 'WRONG_AGE', $wrongAge, age: 'U11 Girls');
        [$wrongGender] = $this->nominee($event, $division, 'WrongGender');
        $this->team($inter, 'WRONG_GENDER', $wrongGender, age: 'U10 Boys');
        [$ambiguous] = $this->nominee($event, $division, 'Ambiguous');
        $this->team($platteland, 'AMBIGUOUS_A', $ambiguous);
        $this->team($platteland, 'AMBIGUOUS_B', $ambiguous);
        [$privateOnly] = $this->nominee($event, $division, 'PrivateFinalization');
        EventSelectionFinalization::create(['event_id' => $platteland->id, 'finalized_by' => $actor->id, 'draft_versions' => [],
            'groups' => ['10-girls' => ['snapshot' => [['id' => $privateOnly->id, 'region' => 'PRIVATE_FINALIZATION_ONLY']]]],
            'team_size' => 10, 'reserve_count' => 5, 'fingerprint' => str_repeat('e', 64), 'finalized_at' => now()]);
        $this->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', [])
            ->assertDontSee('PRIVATE_')->assertDontSee('AMBIGUOUS_')->assertDontSee('WRONG_');
        $this->actingAs($actor)->get(route('events.show', $event))->assertOk()->assertViewHas('nominationProvenance', []);
    }

    public function test_target_publication_identity_and_source_event_scope_are_required_without_name_guessing(): void
    {
        [$event, $division, $inter] = $this->events();
        [$player, $nominee] = $this->nominee($event, $division, 'Linked');
        $team = $this->team($inter, 'PublicRegion', $player);
        EventNomination::forceCreate(['event_id' => $event->id, 'category_event_id' => $division->id, 'nominee_name' => $player->name, 'nominee_surname' => $player->surname]);
        $service = app(PublicCavaliersNominationProvenance::class);
        $badges = $service->forEvent($event->fresh());
        $this->assertSame([$nominee->id], array_keys($badges[$division->id]));
        $division->update(['nominations_published' => false]);
        $this->assertSame([], $service->forEvent($event->fresh()));
        $division->update(['nominations_published' => true]);
        $other = Event::factory()->create(['eventType' => $inter->eventType, 'published' => true]);
        $team->update(['category_event_id' => $this->division($other, 'U10 Girls')->id]);
        $other->regions()->attach($team->region_id);
        $this->assertSame([], $service->forEvent($event->fresh()));
        $team->update(['category_event_id' => null]); // Shared-region legacy membership is not event ownership.
        $this->assertSame([], $service->forEvent($event->fresh()));
        $event->update(['published' => false]);
        $this->assertSame([], $service->forEvent($event->fresh()));
    }
}
