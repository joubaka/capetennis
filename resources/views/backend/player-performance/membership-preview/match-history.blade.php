<section class="mt-3" aria-label="{{ $historyName }} match history">
    <h2>{{ $historyName }} · recorded singles</h2>
    <p class="mp-subtle">{{ $historyPage->total() }} verified saved-source matches in this recorded cohort, up to 20 per page. Scores below are from {{ $historyName }}'s side. This is not an all-career record.</p>
    @forelse($historyPage as $match)
    <article class="mp-card mb-2"><div class="mp-topline"><strong>{{ $match['date'] }}</strong><span class="mp-chip @if($match['won']) mp-chip-active @endif">{{ $match['won'] ? 'Won' : 'Lost' }}</span></div><h3 class="mt-2">vs @if($match['opponent_detail'])<a href="{{ route('backend.player-performance.membership-preview.detail', ['player' => $match['opponent_id'], 'cohort' => $selected]) }}">{{ $match['opponent'] }}</a>@else{{ $match['opponent'] }}@endif</h3><p class="mb-1"><strong>{{ $match['score'] }}</strong></p><p class="mp-subtle mb-1">{{ $match['event'] }} · {{ $match['date_basis'] }}</p><a class="mp-subtle" href="{{ route('event.tab.results', ['id' => $match['event_id']]) }}">Source event results</a><span class="mp-subtle"> · {{ $match['source_id'] }}</span></article>
    @empty<p class="mp-note">No verified direct singles results are available for this saved cohort.</p>@endforelse
    {{ $historyPage->links('pagination::bootstrap-5') }}
</section>
