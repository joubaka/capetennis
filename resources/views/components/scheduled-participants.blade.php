@props(['row'])
@forelse(array_filter($row['participants'] ?? []) as $participantSide => $participantName)
    @unless($loop->first) / @endunless{{ $participantName }}
    @if(($row['fixture_kind'] ?? 'individual') === 'individual' && $participantSide < 2)
        <x-fixture-rating :fixture-id="$row['fixture_id']" :side="$participantSide + 1" :draw-id="$row['draw_id'] ?? null" />
    @endif
@empty
    Participants determined by draw
@endforelse
