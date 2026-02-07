
@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Manage Players')

@section('vendor-style')

@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
@endsection

@section('vendor-script')
@endsection

@section('page-script')

<script src="{{asset('assets/js/draw-players.js')}}"></script>

@endsection



@section('content')

<div class="card">
  <div class="card-header event-header">
    <h3 class="text-center">Players {{ $draw->drawName }}</h3>
  </div>

  <div class="card-body px-4">
<div class="d-flex justify-content-between mb-3">
  <div>
    <button id="import-category" class="btn btn-secondary">Import From Category</button>
  </div>
  <div>
    <select id="player-select" class="form-select d-inline w-auto" style="min-width: 250px;">
      <option value="">Select Player to Add</option>
      @foreach($allPlayers as $player)
        <option value="{{ $player->id }}">{{ $player->name }}</option>
      @endforeach
    </select>
    <button id="add-player" class="btn btn-primary">Add Player</button>
  </div>
</div>

<table class="table table-bordered" id="draw-players-table">
  <thead>
    <tr>
      <th>#</th>
      <th>Name</th>
      <th>Team</th>
      <th>Category</th>
      <th>Action</th>
    </tr>
  </thead>
  <tbody>
    <!-- Populated via AJAX -->
  </tbody>
</table>




  </div>
</div>







@endsection
