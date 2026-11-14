@can('progress', $draw)
  @php
    $oddTailReview = null;
    try { $oddTailReview = app(\App\Services\Draw\OddPlayerPlayoffService::class)->review($draw, (int) $draw->event_id); }
    catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { /* Other formats keep their normal setup. */ }
  @endphp
  @if($oddTailReview)
    <section class="border rounded p-3 my-3" aria-labelledby="odd-tail-title">
      <h6 id="odd-tail-title">Everyone plays a playoff · odd player count</h6>
      <p>The last-ranked player (#{{ $oddTailReview['players'] }}) plays #{{ $oddTailReview['players'] - 1 }}. The winner plays #{{ $oddTailReview['players'] - 2 }}; the preliminary loser finishes {{ $oddTailReview['players'] }}th. Other position pairs stay the same.</p>
      @if($oddTailReview['already_applied'])
        <p class="mb-0 text-success">Enabled. Schedule the preliminary before its final.</p>
      @else
        <form method="POST" action="{{ route('backend.draw.odd-player-playoff', $draw) }}">
          @csrf
          <input type="hidden" name="event_id" value="{{ $draw->event_id }}">
          <input type="hidden" name="snapshot" value="{{ $oddTailReview['snapshot'] }}">
          <label class="d-block mb-2"><input type="checkbox" name="confirmed" value="1" required> Use this option: add one preliminary and retain the existing final and saved times.</label>
          <button class="btn btn-outline-primary" type="submit">Enable odd-player playoff</button>
        </form>
      @endif
    </section>
  @endif
@endcan
