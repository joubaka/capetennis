@php($spotsLeft = app(\App\Services\DivisionCapacityService::class)->remaining($division))
@if($spotsLeft !== null)
  <p class="small text-muted" role="status">{{ $spotsLeft === 0 ? 'Full — no new places available' : $spotsLeft.' '.\Illuminate\Support\Str::plural('spot', $spotsLeft).' left' }} · Maximum {{ $division->entry_limit }} players. Checkouts in progress hold places.</p>
@endif
