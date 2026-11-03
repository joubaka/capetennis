@php
  // Team checkout is priced from the event fee (plus any region fee), not the
  // category rows used to organise imported team selections.
  $categoryFees = $event->isTeam()
    ? collect()
    : $event->eventCategories
      ->filter(fn ($categoryEvent) => $categoryEvent->category)
      ->map(fn ($categoryEvent) => [
        'category' => $categoryEvent->category->name,
        'fee' => (float) ($categoryEvent->entry_fee ?? $event->entryFee ?? 0),
        'remaining' => $categoryEvent->entry_limit === null ? null : app(\App\Services\DivisionCapacityService::class)->remaining($categoryEvent),
      ])
      ->values();

  $distinctFees = $categoryFees
    ->pluck('fee')
    ->uniqueStrict()
    ->values();

  $hasCategorySpecificFees = $distinctFees->count() > 1 || $categoryFees->contains(fn ($row) => $row['remaining'] !== null);
  $singleEntryFee = $distinctFees->first() ?? (float) ($event->entryFee ?? 0);
@endphp

@if($hasCategorySpecificFees)
  <li class="mb-2">
    <strong>Entry fees:</strong>
    <ul class="list-unstyled ms-3 mt-2 mb-0">
      @foreach($categoryFees as $categoryFee)
        <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <span>{{ $categoryFee['category'] }}</span>
          <span class="d-inline-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-label-primary">R{{ number_format($categoryFee['fee'], 2) }}</span>
            @if($categoryFee['remaining'] !== null)
              <span class="badge {{ $categoryFee['remaining'] === 0 ? 'bg-label-warning' : 'bg-label-secondary' }}">
                {{ $categoryFee['remaining'] === 0 ? 'Full' : $categoryFee['remaining'].' '.\Illuminate\Support\Str::plural('spot', $categoryFee['remaining']).' left' }}
              </span>
            @endif
          </span>
        </li>
      @endforeach
    </ul>
  </li>
@else
  <li class="mb-2">
    <strong>Entry fee:</strong>
    <span class="badge bg-label-primary">R{{ number_format($singleEntryFee, 2) }}</span>
  </li>
@endif
