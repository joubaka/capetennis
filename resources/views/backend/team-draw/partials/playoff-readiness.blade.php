@if($playoffReadiness)
<section class="card card-body mb-3" data-playoff-readiness="{{ $draw->id }}" aria-label="Playoff readiness">
  <h2 class="h5">Playoff readiness</h2>
  <dl class="row mb-2">
    <dt class="col-sm-5">Round-robin encounters</dt><dd class="col-sm-7">{{ $playoffReadiness['completed_encounters'] }} / {{ $playoffReadiness['expected_encounters'] }} complete · {{ $playoffReadiness['saved_encounters'] }} saved</dd>
    <dt class="col-sm-5">Match results</dt><dd class="col-sm-7">{{ $playoffReadiness['completed_rubbers'] }} / {{ $playoffReadiness['total_rubbers'] }} complete</dd>
    <dt class="col-sm-5">Scoring</dt><dd class="col-sm-7">{{ $playoffReadiness['scoring_confirmed'] ? 'Confirmed' : 'To be decided' }}</dd>
    <dt class="col-sm-5">Standings</dt><dd class="col-sm-7">{{ $playoffReadiness['ranks_resolved'] ? 'Ranks resolved' : ($playoffReadiness['scoring_confirmed'] && $playoffReadiness['completed_encounters'] === $playoffReadiness['expected_encounters'] && $playoffReadiness['saved_encounters'] === $playoffReadiness['expected_encounters'] ? 'Tied ranks require review' : 'Review after all encounter results are complete') }}</dd>
    <dt class="col-sm-5">Saved playoff format</dt><dd class="col-sm-7">{{ $playoffReadiness['format_counts']['singles'] ?? 0 }} singles · {{ $playoffReadiness['format_counts']['doubles'] ?? 0 }} doubles · {{ $playoffReadiness['format_counts']['mixed_doubles'] ?? 0 }} mixed doubles — {{ $playoffReadiness['format_total'] }} matches per encounter</dd>
    <dt class="col-sm-5">Playoff programme</dt><dd class="col-sm-7">{{ $playoffReadiness['playoff'] ? 'Qualified; saved schedule retained. Review publication separately.' : (($playoffReadiness['draft_playoff'] ?? null) ? 'Draft fixtures ready to schedule; qualified teams pending.' : 'Prepare draft fixtures to plan placement times before qualification.') }}</dd>
  </dl>
  @if(!$playoffReadiness['playoff'])
    @if($playoffReadiness['blockers'])
      <ul>@foreach($playoffReadiness['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
    @else<p>Ready to review qualifiers. Creating playoffs freezes these round-robin results.</p>@endif
  @endif
  <a class="align-self-start" href="{{ route('backend.team-draw.standings', $draw) }}">Review standings and qualifiers</a>
  <p class="small text-muted mt-2 mb-0">Playoff places use match wins. Equal match wins leave places unresolved; confirm the agreed deciding procedure before play.</p>
</section>
@endif
