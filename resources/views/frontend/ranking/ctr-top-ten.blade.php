<section class="p-3 border-bottom" aria-labelledby="ctr-top-ten-title" data-ctr-top-ten>
  <h2 class="h5" id="ctr-top-ten-title">Top 10 CTR</h2>
  <p class="small text-muted">Cape Tennis Rating among this series' published players, per age group and gender. Birth years run from 1 January to 31 December. Provisional ratings do not change the official ranking.</p>
  @forelse($ctr['groups'] as $group)
    <details class="mb-2 border rounded p-2">
      <summary class="py-2">{{ $group['label'] }}</summary>
      <ol class="mb-1 ps-4">
        @foreach($group['players'] as $player)
          <li class="py-2" style="overflow-wrap:anywhere" data-ctr-top-player="{{ $player['id'] }}">
            <a href="{{ route('frontend.ranking.player-detail', [$series, $player['id']]) }}">{{ $player['name'] }}</a>
            <strong>{{ number_format($player['score'], 1) }}/100</strong> <span class="small text-muted">· {{ $player['confidence'] }} confidence</span>
          </li>
        @endforeach
      </ol>
    </details>
  @empty
    <p class="small mb-0">No comparable CTR ratings are available for this series yet.</p>
  @endforelse
  <p class="small text-muted mb-0">Only players connected to the shared rating scale appear. Within-group-only scores cannot establish a shared Top 10.</p>
</section>
