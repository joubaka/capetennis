<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $name }}</title>
<style>@page { margin:7mm; } .venue-print-section + .venue-print-section { page-break-before:always; }</style>
</head><body>
@isset($scheduleSource)<p>{{ $scheduleSource === 'published' ? 'Published schedule' : 'Working schedule' }}</p>@endisset
@isset($venueSections)
  @forelse($venueSections as $section)
    <section class="venue-print-section">
      @include('backend.draw.pdf.pdf-team', ['name' => $name.' - '.$section['venue']->name, 'fixtures' => $section['fixtures']])
    </section>
  @empty
    <h1>{{ $name }}</h1><p>No fixtures found for {{ $selectedDate ?: 'all days' }}.</p>
  @endforelse
@else
  @include('backend.draw.pdf.pdf-team')
@endisset
</body></html>
