@extends('layouts/layoutMaster')

{{-- Page JS --}}
@section('page-script')
<script src="{{ asset(mix('js/insert-score.js')) }}"></script>
@endsection
@section('title', 'Enter Fixture Scores')

@section('content')
<div class="container-xxl py-4">
    <h2 class="mb-3">Enter Scores</h2>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-bold">Scheduled</th>
                            <th>#</th>
                            <th class="d-none d-sm-table-cell">Round</th>
                            <th class="d-none d-md-table-cell">Match #</th>
                            <th>Home</th>
                            <th></th>
                            <th>Away</th>
                            <th>Result</th>
                            <th class="d-none d-lg-table-cell">Venue</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($fixtures as $fx)
                        @php
                            $homeNames = [];
                            $awayNames = [];
                            $homeRegionShort = $fx->region1Name?->short_name ?? null;
                            $awayRegionShort = $fx->region2Name?->short_name ?? null;
                            
                        @endphp
                        @foreach($fx->fixturePlayers as $fpRow)
                            @php
                                // HOME
                                if ($fpRow->team1_id && $fpRow->player1) {
                                    $name = $fpRow->player1->full_name;
                                    if($homeRegionShort) $name.=" ({$homeRegionShort})";
                                    $homeNames[]=$name;
                                }
                                elseif ($fpRow->team1_no_profile_id) {
                                    $np = \App\Models\NoProfileTeamPlayer::find($fpRow->team1_no_profile_id);
                                    if($np){
                                        $name = trim($np->name.' '.$np->surname);
                                        if($homeRegionShort) $name.=" ({$homeRegionShort})";
                                        $homeNames[]=$name;
                                    }
                                }
                                // AWAY
                                if ($fpRow->team2_id && $fpRow->player2) {
                                    $name = $fpRow->player2->full_name;
                                    if($awayRegionShort) $name.=" ({$awayRegionShort})";
                                    $awayNames[]=$name;
                                }
                                elseif ($fpRow->team2_no_profile_id) {
                                    $np2 = \App\Models\NoProfileTeamPlayer::find($fpRow->team2_no_profile_id);
                                    if($np2){
                                        $name = trim($np2->name.' '.$np2->surname);
                                        if($awayRegionShort) $name.=" ({$awayRegionShort})";
                                        $awayNames[]=$name;
                                    }
                                }
                            @endphp
                        @endforeach
                        @php
                            $homeLabel = count($homeNames)?collect($homeNames)->implode(' + '):'TBD';
                            $awayLabel = count($awayNames)?collect($awayNames)->implode(' + '):'TBD';
                            $display = $fx->scheduled_at ?? null;
                            $homeClass = '';
                            $awayClass = '';
                            if ($fx->fixtureResults->count()) {
                                $lastSet = $fx->fixtureResults->last();
                                if ($lastSet->team1_score > $lastSet->team2_score) {
                                    $homeClass = 'winner-home';
                                    $awayClass = 'loser-home';
                                } elseif ($lastSet->team2_score > $lastSet->team1_score) {
                                    $homeClass = 'loser-home';
                                    $awayClass = 'winner-home';
                                } else {
                                    $homeClass = 'draw-cell';
                                    $awayClass = 'draw-cell';
                                }
                            }
                        @endphp
                        <tr id="row-{{ $fx->id }}">
                            <td class="fw-bold">
                                @if($display)
                                    {{ \Carbon\Carbon::parse($display)->format('Y-m-d H:i') }}
                                @else — @endif
                            </td>
                            <td>{{ $fx->id }}</td>
                            <td class="d-none d-sm-table-cell">{{ $fx->round_nr }}</td>
                            <td class="d-none d-md-table-cell">{{ $fx->home_rank_nr }}</td>
                            <td class="home-cell {{ $homeClass }}">
                                @if($fx->home_rank_nr) ({{ $fx->home_rank_nr }}) @endif {{ $homeLabel }}
                                
                            </td>
                            <td class="text-center" style="width:32px;">
                                <span class="badge bg-light border text-secondary">vs</span>
                            </td>
                            <td class="away-cell {{ $awayClass }}">
                                @if($fx->away_rank_nr) ({{ $fx->away_rank_nr }}) @endif {{ $awayLabel }}
                                
                            </td>
                            <td id="result-col-{{ $fx->id }}">
                                @forelse($fx->fixtureResults as $r)
                                    {{ $r->team1_score }}-{{ $r->team2_score }}@if(!$loop->last), @endif
                                @empty
                                    <span class="text-muted">No result</span>
                                @endforelse
                            </td>
                            <td class="d-none d-lg-table-cell">{{ optional($fx->venue)->name ?? '—' }}</td>
                            <td class="text-end" id="actions-col-{{ $fx->id }}">
                                @include('frontend.fixtures.partials.actions', [
                                    'fixture' => $fx,
                                    'homeLabel' => $homeLabel,
                                    'awayLabel' => $awayLabel
                                ])
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Score Modal -->
<div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content">
      <form id="editScoreForm" method="POST" action="">
        @csrf
        <input type="hidden" name="fixture_id" id="editFixtureId">
        <div class="modal-header">
          <h5 class="modal-title">Enter Score</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong id="fixtureTeams"></strong></p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Set</th>
                  <th>Home</th>
                  <th>Away</th>
                </tr>
              </thead>
              <tbody>
                @for($i = 1; $i <= 3; $i++)
                  <tr>
                    <td>Set {{ $i }}</td>
                    <td><input type="number" class="form-control form-control-sm" name="set{{ $i }}_home" id="set{{ $i }}Home" min="0"></td>
                    <td><input type="number" class="form-control form-control-sm" name="set{{ $i }}_away" id="set{{ $i }}Away" min="0"></td>
                  </tr>
                @endfor
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer flex-column flex-sm-row">
          <button type="button" class="btn btn-outline-secondary w-100 mb-2 mb-sm-0" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary w-100">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.winner-home { background-color: rgba(40,167,69,.25)!important; }
.loser-home { background-color: rgba(220,53,69,.25)!important; }
.draw-cell { background-color: rgba(255,193,7,.25)!important; }
@media (max-width: 576px) {
    .table th, .table td { font-size: 0.85rem; padding: 0.25rem; }
    .modal-content { border-radius: 0; }
    .modal-header, .modal-footer { padding: 0.75rem; }
    .modal-footer .btn { font-size: 1rem; }
}
</style>
@endsection



