<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventRegion;
use App\Models\EventRegionManager;
use App\Models\EventType;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamsWorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function teamEvent(): array
    {
        EventType::unguarded(fn () => EventType::updateOrCreate(['id' => 3], ['name' => 'Teams QA', 'type' => EventType::TEAM, 'code' => 'teams-qa']));
        $event = Event::factory()->create(['eventType' => 3, 'name' => 'Synthetic Teams QA']);
        $admin = User::factory()->create();
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $region = TeamRegion::create(['region_name' => 'Synthetic North Region']);
        $eventRegion = EventRegion::forceCreate(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);

        return [$event, $admin, $eventRegion];
    }

    public function test_admin_has_the_same_teams_navigation_and_preserved_panels_on_both_pages(): void
    {
        [$event, $admin] = $this->teamEvent();
        $this->actingAs($admin);
        $invitationUrl = route('backend.team-selection.index', [$event, 'workspace' => 'invitations']);
        $roster = $this->get(route('admin.events.teams', $event))->assertOk();
        $invitations = $this->get($invitationUrl)->assertOk();
        foreach ([$roster, $invitations] as $response) {
            $response->assertSee('aria-label="Teams workspace"', false)
                ->assertSeeInOrder(['Players', 'Invitations', 'Player order', 'Setup', 'Result ranks'])
                ->assertSee($invitationUrl, false);
        }
        foreach (['tab-players', 'tab-order', 'tab-regions', 'tab-result-rank'] as $panel) {
            $roster->assertSee('id="'.$panel.'"', false);
        }
        $invitations->assertSee('Import &amp; send', false)->assertSee('Selection &amp; reserves', false);
        $otherEvent = Event::factory()->create(['eventType' => 3]);
        $this->get(route('admin.events.teams', $otherEvent))->assertForbidden();
        $this->get(route('backend.team-selection.index', $otherEvent))->assertForbidden();

        if (getenv('TEAMS_QA_EXPORT')) {
            $directory = storage_path('framework/testing/teams-qa');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            $html = fn ($response) => preg_replace('~http://localhost/(assets|css|js|build)/~', 'http://localhost/ct/public/$1/', $response->getContent());
            file_put_contents($directory.'/players.html', $html($roster));
            file_put_contents($directory.'/invitations.html', $html($invitations));
            $region = $event->regions->first();
            file_put_contents($directory.'/players-region.html', $html($this->get(route('admin.events.teams', [$event, 'roster_region' => $region->id, 'panel' => 'players']))->assertOk()));
            file_put_contents($directory.'/order-region.html', $html($this->get(route('admin.events.teams', [$event, 'roster_region' => $region->id, 'panel' => 'order']))->assertOk()));
            file_put_contents($directory.'/routes.json', json_encode(['players' => route('admin.events.teams', $event), 'invitations' => $invitationUrl], JSON_PRETTY_PRINT));
        }
    }

    public function test_scorekeeper_can_view_the_roster_without_an_inaccessible_invitations_link(): void
    {
        [$event] = $this->teamEvent();
        $scorekeeper = User::factory()->create();
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorekeeper->id, 'role' => 'score-keeper']);
        $this->actingAs($scorekeeper)->get(route('admin.events.teams', $event))->assertOk()
            ->assertSee('aria-label="Teams workspace"', false)
            ->assertDontSee(route('backend.team-selection.index', [$event, 'workspace' => 'invitations']), false);
        $this->get(route('backend.team-selection.index', $event))->assertForbidden();
    }

    public function test_regional_invitations_stay_visible_without_links_to_admin_roster_and_other_regions(): void
    {
        [$event, $admin, $eventRegion] = $this->teamEvent();
        $otherRegion = TeamRegion::create(['region_name' => 'Private South Region']);
        EventRegion::forceCreate(['event_id' => $event->id, 'region_id' => $otherRegion->id, 'ordering' => 2]);
        $manager = User::factory()->create();
        EventRegionManager::create(['event_id' => $event->id, 'event_region_id' => $eventRegion->id, 'region_id' => $eventRegion->region_id, 'user_id' => $manager->id, 'assigned_by' => $admin->id]);
        $response = $this->actingAs($manager)->get(route('backend.team-selection.index', [$event, 'workspace' => 'invitations']))->assertOk()
            ->assertSee('Invitations</button>', false)->assertSee('Players</button>', false)
            ->assertSee('Synthetic North Region')->assertDontSee('Private South Region')
            ->assertDontSee(route('admin.events.teams', $event), false);
        $this->get(route('admin.events.teams', $event))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('backend.team-selection.index', $event))->assertForbidden();
        if (getenv('TEAMS_QA_EXPORT')) file_put_contents(storage_path('framework/testing/teams-qa/regional.html'), preg_replace('~http://localhost/(assets|css|js|build)/~', 'http://localhost/ct/public/$1/', $response->getContent()));
    }
}
