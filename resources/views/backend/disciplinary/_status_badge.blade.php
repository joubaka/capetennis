@php
    $suspended = $suspended ?? false;
    $activePoints = $activePoints ?? 0;
    $threshold = $threshold ?? 12;
@endphp

@if($suspended)
    <span class="badge bg-danger"><i class="ti ti-ban me-1"></i>Suspended</span>
@elseif($activePoints >= $threshold)
    <span class="badge bg-warning text-dark"><i class="ti ti-alert-triangle me-1"></i>Threshold Reached</span>
@elseif($activePoints > 0)
    <span class="badge bg-label-warning">{{ $activePoints }} pts</span>
@else
    <span class="badge bg-success"><i class="ti ti-check me-1"></i>Clear</span>
@endif
