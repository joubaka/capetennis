<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Tests\TestCase;

class AllAgeRatingHistoryViewTest extends TestCase
{
    public function test_my_tennis_shows_only_one_shared_rating_even_when_a_historical_cohort_is_requested(): void
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $store->method('read')->willReturn(['source_fingerprint' => 'same', 'as_of' => '2026-10-01', 'built_at' => '2026-10-01', 'evidence' => [],
            'months' => [['date' => '2026-10-01', 'cohorts' => ['u13 boys' => [1 => ['score' => 75, 'group' => 'old', 'component' => 'old']], AllAgeRatingModel::COHORT => []]]]]);
        $player = new Player;
        $player->setRawAttributes(['id' => 1, 'name' => 'Synthetic', 'surname' => 'Player']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer($player, ['month' => 0, 'cohort' => 'u13 boys']);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        $this->assertStringContainsString('Unrated', $html);
        $this->assertStringContainsString('No comparable shared rating is available', $html);
        $this->assertStringNotContainsString('u13 boys', $html);
        $this->assertStringNotContainsString('ctr-cohort', $html);
        $this->assertStringNotContainsString('name="cohort"', $html);
        $this->assertStringContainsString('All ages · singles', $html);
        $this->assertStringNotContainsString('75.0', $html);
    }
}
