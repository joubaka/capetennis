<p class="h2">{{ is_numeric($estimate['score'] ?? null) ? number_format($estimate['score'], 1).'/100' : 'Shared rating withheld' }}</p>
<p>One singles rating across age groups · provisional · comparison group {{ $estimate['component'] }}</p>
@if($estimate['reason'] ?? null)<p role="status">{{ $estimate['reason'] }}</p>@endif
<p>Evidence confidence: {{ $estimate['confidence_label'] ?? 'Low' }}. {{ $estimate['played'] ?? 0 }} recorded matches against {{ $estimate['direct_opponents'] ?? 0 }} distinct opponents.</p>
<p class="small">{{ $estimate['confidence_explanation'] ?? '' }}</p>
@if(!empty($estimate['historical_connection']))
<p class="alert alert-warning text-dark" role="status"><strong>Low confidence — historical connection.</strong> This comparison needs a match link from {{ $estimate['connection_reference_date'] ?? 'an older date' }} ({{ $estimate['connection_age_days'] ?? '?' }} days old). Recent matches within the player's own group do not make that link recent.</p>
@endif
<p>Recorded age categories: {{ implode(', ', $estimate['age_groups'] ?? []) ?: 'Not yet established' }}.</p>
<p class="small">Last direct match: {{ $estimate['last_direct_match'] ?? 'None' }}. Evidence as of {{ $estimate['confidence_as_of'] ?? 'Pending' }}. Confidence describes the available evidence, not an accuracy percentage.</p>
@if(!empty($estimate['standard_assumed_matches']))
<p class="small">Includes {{ $estimate['standard_assumed_matches'] }} legacy matches with an assumed standard playing format. These matches lack explicit ball or court confirmation.</p>
@endif
<details><summary class="py-2">How the shared rating works</summary>
    <p>Eligible played singles results connect players across recorded ages and event categories. Opponent strength, score margins and recency affect the estimate. Age, gender, finishing positions and age-specific trial baselines award no strength bonus. Entering a new age group does not reset the rating.</p>
    <p>Results from the last two years can connect players. Recent results carry more weight, with match weight halving every six months. The reference starts from the largest group connected within the last year. Comparisons needing older links are explicitly Low confidence; a disconnected player has no shared score. Red, orange and green-ball formats are excluded. Younger categories require explicit standard-format evidence.</p>
    <p>The scale is provisional and local to Cape Tennis. It is not a conversion to UTR or World Tennis Number. Ratings can change as opponents play or the comparison network changes.</p>
</details>
