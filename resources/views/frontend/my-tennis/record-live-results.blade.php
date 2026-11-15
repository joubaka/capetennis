@php($recentMatches = collect($liveResults))
<section class="record-sheet record-live-results" aria-labelledby="live-results-heading" data-my-tennis-live-results="{{ route('my.tennis.record.live', $recordPlayer) }}" @if($liveResultsPrefetch ?? false) hidden @endif>
  <div class="record-section-heading">
    <h2 id="live-results-heading" tabindex="-1">Latest results</h2>
    <span class="record-note">Past seven days</span>
  </div>
  <p class="record-note mb-2">Recently posted or updated completed scores · separate from your saved season totals.</p>
  @forelse($recentMatches->take(3) as $match)
    @include('frontend.my-tennis.record-live-result-row')
  @empty
    <p class="mb-0">No completed scores have been posted for this player in the past seven days.</p>
  @endforelse
  @if($recentMatches->count() > 3)
    <details id="recent-results-more" class="recent-results-more" data-recent-results-more>
      <summary>Show all recent results <span class="record-note">({{ $recentMatches->count() - 3 }} more)</span></summary>
      @foreach($recentMatches->skip(3) as $match)
        @include('frontend.my-tennis.record-live-result-row')
      @endforeach
    </details>
  @endif
</section>
