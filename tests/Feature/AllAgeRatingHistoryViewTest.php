<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Tests\TestCase;

class AllAgeRatingHistoryViewTest extends TestCase
{
    public function test_empty_shared_history_renders_unrated_and_retains_explicit_context_selector(): void
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $store->method('read')->willReturn(['source_fingerprint' => 'same', 'as_of' => '2026-10-01', 'built_at' => '2026-10-01', 'evidence' => [],
            'months' => [['date' => '2026-10-01', 'cohorts' => ['u13 boys' => [1 => ['score' => 75, 'group' => 'old', 'component' => 'old']], AllAgeRatingModel::COHORT => []]]]]);
        $player = new Player;
        $player->setRawAttributes(['id' => 1, 'name' => 'Synthetic', 'surname' => 'Player']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer($player, ['month' => 0]);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        $this->assertStringContainsString('Unrated', $html);
        $this->assertStringContainsString('No comparable shared rating is available', $html);
        $this->assertStringContainsString('u13 boys', $html);
        $this->assertStringNotContainsString('75.0', $html);
    }
}
