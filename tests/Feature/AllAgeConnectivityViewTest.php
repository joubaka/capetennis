<?php

namespace Tests\Feature;

use App\Services\Performance\AllAgeConnectivityService;
use Tests\TestCase;

class AllAgeConnectivityViewTest extends TestCase
{
    public function test_connection_priorities_are_explicit_suggestions_and_never_imply_confirmed_results(): void
    {
        $plan = (new AllAgeConnectivityService)->summarize([
            'reference_component' => 'a', 'components' => ['a' => ['members' => [1]], 'b' => ['members' => [2]]],
            'players' => [1 => ['age_groups' => ['u13 boys']], 2 => ['age_groups' => ['u13 girls']]],
            'excluded' => ['Ball and court format not established' => 2],
        ]);
        $html = view('backend.player-performance.connection-plan', compact('plan'))->render();
        $this->assertStringContainsString('2 separate groups', $html);
        $this->assertStringContainsString('Minimum connections needed to join the current network: 1', $html);
        $this->assertStringContainsString('not scheduled matches or confirmed links', $html);
        $this->assertStringContainsString('verified boys/girls singles connection', $html);
        $this->assertStringContainsString('Ball and court format not established: 2 matches', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('50.0', $html);
        if (getenv('CT_ALL_AGE_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/all-age-connections-fragment.html'), $html);
        }
    }
}
