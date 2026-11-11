<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Private nomination report</title>
<style>body{font:10px "DejaVu Sans",sans-serif;color:#17283b}h1{font-size:19px}h2{font-size:15px;color:#17487c}h3{font-size:12px}h2,h3{page-break-after:avoid}table{width:100%;border-collapse:collapse;margin:8px 0}th,td{padding:5px;text-align:left;vertical-align:top;border-bottom:1px solid #d9e3eb}th{background:#edf3fa}tr{page-break-inside:avoid}.division{page-break-before:always}.muted{color:#526379}</style></head><body>
<h1>{{ $report['event_name'] }}</h1><p><strong>Private nomination review</strong></p>
<p>Saved selected players and up to five ordered reserves in every prepared division. The explanation list covers all saved players and candidates available to the preparing administrator. It is not a list of every registered player in the source tournaments.</p>
<p>Result flags describe counted singles under each division's current setup. They are supporting evidence, not a recorded individual selection decision. Selection credit is separate from played wins.</p>
@if ($report['unsaved_divisions'])<p><strong>Not prepared:</strong> {{ implode(', ', $report['unsaved_divisions']) }}.</p>@endif
@foreach ($report['sections'] as $section)
<section class="division"><h2>{{ $section['division'] }}</h2>
<p>Inter-Districts source: {{ $section['preview']['source_division'] }}. Included regions: {{ implode(', ', $section['preview']['candidate_regions']) ?: 'None' }}. Results excluded against: {{ implode(', ', $section['preview']['excluded_result_regions']) ?: 'None' }}.</p>
@if ($section['preview']['missing_regions'])<p>No published team for: {{ implode(', ', $section['preview']['missing_regions']) }}.</p>@endif
@if ($section['preview']['platteland_notice'])<p>{{ $section['preview']['platteland_notice'] }}</p>@endif
@foreach (['selected' => 'Selected players', 'reserves' => 'Next five reserves'] as $status => $title)
<h3>{{ $title }}</h3><table><thead><tr><th>#</th><th>Player</th><th>Region / source</th></tr></thead><tbody>
@forelse (collect($section['rows'])->where('status', $status) as $row)<tr><td>{{ $row['position'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['evidence']['region'] ?? '—' }} / {{ $row['evidence']['source'] ?? 'No source evidence' }}</td></tr>@empty<tr><td colspan="3">None recorded.</td></tr>@endforelse
</tbody></table>@endforeach
<h3>Player explanation list</h3><table><thead><tr><th>Player / status</th><th>Counted singles</th><th>Evidence and explanation</th></tr></thead><tbody>
@foreach ($section['rows'] as $row)
@php($evidence = $row['evidence'])
<tr><td>{{ $row['name'] }}<br><span class="muted">{{ ucfirst($row['status']) }}{{ $row['position'] ? ' #'.$row['position'] : '' }} · {{ $evidence['source'] ?? 'No candidate source' }}</span></td>
<td>{{ isset($evidence['wins']) ? $evidence['wins'].'–'.$evidence['losses'] : 'Unavailable' }}</td><td>
@if ($row['status'] === 'declined')Recorded as declined in the saved roster.
@elseif ($row['status'] === 'omitted')Not in the saved selected or reserve lists. No individual decision reason recorded.
@elseif ($row['status'] === 'selected')Included in the saved selected list.
@else Included as an ordered reserve.
@endif
@if ($evidence['lost_all'] ?? false) Lost all counted singles.
@elseif ($evidence['only_beat_winless'] ?? false) Only beat opponents who lost all counted singles.
@elseif (!isset($evidence['wins'])) No counted singles evidence available in this review.
@else No zero-win or only-beat-winless flag in this review.
@endif
</td></tr>@endforeach
</tbody></table></section>@endforeach</body></html>

