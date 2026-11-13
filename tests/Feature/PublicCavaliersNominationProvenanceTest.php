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

    public function test_hidden_reserves_private_finalization_wrong_group_and_ambiguous_memberships_never_supply_badges(): void
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
        $this->team($inter, 'AMBIGUOUS_A', $ambiguous);
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
