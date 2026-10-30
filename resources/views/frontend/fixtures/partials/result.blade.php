@forelse($fixture->fixtureResults as $r)
    {{ $r->registration1_score ?? $r->team1_score }}-{{ $r->registration2_score ?? $r->team2_score }}@if(!$loop->last), @endif
@empty
    <span class="text-muted">{{ $fixture instanceof \App\Models\Fixture && $fixture->isWalkover() ? 'W/O' : 'No result' }}</span>
@endforelse
@if($fixture instanceof \App\Models\Fixture && $fixture->isRetired()) RET @endif
