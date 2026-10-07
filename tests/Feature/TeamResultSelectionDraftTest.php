<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\TeamResultRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamResultSelectionDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_are_private_event_scoped_versioned_and_preserve_server_evidence(): void
    {
        [$event, $input] = $this->scenario();
        $this->getJson(route('backend.team-result-selection.show', ['event' => $event, 'group_key' => '10-boys']))
            ->assertOk()->assertJsonPath('draft', null);
        $this->putJson(route('backend.team-result-selection.store', $event), $input + ['snapshot' => ['forged']])
            ->assertOk()->assertJsonPath('draft.version', 1)->assertJsonPath('draft.snapshot.0.points', 100);
        $this->putJson(route('backend.team-result-selection.store', $event), $input)->assertConflict();
        $input['version'] = 1;
        $input['selected_keys'] = ['imported:3:4'];
        $input['reasons'] = ['1' => 'Repeated losses warrant review.', 'imported:3:4' => 'Strong head to head evidence.'];
        $this->putJson(route('backend.team-result-selection.store', $event), $input)
            ->assertOk()->assertJsonPath('draft.version', 2)->assertJsonPath('draft.reasons.1', 'Repeated losses warrant review.');
        $this->assertDatabaseCount('team_result_selection_drafts', 1);
        $this->assertDatabaseCount('team_result_selection_revisions', 2);
        $other = Event::factory()->create();
        $this->getJson(route('backend.team-result-selection.show', ['event' => $other, 'group_key' => '10-boys']))
            ->assertOk()->assertJsonPath('draft', null);
        $this->actingAs(User::factory()->create());
        $this->getJson(route('backend.team-result-selection.show', ['event' => $event, 'group_key' => '10-boys']))->assertForbidden();
        $this->putJson(route('backend.team-result-selection.store', $event), $input)->assertForbidden();
        $this->assertDatabaseCount('team_result_selection_revisions', 2);
    }

    public function test_foreign_candidates_setup_duplicates_and_missing_decision_reasons_are_rejected(): void
    {
        [$event, $input] = $this->scenario();
        $url = route('backend.team-result-selection.store', $event);
        foreach ([
            ['selected_keys' => ['999']], ['selected_keys' => ['1', '1']],
            ['selected_keys' => array_fill(0, 11, '1')], ['region_ids' => [999]],
            ['formats' => ['doubles']], ['selected_keys' => ['imported:3:4']],
            ['selected_keys' => []], ['reasons' => ['foreign' => 'Reason']],
        ] as $override) {
            $this->putJson($url, array_replace($input, $override))->assertUnprocessable();
        }
        $this->putJson($url, array_replace($input, ['group_key' => '11-girls']))->assertNotFound();
        $this->assertDatabaseCount('team_result_selection_drafts', 0);
        $this->assertDatabaseCount('team_result_selection_revisions', 0);
    }

    public function test_selecting_a_cutoff_tie_requires_a_reason(): void
    {
        [$event, $input] = $this->scenario(true);
        $input['selected_keys'] = ['imported:3:4'];
        $input['reasons'] = ['1' => 'Excluded for review'];
        $this->putJson(route('backend.team-result-selection.store', $event), $input)->assertUnprocessable();
        $input['reasons']['imported:3:4'] = 'Head to head decision at cutoff';
        $this->putJson(route('backend.team-result-selection.store', $event), $input)->assertOk();
    }

    private function scenario(bool $tie = false): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Region', 'short_name' => 'REG']);
        $event->regions()->attach($region);
        $rankings = Mockery::mock(TeamResultRankingService::class);
        $rankings->shouldReceive('setup')->andReturn([
            'groups' => collect([['key' => '10-boys']]), 'regions' => collect([$region]), 'formats' => collect(['singles', 'reverse_singles']),
        ]);
        $rankings->shouldReceive('ranking')->withArgs(fn ($requestedEvent, $group, $regions, $formats) =>
            $requestedEvent->id === $event->id && $group === '10-boys' && $regions === [$region->id] && $formats === ['singles'])
            ->andReturn(collect([
                ['id' => 1, 'points' => 100, 'suggested' => true, 'cutoff_tie' => false],
                ['id' => 'imported:3:4', 'points' => 35, 'suggested' => false, 'cutoff_tie' => $tie],
            ]));
        $this->app->instance(TeamResultRankingService::class, $rankings);
        return [$event, ['group_key' => '10-boys', 'region_ids' => [$region->id], 'formats' => ['singles'],
            'selected_keys' => ['1'], 'reasons' => [], 'version' => 0]];
    }
}
