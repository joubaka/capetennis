{{-- Reusable: severity badge for a section header --}}
@php
  $hasCrit = collect($items)->where('status', 'critical')->count() > 0;
  $hasWarn = collect($items)->where('status', 'warn')->count() > 0;
  $badgeClass = $hasCrit ? 'badge-critical' : ($hasWarn ? 'badge-warn' : 'badge-ok');
  $badgeText  = $hasCrit ? 'CRITICAL' : ($hasWarn ? 'WARN' : 'OK');
@endphp
<span class="badge {{ $badgeClass }} ms-auto">{{ $badgeText }}</span>
