@extends('layouts/layoutMaster')

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
<script src="{{ asset(mix('js/draw-fixtures-show.js')) }}"></script>
@endsection

@section('content')

{{-- ========================= --}}
{{-- SAFE DEBUG HEADER (LOCAL) --}}
{{-- ========================= --}}
@if(app()->environment('local'))
<div class="alert alert-warning small mb-3">
    <strong>DEBUG MODE ACTIVE</strong><br>
    Fixtures count: {{ $fixtures->count() ?? 0 }} <br>
    Event ID: {{ $event->id ?? 'N/A' }}
</div>

@php
\Log::debug('[TeamFixtures] Page Loaded', [
    'event_id' => $event->id ?? null,
    'fixtures_count' => $fixtures->count() ?? 0
]);
@endphp
@endif


<style>
.winner-home { background-color: rgba(40,167,69,.25)!important; }
.loser-home { background-color: rgba(220,53,69,.25)!important; }
.draw-cell { background-color: rgba(255,193,7,.25)!important; }
</style>


<div class="container-xxl">
<div class="card">
<div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-0">

<thead class="table-light">
<tr>
<th>#</th>
<th>Draw</th>
<th>Round</th>
<th>Tie</th>
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

{{-- Fixture Debug --}}
@if(app()->environment('local'))
@php

\Log::debug('[TeamFixtures] Rendering Fixture', [
    'fixture_id' => $fx->id,
    'round' => $fx->round_nr,
    'tie' => $fx->tie_nr,
    'players_count' => $fx->fixturePlayers->count()
]);
\Log::debug('[TeamFixtures] Regions', [
    'fixture_id' => $fx->id,
    'region1' => optional($fx->region1Name)->short_name,
    'region2' => optional($fx->region2Name)->short_name,
]);
@endphp
@endif

@php
$homeClass = '';
$awayClass = '';
@endphp

@if($fx->fixtureResults->count())
@php $lastSet = $fx->fixtureResults->last(); @endphp
@if($lastSet->team1_score > $lastSet->team2_score)
@php $homeClass='winner-home'; $awayClass='loser-home'; @endphp
@elseif($lastSet->team2_score > $lastSet->team1_score)
@php $homeClass='loser-home'; $awayClass='winner-home'; @endphp
@else
@php $homeClass='draw-cell'; $awayClass='draw-cell'; @endphp
@endif
@endif


@php
$homeNames=[];
$awayNames=[];
$homeRegionShort = $fx->region1Name?->short_name ?? null;
$awayRegionShort = $fx->region2Name?->short_name ?? null;
@endphp


@foreach($fx->fixturePlayers as $fpRow)

{{-- Player Debug --}}
@if(app()->environment('local'))
@php
\Log::debug('[TeamFixtures] FixturePlayer Row', [
    'fixture_id'=>$fx->id,
    'team1_id'=>$fpRow->team1_id,
    'team1_no_profile_id'=>$fpRow->team1_no_profile_id,
    'team2_id'=>$fpRow->team2_id,
    'team2_no_profile_id'=>$fpRow->team2_no_profile_id
]);
@endphp
@endif

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
@endphp


<tr id="row-{{ $fx->id }}">
<td>{{ $fx->id }}</td>
<td>{{ optional($fx->draw)->drawName ?? '—' }}</td>
<td>{{ $fx->round_nr }}</td>
<td>{{ $fx->tie_nr }}</td>

<td class="home-cell {{ $homeClass }}">
({{ $fx->home_rank_nr }}) {{ $homeLabel }}
</td>

<td class="away-cell {{ $awayClass }}">
({{ $fx->away_rank_nr }}) {{ $awayLabel }}
</td>

<td id="result-col-{{ $fx->id }}">
@forelse($fx->fixtureResults as $r)
{{ $r->team1_score }}-{{ $r->team2_score }}@if(!$loop->last), @endif
@empty
<span class="text-muted">No result</span>
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
     data-id="{{ $fx->id }}"
     data-action="{{ route('backend.team-fixtures.update', $fx->id) }}"
     data-home="{{ e($homeLabel) }}"
     data-away="{{ e($awayLabel) }}"
     @foreach($fx->fixtureResults as $r)
       data-set{{ $r->set_nr }}_home="{{ $r->team1_score }}"
       data-set{{ $r->set_nr }}_away="{{ $r->team2_score }}"
     @endforeach
  >
    Edit
  </a>
</td>
</tr>

@empty
<tr><td colspan="10" class="text-center">No fixtures found.</td></tr>
@endforelse
</tbody>

</table>
</div>
</div>
</div>

  <!-- Edit Score Modal --> <div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">   <div class="modal-dialog modal-dialog-centered">     <div class="modal-content">       <form id="editScoreForm" method="POST" action="">         @csrf         @method('PUT')         <div class="modal-header">           <h5 class="modal-title">Edit Score</h5>           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>         </div>         <div class="modal-body">           <p><strong id="fixtureTeams"></strong></p>           <div class="table-responsive">             <table class="table table-sm align-middle">               <thead>                 <tr>                   <th>Set</th>                   <th>Home</th>                   <th>Away</th>                 </tr>               </thead>               <tbody>                 @for($i = 1; $i <= 3; $i++)                   <tr>                     <td>Set {{ $i }}</td>                     <td><input type="number" class="form-control" name="set{{ $i }}_home" id="set{{ $i }}Home" min="0"></td>                     <td><input type="number" class="form-control" name="set{{ $i }}_away" id="set{{ $i }}Away" min="0"></td>                   </tr>                 @endfor               </tbody>             </table>           </div>         </div>         <div class="modal-footer">           <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>           <button type="submit" class="btn btn-primary">Save</button>         </div>       </form>     </div>   </div> </div>  <!-- Edit Players Modal --> <div class="modal fade" id="editPlayersModal" tabindex="-1" aria-hidden="true">   <div class="modal-dialog modal-lg modal-dialog-centered">     <div class="modal-content">       <form id="editPlayersForm" method="POST" action="">         @csrf         @method('PUT')         <div class="modal-header">           <h5 class="modal-title">Edit Players</h5>           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>         </div>         <div class="modal-body">           <p><strong id="playersFixtureTeams"></strong></p>           <div class="row"> <div class="col-md-6">   <label class="form-label">Home Players</label>   <select class="form-select select2"            name="home_players[]"            id="homePlayers"            data-fixture-type="{{ $team_fixture->fixture_type ?? 'singles' }}"            multiple>     @foreach($allPlayers as $player)       <option value="{{ $player->id }}">{{ $player->full_name }}</option>     @endforeach   </select> </div>  <div class="col-md-6">   <label class="form-label">Away Players</label>   <select class="form-select select2"            name="away_players[]"            id="awayPlayers"            data-fixture-type="{{ $team_fixture->fixture_type ?? 'singles' }}"            multiple>     @foreach($allPlayers as $player)       <option value="{{ $player->id }}">{{ $player->full_name }}</option>     @endforeach   </select> </div>             </div>         </div>         <div class="modal-footer">           <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>           <button type="submit" class="btn btn-primary">Save Players</button>         </div>       </form>     </div>   </div> </div>



{{-- Browser Console Debug --}}
@if(app()->environment('local'))
<script>
console.group('📊 Blade Fixture Debug');
console.log('Fixtures Count:', {{ $fixtures->count() }});
console.log('Event:', @json($event ?? null));
console.log('First Fixture:', @json($fixtures->first()));
console.groupEnd();
</script>
@endif


@endsection
