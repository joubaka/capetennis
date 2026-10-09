@extends('layouts.backend')

{{-- Vendor CSS --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

{{-- Page JS --}}
@section('page-script')
@include('backend.team-fixtures.partials.participant-revision-script')
<script src="{{ asset(mix('js/draw-fixtures-show.js')) }}"></script>
@endsection

@section('content')

<style>
.team-fixtures-view .fixture-region { display: flex; align-items: center; gap: .45rem; font-weight: 700; color: #18324b; margin-bottom: .4rem; letter-spacing: .03em; }
.team-fixtures-view .fixture-region-logo { width: 28px; height: 28px; flex: 0 0 28px; object-fit: contain; }
.team-fixtures-view .fixture-players { display: flex; flex-wrap: wrap; gap: .35rem; max-width: 22rem; }
.team-fixtures-view .fixture-player-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .6rem; border: 1px solid #dce5ef; border-radius: .5rem; background: #f4f7fb; color: #334b66; font-size: .85rem; line-height: 1.4; white-space: normal; }
.team-fixtures-view .fixture-player-link:hover { background: #e7effa; border-color: #8daed7; color: #173e71; }
.team-fixtures-view .fixture-player-link:focus-visible { outline: 3px solid #497ab8; outline-offset: 2px; }
.team-fixtures-view .fixture-rank { color: #596c83; font-variant-numeric: tabular-nums; }
.team-fixtures-view .table > tbody > tr > td { padding: .8rem .9rem; }
.team-fixtures-view .table > thead > tr > th { padding: .85rem .9rem; white-space: nowrap; }
.team-fixtures-view .fixture-id { color: #697a8d; font-size: .8rem; }
.team-fixtures-view .fixture-group td { background: #edf2f8; font-weight: 600; color: #18324b; padding: .65rem .9rem !important; }
.team-fixtures-view .winner-home { background-color: #e8f5eb !important; }
.team-fixtures-view .loser-home { background-color: #fff1f0 !important; }
.team-fixtures-view .draw-cell { background-color: #fff8e4 !important; }
@media (max-width: 767px) {
  .team-fixtures-view .fixture-secondary { display: none; }
  .team-fixtures-view .table { min-width: 650px; }
  .team-fixtures-view .fixture-players { max-width: 15rem; }
}
</style>


<div class="container-xxl team-fixtures-view">
<div class="card">
<div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div><h4 class="mb-1">Team fixtures</h4><p class="text-muted mb-0">{{ $event?->name ?? 'Manage match lineups and scores' }}</p></div>
  <span class="badge bg-label-primary">{{ number_format($fixtures->total()) }} {{ \Illuminate\Support\Str::plural('fixture', $fixtures->total()) }}</span>
</div>
<div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-0">

<thead class="table-light">
<tr>
<th class="fixture-secondary">#</th>
<th class="fixture-secondary">Draw</th>
<th class="fixture-secondary">Round</th>
<th>Match</th>
<th>Home</th>
<th>Away</th>
<th>Result</th>
<th>Scheduled</th>
<th>Venue</th>
<th class="text-end">Actions</th>
</tr>
</thead>

<tbody>
@forelse($fixtures as $fx)

@php
$homeClass = '';
$awayClass = '';

// Determine if this is a v2 tie-based rubber
$isV2 = !is_null($fx->team_tie_id);
@endphp

@php [$homeClass, $awayClass] = \App\Support\ResultPresentation::classes($fx); @endphp


@php
$home = $fx->lineup_display['home'];
$away = $fx->lineup_display['away'];
$homeLabel = $home['region'].' — '.collect($home['players'])->pluck('name')->implode(' + ');
$awayLabel = $away['region'].' — '.collect($away['players'])->pluck('name')->implode(' + ');
$display = $fx->scheduled_at;
@endphp

@if($loop->first || $fixtures[$loop->index - 1]->draw_id !== $fx->draw_id || $fixtures[$loop->index - 1]->round_nr !== $fx->round_nr || $fixtures[$loop->index - 1]->tie_nr !== $fx->tie_nr)
<tr class="fixture-group">
  <td colspan="10" class="d-none d-md-table-cell">{{ $fx->draw?->drawName ?? 'Draw' }} <span class="mx-2 text-muted">/</span> Round {{ $fx->round_nr }} <span class="mx-2 text-muted">/</span> @include('backend.team-fixtures.partials.region-badge', ['lineup' => $home]) <span class="mx-1">vs</span> @include('backend.team-fixtures.partials.region-badge', ['lineup' => $away])</td>
  <td colspan="7" class="d-md-none">{{ $fx->draw?->drawName ?? 'Draw' }} / Round {{ $fx->round_nr }} / @include('backend.team-fixtures.partials.region-badge', ['lineup' => $home]) <span class="mx-1">vs</span> @include('backend.team-fixtures.partials.region-badge', ['lineup' => $away])</td>
</tr>
@endif
<tr id="row-{{ $fx->id }}">
<td class="fixture-secondary fixture-id">{{ $fx->id }}</td>
<td class="fixture-secondary">{{ optional($fx->draw)->drawName ?? '—' }}</td>
<td class="fixture-secondary">{{ $fx->round_nr }}</td>
<td>{{ $fx->home_rank_nr ?? ($isV2 ? ($fx->rubber_name ?? $fx->rubber_code ?? '—') : '—') }}</td>

<td class="home-cell {{ $homeClass }}">
@include('backend.team-fixtures.partials.side-badges', ['fixture' => $fx, 'side' => 'home'])
</td>

<td class="away-cell {{ $awayClass }}">
@include('backend.team-fixtures.partials.side-badges', ['fixture' => $fx, 'side' => 'away'])
</td>

<td id="result-col-{{ $fx->id }}">
@forelse($fx->fixtureResults as $r)
{{ $r->team1_score }}-{{ $r->team2_score }}@if(!$loop->last), @endif
@empty
<span class="badge bg-label-secondary">Awaiting score</span>
@endforelse
</td>

<td>
@if($display)
{{ \Carbon\Carbon::parse($display)->format('Y-m-d H:i') }}
@else — @endif
</td>

<td>{{ optional($fx->venue)->name ?? '—' }}</td>

<td class="text-end">
  <a href="javascript:void(0);"
     id="edit-btn-{{ $fx->id }}"
     class="btn btn-sm btn-outline-primary edit-score-btn"
     data-id="{{ $fx->id }}" data-participant-revision="{{ app(\App\Services\TeamParticipantHistoryService::class)->revision($fx) }}"
     data-action="{{ route('backend.team-fixtures.update', $fx->id) }}"
     data-home="{{ e($homeLabel) }}"
     data-away="{{ e($awayLabel) }}"
     @foreach($fx->fixtureResults as $r)
       data-set{{ $r->set_nr }}_home="{{ $r->team1_score }}"
       data-set{{ $r->set_nr }}_away="{{ $r->team2_score }}"
     @endforeach
  >
    Edit score
  </a>
</td>
</tr>

@empty
<tr><td colspan="10" class="text-center">No fixtures found.</td></tr>
@endforelse
</tbody>

</table>
</div>
<div class="px-3 pt-3">{{ $fixtures->links() }}</div>
</div>
</div>

  <!-- Edit Score Modal --> <div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">   <div class="modal-dialog modal-dialog-centered">     <div class="modal-content">       <form id="editScoreForm" method="POST" action="">         @csrf         @method('PUT')         <div class="modal-header">           <h5 class="modal-title">Edit Score</h5>           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>         </div>         <div class="modal-body">           <p><strong id="fixtureTeams"></strong></p>           <div class="table-responsive">             <table class="table table-sm align-middle">               <thead>                 <tr>                   <th>Set</th>                   <th>Home</th>                   <th>Away</th>                 </tr>               </thead>               <tbody>                 @for($i = 1; $i <= 3; $i++)                   <tr>                     <td>Set {{ $i }}</td>                     <td><input type="number" class="form-control" name="set{{ $i }}_home" id="set{{ $i }}Home" min="0"></td>                     <td><input type="number" class="form-control" name="set{{ $i }}_away" id="set{{ $i }}Away" min="0"></td>                   </tr>                 @endfor               </tbody>             </table>           </div>         </div>         <div class="modal-footer">           <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>           <button type="submit" class="btn btn-primary">Save</button>         </div>       </form>     </div>   </div> </div>  <!-- Edit Players Modal --> <div class="modal fade" id="editPlayersModal" tabindex="-1" aria-hidden="true">   <div class="modal-dialog modal-lg modal-dialog-centered">     <div class="modal-content">       <form id="editPlayersForm" method="POST" action="">         @csrf         @method('PUT')         <div class="modal-header">           <h5 class="modal-title">Edit Players</h5>           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>         </div>         <div class="modal-body">           <p><strong id="playersFixtureTeams"></strong></p>           <div class="row"> <div class="col-md-6">   <label class="form-label">Home Players</label>   <select class="form-select select2"            name="home_players[]"            id="homePlayers"            data-fixture-type="{{ $team_fixture->fixture_type ?? 'singles' }}"            multiple>     @foreach($allPlayers as $player)       <option value="{{ $player->id }}">{{ $player->full_name }}</option>     @endforeach   </select> </div>  <div class="col-md-6">   <label class="form-label">Away Players</label>   <select class="form-select select2"            name="away_players[]"            id="awayPlayers"            data-fixture-type="{{ $team_fixture->fixture_type ?? 'singles' }}"            multiple>     @foreach($allPlayers as $player)       <option value="{{ $player->id }}">{{ $player->full_name }}</option>     @endforeach   </select> </div>             </div>         </div>         <div class="modal-footer">           <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>           <button type="submit" class="btn btn-primary">Save Players</button>         </div>       </form>     </div>   </div> </div>



@endsection
