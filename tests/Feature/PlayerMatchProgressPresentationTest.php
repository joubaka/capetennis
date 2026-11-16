<?php

namespace Tests\Feature;

use App\Models\Player;
use Tests\TestCase;

class PlayerMatchProgressPresentationTest extends TestCase
{
    private function renderRating(bool $numeric): string
    {
        $player = new Player;
        $player->id = 42;
        $rows = [];
        for ($index = 0; $index < 5; $index++) {
            $available = $numeric && $index !== 2;
            $rows[] = ['date' => '2026-11-14', 'time' => '2026-11-14 09:00:00', 'opponent' => 'Fictional opponent '.($index + 1),
                'won' => true, 'score' => '6-3', 'before_score' => $available ? 50 : null, 'after_score' => $available ? 51 : null,
                'delta' => $available ? 1 : null, 'reason' => $available ? null : 'The same player has two matches at this scheduled time.'];
        }
        return view('frontend.my-tennis.rating', ['embeddedRating' => true, 'ratingPlayer' => $player,
            'ratingHistory' => ['reason' => 'Saved monthly history pending.'], 'ratingTopTen' => ['visible' => false],
            'matchProgress' => ['reason' => null, 'rows' => $rows, 'as_of' => '2026-11-16', 'restricted' => 0]])->render();
    }

    public function test_unavailable_rating_pairs_show_compact_results_and_reasons_instead_of_blank_chart(): void
    {
        $html = $this->renderRating(false);
        $dom = new \DOMDocument; @$dom->loadHTML($html); $xp = new \DOMXPath($dom);
        $this->assertSame(0, $xp->query('//*[@data-match-progress-chart]//svg')->length);
        $this->assertSame(5, $xp->query('//*[@data-match-progress-unavailable]/article')->length);
        $this->assertSame(5, $xp->query('//*[@data-match-progress-unavailable]//button[@data-match-explanation-target]')->length);
        $this->assertSame(5, $xp->query('//*[@data-match-explanation]')->length);
        $this->assertStringContainsString('The same player has two matches at this scheduled time.', $html);
        $this->assertStringContainsString('Other matches at the same time and later results are excluded', $html);
        $this->writeFixture('rating-match-progress-unavailable-qa.html', $html);
    }

    public function test_mixed_rating_pairs_keep_fixed_scale_and_only_available_numeric_arrows(): void
    {
        $html = $this->renderRating(true);
        $dom = new \DOMDocument; @$dom->loadHTML($html); $xp = new \DOMXPath($dom);
        $this->assertSame(1, $xp->query('//*[@data-match-progress-chart]//svg')->length);
        $this->assertSame(4, $xp->query('//*[@data-match-progress-segment]')->length);
        $this->assertSame(0, $xp->query('//*[@data-match-progress-unavailable]')->length);
        $this->assertStringContainsString('fixed 0–100 CTR scale', $html);
        $this->writeFixture('rating-match-progress-qa.html', $html);
    }

    private function writeFixture(string $name, string $html): void
    {
        if (getenv('CT_PROFILE_MATCH_QA_HTML') !== '1') return;
        $head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/vendor/css/core.css"><style>body{background:#f4f7fb;margin:0;padding:16px}.qa-wrap{max-width:1050px;margin:auto}</style>';
        file_put_contents(storage_path('framework/testing/'.$name), '<!doctype html><html><head>'.$head.'</head><body><main class="qa-wrap">'.$html.'</main></body></html>');
    }
}
