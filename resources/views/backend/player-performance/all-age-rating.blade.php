<p class="h2">{{ is_numeric($estimate['score'] ?? null) ? number_format($estimate['score'], 1).'/100' : 'Shared rating withheld' }}</p>
<p>One singles rating across age groups · provisional · comparison group {{ $estimate['component'] }}</p>
@if($estimate['reason'] ?? null)<p role="status">{{ $estimate['reason'] }}</p>@endif
<p>Evidence confidence: {{ $estimate['confidence_label'] ?? 'Low' }}. {{ $estimate['played'] ?? 0 }} recorded matches against {{ $estimate['direct_opponents'] ?? 0 }} distinct opponents.</p>
@if(($estimate['comparable'] ?? true) === false)<p class="alert alert-warning" role="status"><strong>Provisional within-group rating · low confidence.</strong> This estimate is not calibrated against other comparison groups. A matching number elsewhere does not establish equal ability.</p>@endif
<p class="small">{{ $estimate['confidence_explanation'] ?? '' }}</p>
@if(!empty($estimate['historical_connection']))
<p class="alert alert-warning text-dark" role="status"><strong>Low confidence — historical connection.</strong> This comparison needs a match link from {{ $estimate['connection_reference_date'] ?? 'an older date' }} ({{ $estimate['connection_age_days'] ?? '?' }} days old). Recent matches within the player's own group do not make that link recent.</p>
@endif
<p>Recorded age categories: {{ implode(', ', $estimate['age_groups'] ?? []) ?: 'Not yet established' }}.</p>
<p class="small">Last direct match: {{ $estimate['last_direct_match'] ?? 'None' }}. Evidence as of {{ $estimate['confidence_as_of'] ?? 'Pending' }}. Confidence describes the available evidence, not an accuracy percentage.</p>
<x-rating-explanation />
