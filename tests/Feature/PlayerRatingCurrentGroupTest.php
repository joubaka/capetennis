<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, BallRatingModel, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayerRatingCurrentGroupTest extends TestCase
{
    public static function stages(): array
    {
        return [
            'red only' => [['red' => 0], 'red'],
            'orange above red' => [['red' => 90, 'orange' => 20], 'orange'],
            'standard above orange' => [['red' => 90, 'orange' => 80, 'standard' => 1], 'standard'],
            'invalid standard excluded' => [['orange' => 40, 'standard' => 101], 'orange'],
            'no valid current level' => [['red' => -1, 'orange' => INF], null],
        ];
    }

    #[DataProvider('stages')]
    public function test_history_uses_current_saved_level_and_ignores_requested_cohort(array $scores, ?string $expected): void
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $cohorts = [AllAgeRatingModel::COHORT => [42 => ['score' => 99, 'group' => 'all', 'component' => 'all']]];
        foreach ($scores as $stage => $score) $cohorts[BallRatingModel::COHORTS[$stage]][42] = ['score' => $score, 'group' => $stage, 'component' => $stage];
        // An older standard rating does not establish the current level.
        $months = [['date' => '2026-09-30', 'cohorts' => [BallRatingModel::COHORTS['standard'] => [42 => ['score' => 88, 'group' => 'old', 'component' => 'old']]]],
            ['date' => '2026-10-31', 'cohorts' => $cohorts]];
        $store->method('read')->willReturn(['source_fingerprint' => 'same', 'as_of' => '2026-10-31', 'built_at' => '2026-10-31', 'months' => $months, 'evidence' => []]);
        $player = new Player;
        $player->setRawAttributes(['id' => 42, 'name' => 'Synthetic', 'surname' => 'Player']);
        $service = new PlayerRatingHistoryService($ability, $store);
        foreach ([AllAgeRatingModel::COHORT, ...array_values(BallRatingModel::COHORTS)] as $requested) {
            $history = $service->forPlayer($player, ['cohort' => $requested, 'month' => 0]);
            $this->assertSame($expected, $history['ball_stage']);
            $this->assertSame($expected ? BallRatingModel::COHORTS[$expected] : '', $history['cohort']);
            $this->assertSame('full', $history['scale']);
            $this->assertSame(0.0, (float) $history['axis']['min']);
            $this->assertSame(100.0, (float) $history['axis']['max']);
            $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
            $this->assertStringContainsString('data-current-rating-group', $html);
            $this->assertStringContainsString($expected ? BallRatingModel::LABELS[$expected] : 'Not rated yet', $html);
            $this->assertStringNotContainsString('name="cohort"', $html);
            $this->assertStringNotContainsString('id="ctr-ball"', $html);
        }
    }
}
