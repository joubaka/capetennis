@php
  $categoryFees = $event->eventCategories
    ->filter(fn ($categoryEvent) => $categoryEvent->category)
    ->map(fn ($categoryEvent) => [
      'category' => $categoryEvent->category->name,
      'fee' => (float) ($categoryEvent->entry_fee ?? $event->entryFee ?? 0),
    ])
    ->values();

  $distinctFees = $categoryFees
    ->pluck('fee')
    ->uniqueStrict()
    ->values();

  $hasCategorySpecificFees = $distinctFees->count() > 1;
  $singleEntryFee = $distinctFees->first() ?? (float) ($event->entryFee ?? 0);
@endphp

@if($hasCategorySpecificFees)
  <li class="mb-2">
    <strong>Entry fees:</strong>
    <ul class="list-unstyled ms-3 mt-2 mb-0">
      @foreach($categoryFees as $categoryFee)
        <li class="d-flex justify-content-between gap-3 mb-1">
          <span>{{ $categoryFee['category'] }}</span>
          <span class="badge bg-label-primary">R{{ number_format($categoryFee['fee'], 2) }}</span>
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
