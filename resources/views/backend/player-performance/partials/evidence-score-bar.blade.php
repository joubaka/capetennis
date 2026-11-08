<li class="list-group-item {{ $currentPlayer ? 'bg-light' : '' }}">
    <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
        @if($profileUrl)
            <a class="text-break" href="{{ $profileUrl }}">{{ $name }}</a>
        @else
            <strong class="text-break">{{ $name }} <span class="small fw-normal">(this player)</span></strong>
        @endif
        <span class="fw-semibold text-nowrap">{{ $score === null ? 'Withheld' : number_format($score, 1).'/100' }}</span>
    </div>
    @if($score !== null)
        <div class="progress" style="height:.5rem" aria-hidden="true">
            <div class="progress-bar {{ $currentPlayer ? 'bg-primary' : 'bg-info' }}" style="width:{{ max(0, min(100, (float) $score)) }}%"></div>
        </div>
    @endif
</li>
