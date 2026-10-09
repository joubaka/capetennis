@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Player ratings')
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')

<div class="card"><div class="card-body">
    <h1 class="h3">All players · performance pilot</h1>
    <p>Choose a player to calculate their private provisional score. Players without eligible results remain unrated.</p>
    <form method="get" class="mb-3">
        <label for="search" class="form-label">Player name</label>
        <input type="search" id="search" name="search" class="form-control" maxlength="100" value="{{ $search }}">
        <button class="btn btn-primary mt-2">Search players</button>
    </form>
    <p>{{ $players->total() }} players</p>
    <ul class="list-group mb-3">
        @forelse($players as $player)
            <li class="list-group-item"><a class="d-inline-flex align-items-center" style="min-height:44px" href="{{ route('backend.player-performance.show', $player->id) }}">{{ $player->name }} {{ $player->surname }}<x-player-rating :player-id="$player->id" /></a> <span class="text-muted">#{{ $player->id }}</span></li>
        @empty
            <li class="list-group-item">No matching players.</li>
        @endforelse
    </ul>
    {{ $players->links() }}
    <a href="{{ route('backend.player-performance.index') }}">Compare a manually selected cohort</a>
</div></div>
</div>
@endsection
