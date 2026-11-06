<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\PlayerRatingHistoryDisplay;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class PlayerRatingHeaderPresentationTest extends TestCase
{
    private function renderHeader(int $month = 11, array $changes = [], array $viewData = []): array
    {
        $player = new Player; $player->setRawAttributes(['id' => 42, 'name' => 'Example', 'surname' => 'Player']);
        $points = [
            ['index' => 10, 'date' => '2026-10-31', 'score' => 61.1, 'group' => 'a', 'component' => 'a', 'confidence_label' => 'Low'],
            ['index' => 11, 'date' => '2026-11-03', 'score' => 66.6, 'group' => 'a', 'component' => 'a', 'confidence_label' => 'Medium'],
        ];
        $months = []; for ($index = 0; $index < 12; $index++) $months[] = ['date' => '2026-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-01'];
        $months[10]['date'] = '2026-10-31'; $months[11]['date'] = '2026-11-03';
        $history = ['reason' => null, 'as_of' => '2026-11-03', 'built_at' => '2026-11-03T03:00:00+02:00', 'stale' => false,
            'points' => $points, 'series' => ['orange ball · singles' => $points], 'point' => $points[$month === 11 ? 1 : 0],
            'previous' => $month === 11 ? $points[0] : null, 'months' => $months, 'month' => $month, 'scope' => 'new', 'scale' => 'full',
            'cohort' => 'orange ball · singles', 'ball_stage' => 'orange', 'current_group_label' => 'Orange ball',
            'axis' => PlayerRatingHistoryDisplay::axis($points, 'full'),
            'evidence' => ['rows' => [], 'included' => 3, 'new' => 0, 'matches' => 3, 'wins' => 2, 'losses' => 1, 'finishes' => 0, 'pages' => 1, 'page' => 1, 'total' => 0]];
        $html = view('frontend.my-tennis.rating', $viewData + ['ratingPlayer' => $player, 'ratingHistory' => array_replace($history, $changes)])->render();
        if (getenv('CT_RATING_HEADER_QA') === '1') {
            $fixture = ($viewData['embeddedRating'] ?? false) ? 'overview' : (isset($changes['reason']) ? 'withheld' : (isset($changes['points']) ? 'local' : ($month === 11 ? 'latest' : 'historical')));
            $directory = storage_path('framework/testing/rating-header-qa');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
            file_put_contents($directory.'/'.$fixture.'.html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/vendor/css/core.css"><title>Local synthetic rating preview</title></head><body>'.$html.'</body></html>');
        }
        $document = new DOMDocument; @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        return [$html, new DOMXPath($document)];
    }

    public function test_embedded_overview_shows_the_saved_rating_and_positions_before_the_top_ten(): void
    {
        [$html, $xpath] = $this->renderHeader(viewData: ['embeddedRating' => true, 'ratingTopTen' => [
            'visible' => true, 'can_browse' => false, 'selected_age' => 'u13',
            'age_options' => ['u13' => 'U13 and younger'], 'filters' => ['rating_gender' => 'girls', 'rating_per_page' => 10],
            'as_of' => '2026-11-03', 'stale' => false, 'reason' => null,
            'summary' => ['age' => ['player' => ['score' => 72, 'position' => 2], 'total' => 5]],
            'lists' => ['overall' => ['players' => [['id' => 42, 'name' => 'Example Player', 'position' => 2, 'score' => 72]]]],
        ]]);

        $overview = $xpath->query('//*[@id="ctr-panel-compare" and contains(@class, "active")]')->item(0);
        $this->assertNotNull($overview);
        $this->assertSame(1, $xpath->query('.//*[@data-current-rating-summary]', $overview)->length);
        $this->assertLessThan(strpos($html, 'Your rating positions'), strpos($html, 'Latest saved rating'));
        $this->assertLessThan(strpos($html, 'Your age-group Top 10'), strpos($html, 'Your rating positions'));
        $this->assertStringNotContainsString('name="rating_search"', $html);
        $this->assertStringNotContainsString('name="rating_per_page"', $html);
        $this->assertStringNotContainsString('rating_per_page=10', $html);
    }

    public function test_header_contains_the_only_latest_summary_and_embedded_navigation(): void
    {
        [$html, $xpath] = $this->renderHeader();
        $this->assertSame(1, $xpath->query('//*[@data-rating-hero]//*[@data-current-rating-summary]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-rating-hero]//*[@data-ctr-badge]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-rating-hero]//nav[@aria-label="Player Plus views"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-selected-month-summary]//*[@data-ctr-badge]')->length);
        $this->assertStringContainsString('66.6', $xpath->query('//*[@data-current-rating-summary]')->item(0)->textContent);
        $this->assertStringContainsString('Medium confidence', $xpath->query('//*[@data-current-rating-summary]')->item(0)->textContent);
        $this->assertStringContainsString('Latest saved month', $html);
        $this->assertLessThan(strpos($html, 'id="ctr-month"'), strpos($html, 'role="region" aria-label="Rating chart'));
        $this->assertStringNotContainsString('Past-event ratings and badges', $html);
        $this->assertStringNotContainsString('ctr-current-summary', $html);
    }

    public function test_historical_selected_month_keeps_its_own_distinct_badge(): void
    {
        request()->query->set('rating_section', 'history');
        [, $xpath] = $this->renderHeader(10);
        $this->assertSame(1, $xpath->query('//*[@id="ctr-panel-progress" and contains(@class, "active")]')->length);
        $selected = $xpath->query('//*[@data-selected-month-summary]')->item(0);
        $this->assertSame(1, $xpath->query('.//*[@data-ctr-badge]', $selected)->length);
        $this->assertStringContainsString('61.1', $selected->textContent);
        $this->assertStringNotContainsString('66.6', $selected->textContent);
        $this->assertStringContainsString('66.6', $xpath->query('//*[@data-current-rating-summary]')->item(0)->textContent);
    }

    public function test_withheld_history_never_promotes_a_leftover_saved_point_to_the_header(): void
    {
        [$html, $xpath] = $this->renderHeader(11, ['reason' => 'Source publication changed']);
        $this->assertSame(0, $xpath->query('//*[@data-rating-hero]//*[@data-ctr-badge]')->length);
        $this->assertStringContainsString('Source publication changed', $html);
        $this->assertStringContainsString('No rating yet.', $html);
    }

    public function test_header_keeps_local_finishing_only_and_historical_qualifiers_with_the_badge(): void
    {
        [, $xpath] = $this->renderHeader(11, ['points' => [['index' => 11, 'date' => '2026-11-03', 'score' => 43.9,
            'group' => 'a', 'component' => 'a', 'confidence_label' => 'Low', 'evidence_basis' => 'finish_only',
            'connection_status' => 'disconnected', 'historical_connection' => true, 'connection_reference_date' => '2025-01-01']]]);
        $header = $xpath->query('//*[@data-current-rating-summary]')->item(0);
        foreach (['43.9', 'within-group only', 'Low confidence', 'final placings only', '2025-01-01'] as $qualifier) $this->assertStringContainsString($qualifier, $header->textContent);
    }
}
