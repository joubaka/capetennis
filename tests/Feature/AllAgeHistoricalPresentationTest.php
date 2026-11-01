<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Tests\TestCase;

class AllAgeHistoricalPresentationTest extends TestCase
{
    public function test_latest_disconnected_badge_remains_local_when_an_older_connected_month_is_selected(): void
    {
        $saved = $this->savedHistory();
        $latest = $saved['months'][0];
        $latest['date'] = '2026-11-01';
        $latest['cohorts'][AllAgeRatingModel::COHORT][1] = ['score' => 93.1, 'group' => 'local', 'component' => 'local', 'connection_status' => 'disconnected'];
        $saved['months'][] = $latest;
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $store->method('read')->willReturn($saved);
        $player = new Player;
        $player->setRawAttributes(['id' => 1, 'name' => 'Synthetic', 'surname' => 'Player']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer($player, ['month' => 0]);
        $this->assertSame('historical', $history['point']['connection_status']);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        $this->assertStringContainsString('93.1', $html);
        $this->assertStringContainsString('within-group only', $html);
    }

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

    public function test_disconnected_numeric_rating_is_visible_with_an_explicit_low_confidence_warning(): void
    {
        $saved = $this->savedHistory();
        $saved['months'][0]['cohorts'][AllAgeRatingModel::COHORT][1] = [
            'score' => 61.5, 'group' => 'local', 'component' => 'local', 'connection_status' => 'disconnected',
            'historical_connection' => false, 'connection_age_days' => null, 'connection_reference_date' => null,
        ];
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $store->method('read')->willReturn($saved);
        $player = new Player;
        $player->setRawAttributes(['id' => 1, 'name' => 'Synthetic', 'surname' => 'Player']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer($player, ['month' => 0]);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        $this->assertStringContainsString('61.5', $html);
        $this->assertStringContainsString('Provisional rating · low confidence', $html);
        $this->assertStringContainsString('not yet calibrated against other comparison groups', $html);
        $estimate = ['score' => 61.5, 'component' => 'local', 'confidence_label' => 'Low', 'comparable' => false];
        $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
        $this->assertStringContainsString('61.5/100', preg_replace('/\s+/', '', strip_tags($html)));
        $this->assertStringContainsString('ctr-badge--local', $html);
        $this->assertStringContainsString('All-age rating unavailable — insufficient connecting results', $html);
        $this->assertStringContainsString('Within-group score only:', $html);
        $this->assertStringNotContainsString('<p class="h2">61.5/100</p>', $html);
        $this->assertStringNotContainsString('One singles rating across age groups', $html);
        $this->assertStringContainsString('Provisional within-group rating · low confidence', $html);
    }

    public function test_disconnected_saved_status_withholds_headline_even_without_comparable_flag(): void
    {
        $estimate = ['score' => 93.1, 'component' => 'local', 'connection_status' => 'disconnected'];
        $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
        $this->assertStringContainsString('All-age rating unavailable — insufficient connecting results', $html);
        $this->assertStringContainsString('Within-group score only:', $html);
        $this->assertStringContainsString('CTR 93.1 within-group only', $html);
        $this->assertStringContainsString('ctr-badge--local', $html);
        $this->assertStringNotContainsString('<p class="h2">93.1/100</p>', $html);
    }

    public function test_connected_rating_retains_numeric_all_age_headline(): void
    {
        $estimate = ['score' => 61.5, 'component' => 'shared', 'comparable' => true, 'connection_status' => 'recent'];
        $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
        $this->assertStringContainsString('data-ctr-badge', $html);
        $this->assertStringContainsString('61.5/100', preg_replace('/\s+/', '', strip_tags($html)));
        $this->assertStringNotContainsString('ctr-badge--local', $html);
        $this->assertStringContainsString('One singles rating across age groups', $html);
        $this->assertStringNotContainsString('Within-group score only:', $html);
        $this->assertStringNotContainsString('All-age rating unavailable', $html);
    }

    public function test_unavailable_score_does_not_render_a_within_group_number(): void
    {
        foreach ([null, 'not-a-number'] as $score) {
            $estimate = ['score' => $score, 'component' => 'local', 'comparable' => false];
            $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
            $this->assertStringContainsString('<p class="h2">All-age rating unavailable</p>', $html);
            $this->assertStringNotContainsString('insufficient connecting results', $html);
            $this->assertStringNotContainsString('is not connected', $html);
            $this->assertStringNotContainsString('Within-group score only:', $html);
            $this->assertStringNotContainsString('/100', $html);
        }
        $estimate = ['score' => null, 'component' => 'shared', 'comparable' => true];
        $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
        $this->assertStringContainsString('<p class="h2">Shared rating withheld</p>', $html);
    }

    public function test_nonconverged_score_retains_the_actual_failure_reason(): void
    {
        $estimate = ['score' => null, 'component' => 'shared', 'comparable' => false,
            'connection_status' => 'disconnected', 'reason' => 'The shared model did not converge.'];
        $html = view('backend.player-performance.all-age-rating', compact('estimate'))->render();
        $this->assertStringContainsString('<p class="h2">All-age rating unavailable</p>', $html);
        $this->assertStringContainsString('The shared model did not converge.', $html);
        $this->assertStringNotContainsString('insufficient connecting results', $html);
        $this->assertStringNotContainsString('is not connected', $html);
        $this->assertStringNotContainsString('Within-group score only:', $html);
        $this->assertStringNotContainsString('Provisional within-group rating', $html);
    }
}
