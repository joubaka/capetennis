@props(['outcome' => ''])
@if($outcome)<span class="result-label">{{ $outcome === 'winner-home' ? 'Won' : 'Lost' }}</span>@endif
