<?php

namespace Tests\Unit;

use App\Services\Performance\PlayerRatingHistoryDisplay;
use Tests\TestCase;

class PlayerRatingSnapshotTest extends TestCase
{
    private function point(int $index, float $score = 50, string $group = 'a'): array
    {
        return ['index' => $index, 'date' => '2026-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-01',
            'score' => $score, 'group' => $group, 'confidence_label' => 'Medium', 'private_contact' => 'never-output@example.test'];
    }

    public function test_snapshot_is_bounded_and_whitelisted_without_other_evidence_or_identity_fields(): void
    {
        $points = []; for ($i = 0; $i < 120; $i++) $points[] = $this->point($i % 12);
        $snapshot = PlayerRatingHistoryDisplay::snapshot(['points' => $points, 'ball_stage' => 'red', 'as_of' => '2026-12-31',
            'reason' => null, 'stale' => true, 'evidence' => [['private' => 'secret']]]);
        $this->assertCount(12, $snapshot['points']); $this->assertSame('Red ball', $snapshot['label']);
        $this->assertSame('red', $snapshot['stage']); $this->assertTrue($snapshot['stale']);
        $this->assertStringNotContainsString('private_contact', json_encode($snapshot));
        $this->assertStringNotContainsString('evidence', json_encode(array_diff_key($snapshot, ['points' => true])));
        $this->assertStringNotContainsString('never-output', json_encode($snapshot));
        $this->assertSame(50.0, $snapshot['points'][0]['score']);
    }

    public function test_withheld_and_invalid_points_never_produce_an_invented_zero_or_rating(): void
    {
        $this->assertSame([], PlayerRatingHistoryDisplay::snapshot(['reason' => 'Publication changed', 'points' => [$this->point(0)]])['points']);
        $points = [$this->point(0, 0), $this->point(1, 100), $this->point(2, -1), $this->point(3, INF),
            $this->point(12), array_replace($this->point(4), ['date' => '2026-02-31']), $this->point(11)];
        $snapshot = PlayerRatingHistoryDisplay::snapshot(['points' => $points, 'as_of' => '2026-10-01']);
        $this->assertSame([0, 1], array_column($snapshot['points'], 'index'));
        $this->assertSame([0.0, 100.0], array_column($snapshot['points'], 'score'));
        $this->assertSame([], PlayerRatingHistoryDisplay::snapshot(['points' => []])['points']);
    }

    public function test_mini_trend_keeps_missing_month_gaps_and_dashed_group_changes(): void
    {
        $snapshot = PlayerRatingHistoryDisplay::snapshot(['points' => [$this->point(0, 0), $this->point(1, 100), $this->point(3, 50, 'b'), $this->point(4, 60, 'c')]]);
        $html = view('frontend.my-tennis.rating-snapshot', ['ratingSnapshot' => $snapshot])->render();
        $document = new \DOMDocument; @$document->loadHTML($html); $xpath = new \DOMXPath($document);
        $this->assertSame(4, $xpath->query('//*[@data-snapshot-point]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-rating-mini-trend]//line[@stroke-width]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-snapshot-group-change]')->length);
        $this->assertSame('#ff9f43', $xpath->query('//*[@data-snapshot-point]')->item(3)->getAttribute('fill'));
        $this->assertStringContainsString('not like-for-like progress', $html);
        $this->assertStringContainsString('fixed zero to one hundred scale', $html);
    }

    public function test_conflicting_duplicate_months_are_not_silently_connected(): void
    {
        $snapshot = PlayerRatingHistoryDisplay::snapshot(['points' => [$this->point(0), $this->point(0, 75), $this->point(1)]]);
        $this->assertSame([1], array_column($snapshot['points'], 'index'));
    }
}
