@once
<style>
.team-rr-scroll{overflow:auto;max-width:100%;isolation:isolate}.team-rr-table{border-collapse:separate;border-spacing:0;margin:0;min-width:100%}.team-rr-table th,.team-rr-table td{padding:.75rem;border-bottom:1px solid #d9dee3;border-right:1px solid #d9dee3;text-align:center;vertical-align:middle;min-width:108px}.team-rr-table .team-rr-name{position:sticky;left:0;z-index:2;background:var(--bs-body-bg,#fff);min-width:160px;max-width:220px;white-space:normal;text-align:left;overflow-wrap:anywhere}.team-rr-table thead th{background:var(--bs-tertiary-bg,#f5f5f9);white-space:normal}.team-rr-table thead .team-rr-name{z-index:3}.team-rr-table .team-rr-self{background:#eceef1;color:#697a8d}.team-rr-cell{display:flex;min-height:48px;flex-direction:column;align-items:center;justify-content:center;gap:.2rem;padding:.3rem;text-decoration:none;border-radius:.35rem}.team-rr-cell[href]{border:1px solid #d9dee3}.team-rr-cell[href]:hover,.team-rr-cell[href]:focus-visible{background:#e8edff;outline:2px solid #696cff}.team-rr-cell small{font-size:.75rem}.team-rr-caption{caption-side:top;padding:.75rem;text-align:left}
@media(max-width:575px){.team-rr-table th,.team-rr-table td{padding:.45rem;min-width:96px}.team-rr-table .team-rr-name{min-width:132px;max-width:150px;font-size:.85rem}}
</style>
@endonce
<section class="card mb-4" data-team-round-robin-matrix>
  <div class="card-header"><h2 class="h5 mb-1">{{ $matrix['draw']->drawName }}: round-robin matrix</h2>
    <p class="mb-0">Read each result from the row team's perspective. Scores show rubbers won. @if($matrixScoring ?? true)Select an encounter to enter or review its match scores.@endif</p>
    <p class="small text-muted mb-0 mt-2">Scroll across for all opponents and totals. Match points plus tie bonus points equal total points. Rankings and points use this draw's saved scoring rules.</p>
  </div>
  <div class="team-rr-scroll" tabindex="0" role="region" aria-label="{{ $matrix['draw']->drawName }} round-robin results">
    <table class="team-rr-table"><caption class="visually-hidden">Team encounters and current standings</caption><thead><tr><th scope="col" class="team-rr-name">Team</th>
      @foreach($matrix['rows'] as $opponent)<th scope="col">{{ $opponent['name'] }}</th>@endforeach
      @foreach(['Played','Won','Drawn','Lost','Match points','Tie points','Total points','Position'] as $label)<th scope="col">{{ $label }}</th>@endforeach
    </tr></thead><tbody>
    @forelse($matrix['rows'] as $row)<tr><th scope="row" class="team-rr-name">{{ $row['name'] }}</th>
      @foreach($matrix['rows'] as $opponent)
        @if($row['team_id'] === $opponent['team_id'])<td class="team-rr-self" aria-label="Same team">—</td>
        @else<td>
          @forelse($matrix['cells'][$row['team_id']][$opponent['team_id']] ?? [] as $cell)
            @php($scoreLink = ($matrixScoring ?? true) && $cell['canScore'])
            @if($scoreLink)<a class="team-rr-cell" href="{{ route('frontend.fixtures.enter-scores', ['draw' => $matrix['draw']->id, 'tie' => $cell['tie']->id]) }}" aria-label="Score {{ $row['name'] }} versus {{ $opponent['name'] }}, round {{ $cell['tie']->round_nr }}">
            @else<div class="team-rr-cell">@endif
              <strong>{{ $cell['for'] }}–{{ $cell['against'] }}</strong><small>{{ $cell['status'] }}</small><small>Round {{ $cell['tie']->round_nr }}</small>
            @if($scoreLink)</a>@else</div>@endif
          @empty<span class="text-muted">Not scheduled</span>@endforelse
        </td>@endif
      @endforeach
      @foreach(['played','wins','draws','losses','match_points','tie_points','points','rank'] as $metric)<td>{{ $row[$metric] ?? '—' }}</td>@endforeach
    </tr>@empty<tr><td colspan="9">No confirmed scoring rules or team encounters are available yet.</td></tr>@endforelse
    </tbody></table>
  </div>
</section>
