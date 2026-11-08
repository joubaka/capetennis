@php($disconnected = ($estimate['comparable'] ?? true) === false || ($estimate['connection_status'] ?? null) === 'disconnected')
@if($disconnected)
@if(is_numeric($estimate['score'] ?? null))
<p class="h2">All-age rating unavailable — insufficient connecting results</p>
<p>Comparison group {{ $estimate['component'] }} is not connected to the shared all-age reference.</p>
<p>Within-group score only: <x-ctr-badge :overall="true" :score="$estimate['score']" :local="true" />. This is not an all-age rating.</p>
@else
<p class="h2">All-age rating unavailable</p>
@endif
@else
@if(is_numeric($estimate['score'] ?? null))<p><x-ctr-badge :overall="true" :score="$estimate['score']" /></p>@else<p class="h2">Shared rating withheld</p>@endif
<p>One singles rating across age groups · provisional · comparison group {{ $estimate['component'] }}</p>
@endif
@if($estimate['reason'] ?? null)<p role="status">{{ $estimate['reason'] }}</p>@endif
<p>Evidence confidence: {{ $estimate['confidence_label'] ?? 'Low' }}. {{ $estimate['played'] ?? 0 }} recorded matches against {{ $estimate['direct_opponents'] ?? 0 }} distinct opponents.</p>
@if($disconnected && is_numeric($estimate['score'] ?? null))<p class="alert alert-warning" role="status"><strong>Provisional within-group rating · low confidence.</strong> This estimate is not calibrated against other comparison groups. A matching number elsewhere does not establish equal ability.</p>@endif
@unless($performanceDetailPage ?? false)
<p class="small">{{ $estimate['confidence_explanation'] ?? '' }}</p>
@endunless
@if(!empty($estimate['historical_connection']))
<p class="alert alert-warning text-dark" role="status"><strong>Low confidence — historical connection.</strong> This comparison needs a match link from {{ $estimate['connection_reference_date'] ?? 'an older date' }} ({{ $estimate['connection_age_days'] ?? '?' }} days old). Recent matches within the player's own group do not make that link recent.</p>
@endif
@if($performanceDetailPage ?? false)
<details class="mb-3">
    <summary class="text-primary py-2">See rating dates and confidence details</summary>
    <p class="small mt-2">{{ $estimate['confidence_explanation'] ?? '' }}</p>
@endif
<p>Recorded age categories: {{ implode(', ', $estimate['age_groups'] ?? []) ?: 'Not yet established' }}.</p>
<p class="small">Last direct match: {{ $estimate['last_direct_match'] ?? 'None' }}. Evidence as of {{ $estimate['confidence_as_of'] ?? 'Pending' }}. Confidence describes the available evidence, not an accuracy percentage.</p>
@if($performanceDetailPage ?? false)
</details>
@endif
<x-rating-explanation />
