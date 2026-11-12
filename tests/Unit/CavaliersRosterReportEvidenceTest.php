<?php

namespace Tests\Unit;

use App\Services\CavaliersRosterReport;
use App\Support\CavaliersCandidateMatchEvidence;
use Tests\TestCase;

class CavaliersRosterReportEvidenceTest extends TestCase
{
    public function test_summary_counts_unique_explanations_and_does_not_treat_zero_results_as_missing(): void
    {
        $service = app(CavaliersRosterReport::class);
        $candidates = [
            ['id' => 1, 'name' => 'Zero Results', 'source' => 'Inter-Districts', 'wins' => 0, 'losses' => 0],
            ['id' => 2, 'name' => 'Missing Results', 'source' => 'Inter-Districts', 'wins' => null, 'losses' => null],
            ['id' => 3, 'name' => 'Declined Player', 'source' => 'Inter-Districts', 'wins' => 0, 'losses' => 2, 'lost_all' => true],
        ];
        $section = ['division' => 'Fictional Division', 'rows' => [
            ['status' => 'selected', 'position' => 1, 'name' => 'Selected Player'],
            ['status' => 'reserves', 'position' => 1, 'name' => 'Reserve Player'],
            ['status' => 'declined', 'position' => 1, 'name' => 'Declined Player'],
        ], 'explanations' => $service->explanationGroups($candidates, ['declined' => [3]]),
            'preview' => ['source_division' => 'Source Division', 'candidate_regions' => [], 'excluded_result_regions' => [], 'missing_regions' => [], 'platteland_notice' => null]];
        $this->assertSame(['selected' => 1, 'reserves' => 1, 'declined' => 1, 'explained' => 3, 'review_required' => 2, 'missing_evidence' => 1], $service->sectionSummary($section));
        $report = ['event_name' => 'Fictional Review', 'unsaved_divisions' => ['Unprepared Division'], 'sections' => [$section]];
        $html = view('backend.nominations.cavaliers-report-pdf', compact('report'))->render();
        $this->assertStringContainsString('<td>0 / 0</td>', $html);
        $this->assertSame(1, substr_count($html, 'No counted singles evidence.'));
        $this->assertStringContainsString('3 candidates explained below; 2 require selection review.', $html);
        $this->assertStringContainsString('Unprepared Division', $html);
        $duplicate = $section['explanations']['next_candidates'][0];
        $section['explanations']['inter_districts'][] = $duplicate;
        $this->assertSame(3, $service->sectionSummary($section)['explained']);
    }

    public function test_next_five_and_inter_districts_explanations_preserve_order_membership_deduplication_and_actual_numbers(): void
    {
        $candidates = [];
        for ($index = 1; $index <= 16; $index++) $candidates[] = ['id' => $index, 'name' => 'Candidate '.$index,
            'source' => $index === 6 ? 'Platteland' : 'Inter-Districts', 'rank' => 99, 'source_position' => 81, 'wins' => 0, 'losses' => 2, 'lost_all' => true, 'only_beat_winless' => false,
            'matches' => CavaliersCandidateMatchEvidence::project([
                ['opponent' => 'Opponent Alpha', 'won' => false, 'score' => '6–1, 6–2', 'roster_rank' => 3, 'opponent_rank' => 1],
                ['opponent' => 'Opponent Beta', 'won' => false, 'score' => '6–2, 6–3', 'roster_rank' => 4, 'opponent_rank' => 2],
            ])];
        // Existing merit order is authoritative; do not sort by player ID or aggregate rank.
        [$candidates[4], $candidates[5]] = [$candidates[5], $candidates[4]];
        $groups = app(CavaliersRosterReport::class)->explanationGroups($candidates, ['selected' => [1, 14], 'reserves' => [2], 'declined' => [3]]);
        $rows = $groups['next_candidates'];
        $this->assertSame([4, 6, 5, 7, 8], array_column($rows, 'id'));
        $this->assertSame([2, 3, 9, 10, 11, 12, 13, 15, 16], array_column($groups['inter_districts'], 'id'));
        $this->assertSame('Saved reserve #1.', $groups['inter_districts'][0]['selection_status']);
        $this->assertSame('Recorded as declined.', $groups['inter_districts'][1]['selection_status']);
        $this->assertSame('Declined', $groups['inter_districts'][1]['badge']);
        $this->assertStringContainsString('saved decline explains', $groups['inter_districts'][1]['review_note']);
        $this->assertStringContainsString('Selection review needed', $rows[0]['review_note']);
        $this->assertSame([3, 4], $rows[0]['playing_numbers']);
        $this->assertSame('Lost all matches', $rows[0]['badge']);
        $this->assertSame('Lost', $rows[0]['evidence']['matches'][0]['outcome']);
        $this->assertSame(3, $rows[0]['evidence']['matches'][0]['playing_number']);
        $this->assertArrayNotHasKey('opponent_rank', $rows[0]['evidence']['matches'][0]);
        $candidate = $candidates[0];
        $candidate['matches'] = CavaliersCandidateMatchEvidence::project([['opponent' => 'Unknown number opponent', 'won' => true, 'roster_rank' => 'PRIVATE_INVALID_NUMBER']]);
        $candidate['lost_all'] = false; $candidate['only_beat_winless'] = true;
        $unknown = app(CavaliersRosterReport::class)->explanationGroups([$candidate], [])['next_candidates'][0];
        $this->assertSame([], $unknown['playing_numbers']);
        $this->assertSame('Only beat winless players', $unknown['badge']);
        $candidate['matches'] = []; $candidate['wins'] = null; $candidate['losses'] = null; $candidate['only_beat_winless'] = false;
        $unknownInterDistricts = app(CavaliersRosterReport::class)->explanationGroups([$candidate], ['reserves' => [$candidate['id']]])['inter_districts'][0];
        $this->assertSame('Selector reason required', $unknownInterDistricts['badge']);
        $this->assertSame([], $unknownInterDistricts['playing_numbers']);
        $report = ['event_name' => 'Fictional Review', 'unsaved_divisions' => [], 'sections' => [[
            'division' => 'U11 Girls', 'rows' => [['name' => 'Selected Only Name', 'status' => 'selected', 'position' => 1, 'evidence' => null]],
            'explanations' => $groups, 'preview' => ['source_division' => 'U10 Girls', 'candidate_regions' => [], 'excluded_result_regions' => [], 'missing_regions' => [], 'platteland_notice' => null],
        ]]];
        $html = view('backend.nominations.cavaliers-report-pdf', compact('report'))->render();
        $this->assertSame(1, substr_count($html, 'Selected Only Name'));
        $this->assertStringContainsString('Next five outside the nomination list', $html);
        $this->assertStringContainsString('Inter-Districts players outside top 32', $html);
        $this->assertStringContainsString('Lost: Opponent Alpha (6–1, 6–2) · No. 3', $html);
        $this->assertStringContainsString('<td>3, 4</td>', $html);
        $this->assertStringNotContainsString('No. 99', $html);
    }
}
