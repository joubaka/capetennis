@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'TeamSheet tennis results')
@section('content')
<div class="operational-page" data-teamsheet-tennis="verified-restricted-v1">
    <div class="card mb-3"><div class="card-body">
        <h1 class="h3">TeamSheet tennis results</h1>
        <p>Verified tennis singles results from TeamSheet ladders, leagues and tournaments can contribute to the shared player rating through a restricted read-only connection. Doubles, walkovers and disputed scores are excluded.</p>
        <p>Connect both player identities and verify an actual playing date. Scheduled dates and score-entry times need evidence. Possible duplicates are held during the rating calculation; a connected player and date alone do not guarantee inclusion.</p>
        <p>Results stop contributing after 24 hours without a successful sync. Updates to ratings run through the existing saved-rating workflow.</p>
        @if(session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        @if(!$ready)
            <p class="alert alert-warning">The reviewed TeamSheet import migration has not been applied in this environment.</p>
        @elseif(!$configured)
            <p class="alert alert-info">The TeamSheet restricted results connection has not been configured.</p>
        @else
            <p><strong>{{ $fresh ? 'Verified snapshot current' : 'Verified snapshot unavailable or stale' }}</strong> · Last successful sync: {{ $feed?->last_success_at ?? 'Never' }}</p>
            <form method="post" action="{{ route('backend.integrations.teamsheet.tennis.sync') }}">@csrf<button class="btn btn-primary" style="min-height:44px">Sync verified results</button></form>
        @endif
    </div></div>
    @if($ready)
        <div class="card mb-3"><div class="card-body">
            <h2 class="h4">Connect a player identity</h2>
            <p>Use identity evidence to connect an imported TeamSheet player ID to an existing Cape Tennis profile. Names alone are insufficient; this creates no account ownership.</p>
            <form method="post" action="{{ route('backend.integrations.teamsheet.tennis.link') }}" class="row g-3">@csrf
                <div class="col-sm-6"><label for="ts-player" class="form-label">TeamSheet player ID</label><input id="ts-player" name="teamsheet_player_id" type="number" min="1" required class="form-control" value="{{ old('teamsheet_player_id') }}"></div>
                <div class="col-sm-6"><label for="ct-player" class="form-label">Cape Tennis player ID</label><input id="ct-player" name="player_id" type="number" min="1" required class="form-control" value="{{ old('player_id') }}"></div>
                <div class="col-12"><label for="link-reason" class="form-label">Identity evidence</label><textarea id="link-reason" name="reason" minlength="10" maxlength="1000" required class="form-control">{{ old('reason') }}</textarea></div>
                <div class="col-12"><button class="btn btn-primary" style="min-height:44px">Connect reviewed identity</button></div>
            </form>
        </div></div>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h4">Reviewed player connections</h2>
            @forelse($links as $link)
                <details class="border rounded p-3 mb-2"><summary style="min-height:44px">TeamSheet #{{ $link->teamsheet_player_id }} → {{ $link->name }} {{ $link->surname }} (Cape Tennis #{{ $link->player_id }})</summary>
                    <p class="mt-2">{{ $link->reason }}</p>
                    <form method="post" action="{{ route('backend.integrations.teamsheet.tennis.unlink', $link->id) }}">@csrf @method('DELETE')
                        <label class="form-label" for="unlink-{{ $link->id }}">Reason for removing this connection</label><input id="unlink-{{ $link->id }}" name="reason" minlength="10" maxlength="1000" required class="form-control mb-2">
                        <button class="btn btn-outline-danger" style="min-height:44px">Remove connection</button>
                    </form>
                </details>
            @empty
                <p>No player connections yet.</p>
            @endforelse
            {{ $links->appends(request()->except('links_page'))->links() }}
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h4">Imported verified results</h2>
            @forelse($matches as $match)
                @php($state = $states[$match->id])
                <article class="border rounded p-3 mb-3" style="overflow-wrap:anywhere">
                    <h3 class="h6">{{ $match->source_match_id }}</h3>
                    <p>TeamSheet players #{{ $state['payload']['side1']['teamsheet_player_id'] }} / #{{ $state['payload']['side2']['teamsheet_player_id'] }} · {{ ucfirst($state['payload']['competition']['type']) }} #{{ $state['payload']['competition']['id'] }}</p>
                    <p>Score: {{ collect($state['payload']['sets'])->map(fn($set) => $set['side1_games'].'-'.$set['side2_games'])->implode(' ') }} · Source date: {{ substr($state['payload']['played_at'], 0, 10) }} ({{ $state['payload']['date_precision'] }})</p>
                    <p><strong>{{ !$fresh ? 'Held: source unavailable or stale' : ($state['reason'] ?? 'Ready for duplicate checks at the next rating update') }}</strong></p>
                    @if($state['date'])
                        <p>Actual playing date: {{ $state['date'] }}</p>
                    @endif
                    @if(!$match->retracted)
                        <details><summary style="min-height:44px">Review actual playing date</summary>
                            <form method="post" action="{{ route('backend.integrations.teamsheet.tennis.date', $match->id) }}" class="mt-2">@csrf
                                <input type="hidden" name="source_version" value="{{ $match->source_version }}">
                                <label for="date-{{ $match->id }}" class="form-label">Actual match date</label><input type="date" id="date-{{ $match->id }}" name="played_on" required max="{{ today()->toDateString() }}" class="form-control mb-2" value="{{ $state['date'] }}">
                                <label for="date-reason-{{ $match->id }}" class="form-label">Playing-date evidence</label><textarea id="date-reason-{{ $match->id }}" name="reason" minlength="10" maxlength="1000" required class="form-control mb-2"></textarea>
                                <button class="btn btn-outline-primary" style="min-height:44px">Save reviewed date</button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <p>No verified results have been imported.</p>
            @endforelse
            {{ $matches->appends(request()->except('page'))->links() }}
        </div></div>
    @endif
</div>
@endsection
