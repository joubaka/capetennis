<div class="masters-compact-list" aria-label="Provisional Top 12 within this comparison group">
<div class="masters-compact-row masters-compact-heading"><span>Pos.</span><span>Player</span><span>Rating</span></div>
@foreach($group['rows'] as $row)
<div class="masters-compact-row {{ $row['band'] || $row['boundary_tie'] ? 'masters-cutoff-row' : '' }}">
<span>{{ $row['position'] }}</span>
<div class="masters-compact-name"><strong>{{ $row['name'] }}</strong>
@if($row['boundary_tie'])<small class="d-block">Top 10 boundary tie — rule needed</small>@endif
@foreach($row['flags'] as $flag)
@if(str_contains($flag, 'Over the division'))<small class="d-block">Over-age under provisional rule</small>
@elseif(str_contains($flag, 'Younger player'))<small class="d-block">Younger — playing-up rule unconfirmed</small>
@elseif(str_contains($flag, 'Birth date') || str_contains($flag, 'Current player profile') || str_contains($flag, 'Profile gender'))<small class="d-block">{{ $flag }}</small>@endif
@endforeach
</div>
<strong class="masters-compact-score">{{ $group['converged'] && $row['score'] !== null ? number_format($row['score'], 1) : '—' }}</strong>
</div>
@endforeach
</div>
