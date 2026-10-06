@php
    $ageGroups = $registration->categoryEvents
        ->filter(fn ($categoryEvent) => $registeredEvent && $categoryEvent->event_id == $registeredEvent->id)
        ->map(fn ($categoryEvent) => $categoryEvent->category?->name)
        ->filter(fn ($name) => filled($name))
        ->unique()
        ->values();
@endphp
@forelse($ageGroups as $ageGroup)
    <span class="badge bg-label-primary">{{ $ageGroup }}</span>
@empty
    <span class="text-muted">Not recorded</span>
@endforelse
