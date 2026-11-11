<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Private nomination report</title>
<style>body{font:10px "DejaVu Sans",sans-serif;color:#17283b}h1{font-size:19px}h2{font-size:15px;color:#17487c}h3{font-size:12px}h2,h3{page-break-after:avoid}table{width:100%;border-collapse:collapse;margin:8px 0}th,td{padding:5px;text-align:left;vertical-align:top;border-bottom:1px solid #d9e3eb}th{background:#edf3fa}tr{page-break-inside:avoid}.division{page-break-before:always}.muted{color:#526379}.badge{font-weight:bold;color:#914500}p{margin:5px 0}.match{font-size:9px}</style></head><body>
<h1>{{ $report['event_name'] }}</h1><p><strong>Private nomination review</strong></p>
<p>Selected players, up to five reserves, the next five outside that list, and Inter-Districts players outside the saved top 32. Players appear once in the explanations; declines are excluded from the next five.</p>
<p>Scope: eligible candidates from the configured published regional rosters. Counted singles use each division's result exclusions. Playing numbers come from match evidence; a roster place does not prove a match was played.</p>
@if ($report['unsaved_divisions'])<p><strong>Not prepared:</strong> {{ implode(', ', $report['unsaved_divisions']) }}.</p>@endif
@foreach ($report['sections'] as $section)
<section class="division"><h2>{{ $section['division'] }}</h2>
<p>Source: {{ $section['preview']['source_division'] }}. Regions: {{ implode(', ', $section['preview']['candidate_regions']) ?: 'None' }}. Results excluded against: {{ implode(', ', $section['preview']['excluded_result_regions']) ?: 'None' }}.</p>
@if ($section['preview']['missing_regions'])<p>No published team for: {{ implode(', ', $section['preview']['missing_regions']) }}.</p>@endif
@if ($section['preview']['platteland_notice'])<p>{{ $section['preview']['platteland_notice'] }}</p>@endif
@foreach (['selected' => 'Selected players', 'reserves' => 'Next five reserves'] as $status => $title)
<h3>{{ $title }}</h3><table><thead><tr><th>#</th><th>Player</th><th>Region / source</th></tr></thead><tbody>
@forelse (collect($section['rows'])->where('status', $status) as $row)<tr><td>{{ $row['position'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['evidence']['region'] ?? '—' }} / {{ $row['evidence']['source'] ?? 'No source evidence' }}</td></tr>@empty<tr><td colspan="3">None recorded.</td></tr>@endforelse
</tbody></table>@endforeach
<p>Review order follows the source band/results order, then the finalized Platteland additions. Selection credit is separate from played wins. Scores retain the source fixture's home–away order. The saved list may reflect manual choices; performance flags alone do not confirm why a player was omitted.</p>
@foreach (['next_candidates' => 'Next five outside the nomination list', 'inter_districts' => 'Inter-Districts players outside top 32'] as $group => $heading)
<h3>{{ $heading }}</h3>
@if ($group === 'inter_districts')<p class="muted">Additional players from the eligible configured Inter-Districts pool, including reserves and declines. Those shown in the next five above are not repeated. Unavailable or excluded match evidence remains unconfirmed.</p>@endif
<table><thead><tr><th style="width:23%">Player</th><th>Wins</th><th>Losses</th><th>Playing No.</th><th style="width:45%">Explanation / matches</th></tr></thead><tbody>
@forelse ($section['explanations'][$group] as $row)
@php($evidence = $row['evidence'])
<tr><td>{{ $loop->iteration }}. {{ $row['name'] }}<br><span class="muted">{{ $evidence['source'] ?? 'No candidate source' }}</span></td>
<td>{{ $evidence['wins'] ?? '—' }}</td><td>{{ $evidence['losses'] ?? '—' }}</td>
<td>{{ ($row['playing_numbers'] ?? []) ? implode(', ', $row['playing_numbers']) : 'Not recorded' }}</td>
<td><span class="badge">{{ $row['badge'] }}</span><div>{{ $row['selection_status'] }} Review order #{{ $row['source_order'] }}{{ isset($evidence['band']) ? ' · Band '.$evidence['band'] : '' }}{{ isset($evidence['starting_credit']) ? ' · Selection credit '.$evidence['starting_credit'] : '' }}.</div>
<div class="muted">{{ $row['review_note'] }}</div>
@forelse ($evidence['matches'] ?? [] as $match)<div class="match">{{ $match['outcome'] }}: {{ $match['opponent'] }}{{ $match['score'] ? ' ('.$match['score'].')' : '' }}{{ isset($match['playing_number']) ? ' · No. '.$match['playing_number'] : '' }}</div>
@empty @if (!isset($evidence['wins']))<div class="muted">No counted singles evidence.</div>@endif
@endforelse
</td></tr>@empty<tr><td colspan="5">No further available candidates.</td></tr>@endforelse
</tbody></table>@endforeach</section>@endforeach</body></html>
