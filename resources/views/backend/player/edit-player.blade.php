@extends('layouts.backend')

@section('title', 'Player Profile')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}} " />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>

@endsection

@section('page-script')
<script src="{{asset('assets/js/edit-player.js')}}"></script>

@endsection

@section('content')
<div class="player-edit-admin">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h4 class="mb-1">Edit player</h4><p class="text-muted mb-0">{{ $player->name }} {{ $player->surname }} · Profile #{{ $player->id }}</p></div><a href="{{ route('backend.player.profile', $player->id) }}" class="btn btn-outline-secondary">Back to profile</a></div>
  @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Review the player details.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form action="{{ route('player.update', $player->id) }}" method="POST">
    @csrf @method('PATCH')
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">Identity</h5></div><div class="card-body"><div class="row g-3">
      <div class="col-md-6"><label for="player-name" class="form-label">Player name</label><input class="form-control" type="text" name="player_name" value="{{ old('player_name', $player->name) }}" id="player-name" required maxlength="255" autocomplete="given-name"></div>
      <div class="col-md-6"><label for="player-surname" class="form-label">Player surname</label><input class="form-control" type="text" name="player_surname" value="{{ old('player_surname', $player->surname) }}" id="player-surname" required maxlength="255" autocomplete="family-name"></div>
      <div class="col-md-6"><label for="player-dob" class="form-label">Date of birth</label><input class="form-control" type="date" name="dob" value="{{ old('dob', $player->dateOfBirth) }}" id="player-dob" autocomplete="bday"></div>
      <div class="col-md-6"><label for="player-gender" class="form-label">Gender</label>@php($savedGender = (string) old('gender', $player->gender))<select name="gender" id="player-gender" class="form-select">@if($savedGender !== '' && !in_array($savedGender, ['1','2'], true))<option value="{{ $savedGender }}" selected>{{ ucfirst($savedGender) }}</option>@endif<option value="">Not set</option><option value="1" @selected((string) old('gender', $player->gender) === '1')>Male</option><option value="2" @selected((string) old('gender', $player->gender) === '2')>Female</option></select></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">Contact details</h5></div><div class="card-body"><div class="row g-3">
      <div class="col-md-6"><label for="player-email" class="form-label">Email</label><input class="form-control" name="email" type="email" value="{{ old('email', $player->email) }}" id="player-email" maxlength="255" autocomplete="email"></div>
      <div class="col-md-6"><label for="player-cell" class="form-label">Cell number</label><input class="form-control" type="tel" name="cell_nr" value="{{ old('cell_nr', $player->cellNr) }}" id="player-cell" maxlength="50" autocomplete="tel"></div>
    </div></div></div>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-0">Coach and development</h5></div><div class="card-body">
      <div class="mb-3"><label for="player-coach" class="form-label">Player coach</label><input class="form-control" type="text" name="coach" value="{{ old('coach', $player->coach) }}" id="player-coach" maxlength="255"></div>
      @if(auth()->user()->hasAnyRole(['super-user', 'admin']))
      <div><label class="form-label" for="is-player-of-colour">Player of colour (POC)</label><select class="form-select" name="is_player_of_colour" id="is-player-of-colour"><option value="" @selected(old('is_player_of_colour', $player->is_player_of_colour) === null || old('is_player_of_colour', $player->is_player_of_colour) === '')>Not declared</option><option value="1" @selected((string) old('is_player_of_colour', $player->is_player_of_colour) === '1')>Yes</option><option value="0" @selected(old('is_player_of_colour', $player->is_player_of_colour) === false || (string) old('is_player_of_colour', $player->is_player_of_colour) === '0')>No</option></select><p class="small text-muted mb-0 mt-1">For transformation and player development planning.</p></div>
      @endif
    </div></div>
    <button type="submit" class="btn btn-primary">Save player details</button>
  </form>
</div>
<style>.player-edit-admin { max-width:960px; } .player-edit-admin :is(.btn,input,select) { min-height:44px !important; } .player-edit-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }</style>
@endsection
