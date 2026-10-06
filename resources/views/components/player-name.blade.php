@props(['players', 'context' => null, 'separator' => ' / ', 'fallback' => 'TBD', 'svg' => false])
@forelse(collect($players) as $namedPlayer)
    @unless($loop->first){{ $separator }}@endunless{{ $namedPlayer->full_name }}<x-player-rating :player-id="$namedPlayer->id" :context="$context" :svg="$svg" />
@empty
    {{ $fallback }}
@endforelse
