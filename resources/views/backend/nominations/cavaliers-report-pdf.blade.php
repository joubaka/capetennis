<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Private nomination report</title>
<style>
@page { margin: 34px 42px 48px; }
body { font: 11px "DejaVu Sans", sans-serif; line-height: 1.4; color: #17283b; }
h1 { font-size: 21px; line-height: 1.2; margin: 5px 0 9px; }
h2 { font-size: 17px; color: #17487c; margin: 14px 0 4px; }
h3 { font-size: 12px; color: #17487c; margin: 15px 0 5px; }
h2, h3 { page-break-after: avoid; }
p { margin: 4px 0; }
table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
thead { display: table-header-group; }
th, td { padding: 4px 7px; text-align: left; vertical-align: top; border-bottom: 1px solid #d9e3eb; }
th { background: #edf3fa; color: #17487c; font-size: 9px; }
tr { page-break-inside: avoid; }
.division-next { page-break-before: always; }
.eyebrow { font-size: 9px; font-weight: bold; letter-spacing: 1px; color: #17487c; text-transform: uppercase; }
.muted { color: #526379; }
.note { background: #f2f5f8; border-left: 3px solid #17487c; padding: 7px 10px; margin: 8px 0; }
.overview { font-size: 9px; }
.summary { margin: 6px 0; color: #17487c; font-weight: bold; }
.candidate-head { background: #edf3fa; padding: 6px 8px; margin-top: 10px; page-break-inside: avoid; page-break-after: avoid; }
.candidate-head strong { font-size: 11px; }
.candidate-facts { margin: 0 0 4px; }
.candidate-facts td { font-size: 9px; padding: 4px 8px; }
.candidate-note { margin: 4px 8px; }
.badge { font-weight: bold; color: #914500; }
.match { margin: 2px 8px; font-size: 10px; page-break-inside: avoid; }
</style></head><body>
<div class="eyebrow">Cape Tennis / Private nomination review</div>
<h1>{{ $report['event_name'] }}</h1>
<p class="muted">Saved nominations and supporting evidence for selection review. This report does not publish the rosters.</p>
<table class="overview"><thead><tr><th>Prepared division</th><th>Selected</th><th>Reserves</th><th>Review required*</th></tr></thead><tbody>
@foreach ($report['sections'] as $section)
@php($summary = app(\App\Services\CavaliersRosterReport::class)->sectionSummary($section))
<tr><td>{{ $section['division'] }}</td><td>{{ $summary['selected'] }}</td><td>{{ $summary['reserves'] }}</td><td>{{ $summary['review_required'] }}</td></tr>
@endforeach
</tbody></table>
<p class="muted">*Explained candidates with an unrecorded selector reason or an explicit review-order flag. Performance flags alone do not confirm why a player was omitted.</p>
@if ($report['unsaved_divisions'])<p><strong>Not prepared:</strong> {{ implode(', ', $report['unsaved_divisions']) }}.</p>@endif
@foreach ($report['sections'] as $section)
@php($summary = app(\App\Services\CavaliersRosterReport::class)->sectionSummary($section))
<section class="{{ $loop->first ? 'division-first' : 'division-next' }}">
<h2>{{ $section['division'] }}</h2>
<p class="summary">{{ $summary['selected'] }} selected / {{ $summary['reserves'] }} reserves / {{ $summary['declined'] }} recorded declines</p>
<p>{{ $summary['explained'] }} candidates explained below; {{ $summary['review_required'] }} require selection review. Counted singles evidence is unavailable for {{ $summary['missing_evidence'] }} of these candidates.</p>
<div class="note">
<p><strong>Evidence scope:</strong> {{ $section['preview']['source_division'] }}. Regions: {{ implode(', ', $section['preview']['candidate_regions']) ?: 'None' }}.</p>
<p>Results excluded against: {{ implode(', ', $section['preview']['excluded_result_regions']) ?: 'None' }}.</p>
@if ($section['preview']['missing_regions'])<p>No published team for: {{ implode(', ', $section['preview']['missing_regions']) }}.</p>@endif
@if ($section['preview']['platteland_notice'])<p>{{ $section['preview']['platteland_notice'] }}</p>@endif
</div>
@foreach (['selected' => 'Selected players', 'reserves' => 'Next five reserves'] as $status => $title)
<h3>{{ $title }}</h3>
<table><thead><tr><th style="width:6%">#</th><th style="width:47%">Player</th><th>Region / source</th></tr></thead><tbody>
@forelse (collect($section['rows'])->where('status', $status) as $row)
<tr><td>{{ $row['position'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['evidence']['region'] ?? '-' }} / {{ $row['evidence']['source'] ?? 'No source evidence' }}</td></tr>
@empty<tr><td colspan="3">None recorded.</td></tr>@endforelse
</tbody></table>
@endforeach
<div class="note muted">Review order follows the source band/results order, then the finalized Platteland additions. Selection credit is separate from played wins. Scores retain the source fixture's home-away order. Playing numbers come from match evidence; a roster place does not prove a match was played.</div>
@foreach (['next_candidates' => 'Next five outside the nomination list', 'inter_districts' => 'Inter-Districts players outside top 32'] as $group => $heading)
<h3>{{ $heading }}</h3>
@if ($group === 'inter_districts')<p class="muted">Additional eligible Inter-Districts candidates, including reserves and declines. Candidates explained above are not repeated.</p>
@else<p class="muted">Next available candidates in review order, excluding selected players, saved reserves and declines.</p>@endif
@forelse ($section['explanations'][$group] as $row)
@php($evidence = $row['evidence'])
<div class="candidate-head"><strong>{{ $loop->iteration }}. {{ $row['name'] }}</strong><br><span class="muted">{{ $evidence['region'] ?? 'Region unavailable' }} / {{ $evidence['source'] ?? 'No candidate source' }} / Review order #{{ $row['source_order'] }}</span></div>
<table class="candidate-facts"><thead><tr><th>Saved status</th><th>Counted wins / losses</th><th>Playing No.</th><th>Selection credit / band</th></tr></thead><tbody><tr>
<td>{{ $row['selection_status'] }}</td>
<td>{{ isset($evidence['wins'], $evidence['losses']) ? $evidence['wins'].' / '.$evidence['losses'] : 'Unavailable' }}</td>
<td>{{ ($row['playing_numbers'] ?? []) ? implode(', ', $row['playing_numbers']) : 'Not recorded' }}</td>
<td>{{ $evidence['starting_credit'] ?? 'Not recorded' }} / {{ $evidence['band'] ?? 'Not recorded' }}</td>
</tr></tbody></table>
<p class="candidate-note"><span class="badge">{{ $row['badge'] }}</span>@if ($row['review_note']) - {{ $row['review_note'] }}@endif</p>
@if (!isset($evidence['wins'], $evidence['losses']))<p class="candidate-note muted">No counted singles evidence.</p>@endif
@foreach ($evidence['matches'] ?? [] as $match)
<div class="match">{{ $match['outcome'] }}: {{ $match['opponent'] }}{{ $match['score'] ? ' ('.$match['score'].')' : '' }}{{ isset($match['playing_number']) ? ' · No. '.$match['playing_number'] : '' }}</div>
@endforeach
@empty<p class="muted">No further available candidates.</p>@endforelse
@endforeach
</section>
@endforeach
</body></html>
