@extends('layouts/contentNavbarLayout')
@section('title', 'Past-event player ratings')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-event-rating-history="v1">
    @include('frontend.my-tennis.profile-header', ['workspaceTab' => 'rating'])
    <div class="card"><div class="card-body">
        <h2 class="h5">Past-event ratings</h2>
        <p><a href="{{ route('my.tennis', ['player' => $ratingPlayer, 'tab' => 'rating']) }}">Back to rating</a></p>
        <p>See your estimated rating at the end of each event.</p>
        <details class="mb-3">
            <summary>How these estimates are worked out</summary>
            <p class="small text-muted mb-0 mt-2">These ratings were calculated later using eligible results from the {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }} days up to each event’s end. Each ball level has its own rating. Published final placings carry less weight than scored singles matches.</p>
        </details>
        @if(session('event_history_status'))
            <p class="alert alert-info" role="status">{{ session('event_history_status') }}</p>
        @endif
        @if($timeline['reason'])
            <p class="alert alert-info" role="status">{{ $timeline['reason'] }}</p>
        @else
            <p class="small text-muted">{{ $timeline['total'] }} published Cape Tennis events · saved through {{ $timeline['as_of'] }}.</p>
        @endif
        <form method="post" action="{{ route('my.tennis.event-history.refresh', $ratingPlayer) }}" class="mb-3">
            @csrf
            <button class="btn btn-outline-primary" style="min-height:44px" @disabled(in_array($refreshStatus['state'], ['pending', 'running'], true))>Update past-event history</button>
            <span class="small text-muted d-block mt-2" role="status">{{ $refreshStatus['message'] }}</span>
        </form>
        <style>
            .event-rating-levels{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
            .event-rating-card{min-width:0;overflow-wrap:anywhere;border:1px solid #d9e3e0;border-radius:.5rem;padding:1rem;margin-top:1rem}
            .event-rating-level{min-width:0;padding:.75rem;border-top:3px solid var(--ball-color);background:#f7faf9;border-radius:.3rem}
            @media(max-width:767px){.event-rating-levels{grid-template-columns:1fr}}
        </style>
        @forelse($timeline['events'] as $event)
            <article class="event-rating-card">
                <h2 class="h5">{{ $event['event'] }}</h2><p class="small text-muted">As of {{ $event['date'] }}</p>
                <p class="small">Recorded entry level: {{ collect($event['entry_stages'])->map(fn($stage) => \App\Services\Performance\BallRatingModel::LABELS[$stage])->implode(' · ') ?: 'Unclassified mixed level' }}. Entry is participation metadata, not completion of that level.</p>
                @unless($event['has_eligible_matches'])
                    <p class="small text-muted">No eligible scored singles were found for this event. Valid published final placings may contribute weaker rating evidence, but do not add to the played-event count.</p>
                @endunless
                @foreach($event['finishes'] as $finish)
                    <p class="small mb-1">Published finishing position: {{ $finish['position'] }} · {{ $finish['category'] }}. A valid complete singles field may contribute weaker finishing-order evidence.</p>
                @endforeach
                <div class="event-rating-levels">
                    @foreach(\App\Services\Performance\BallRatingModel::LABELS as $stage => $label)
                        @php($rating = $event['levels'][$stage])
                        <section class="event-rating-level" data-event-rating-stage="{{ $stage }}" data-rating-as-of="{{ $event['date'] }}" style="--ball-color:{{ ['red' => '#b92727', 'orange' => '#bd640b', 'standard' => '#14645a'][$stage] }}">
                            <h3 class="h6">{{ $label }}</h3>
                            <x-ctr-badge :stage="$stage" :score="$rating['score']" :local="$rating['local']" :title="'Reconstructed '.$label.' rating as of '.$event['date'].'. Retrospective estimate, not an original published rating.'" />
                            @if(($rating['evidence_basis'] ?? null) === 'finish_only')
                                <p class="small mb-1">Low confidence · final placings only</p>
                            @endif
                            @if($rating['score'] === null)
                                <p class="small mb-1">Not rated at this date.</p>
                            @endif
                            <p class="small mt-2 mb-1">{{ $rating['matches_in_event'] }} eligible singles matches at this event · {{ $rating['cape_events'] }} played Cape Tennis events at this level by this date.</p>
                            <p class="small text-muted mb-0">{{ $rating['reason'] }}</p>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            @unless($timeline['reason'])<p>No published completed events were found for this player.</p>@endunless
        @endforelse
        @if($timeline['pages'] > 1)
            <nav aria-label="Past-event history pages" class="d-flex flex-wrap gap-3 mt-3">
                @if($timeline['page'] > 1)<a href="{{ route('my.tennis.event-history', ['player' => $ratingPlayer, 'page' => $timeline['page'] - 1]) }}">Previous</a>@endif
                <span>Page {{ $timeline['page'] }} of {{ $timeline['pages'] }}</span>
                @if($timeline['page'] < $timeline['pages'])<a href="{{ route('my.tennis.event-history', ['player' => $ratingPlayer, 'page' => $timeline['page'] + 1]) }}">Next</a>@endif
            </nav>
        @endif
    </div></div>
</div>
@endsection
