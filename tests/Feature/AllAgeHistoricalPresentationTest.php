<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Tests\TestCase;

class AllAgeHistoricalPresentationTest extends TestCase
{
    private function savedHistory(): array
    {
        return ['source_fingerprint' => 'same', 'as_of' => '2026-10-01', 'built_at' => '2026-10-01', 'evidence' => [],
            'months' => [['date' => '2026-10-01', 'cohorts' => [AllAgeRatingModel::COHORT => [1 => [
                'score' => 61.5, 'group' => 'shared', 'component' => 'shared', 'connection_status' => 'historical',
                'historical_connection' => true, 'connection_age_days' => 500, 'connection_reference_date' => '2025-05-19',
            ]]]]]];
    }

    public function test_saved_history_retains_historical_link_warning_and_date_in_the_rendered_month(): void
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $store->method('read')->willReturn($this->savedHistory());
        $player = new Player;
        $player->setRawAttributes(['id' => 1, 'name' => 'Synthetic', 'surname' => 'Player']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer($player, ['month' => 0]);
        $this->assertTrue($history['point']['historical_connection']);
        $this->assertSame(500, $history['point']['connection_age_days']);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        $this->assertStringContainsString('Low confidence — historical connection', $html);
        $this->assertStringContainsString('2025-05-19', $html);
        $this->assertStringContainsString('500 days old', $html);
    }

    public function test_profile_warning_distinguishes_old_connection_from_recent_own_results(): void
    {
        $estimate = ['score' => 61.5, 'component' => 'shared', 'confidence_label' => 'Low', 'historical_connection' => true,
            'connection_age_days' => 500, 'connection_reference_date' => '2025-05-19', 'last_direct_match' => '2026-10-01'];
        $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
        $this->assertStringContainsString('Low confidence — historical connection', $html);
        $this->assertStringContainsString('Recent matches within the player', $html);
        $this->assertStringContainsString('2026-10-01', $html);
        $this->assertStringContainsString('2025-05-19', $html);
        $recent = array_replace($estimate, ['historical_connection' => false]);
        $this->assertStringNotContainsString('role="status"', view('backend.player-performance.all-age-rating', ['estimate' => $recent])->render());
    }
}
