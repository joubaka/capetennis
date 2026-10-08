<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $name }}</title>
  <style>
    body { margin:0; padding:24px; font-family:Arial,sans-serif; color:#172e45; background:#fff; }
    .print-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin-bottom:24px; }
    .print-toolbar button, .print-toolbar a { min-height:44px; padding:10px 18px; background:#172e45; color:white; border:0; border-radius:6px; font:inherit; cursor:pointer; box-sizing:border-box; text-decoration:none; display:inline-block; }
    .print-sheet { overflow-x:auto; }
    .print-day-form { display:flex; flex-wrap:wrap; align-items:end; gap:12px; margin-bottom:16px; }
    .print-day-form label { display:block; margin-bottom:4px; }
    .print-day-form select, .print-day-form button, .print-day-form a { min-height:44px; box-sizing:border-box; padding:10px 14px; font:inherit; }
    .print-day-form a { color:#172e45; border:1px solid #172e45; border-radius:6px; text-decoration:none; }
    .venue-print-section { margin-bottom:24px; }
    @media print { @page { size:A4 landscape; margin:7mm; } body { padding:0; } .print-toolbar, .print-day-form { display:none; } .print-sheet { overflow:visible; } thead { display:table-header-group; } tr { break-inside:avoid; } .venue-print-section + .venue-print-section { page-break-before:always; break-before:page; } }
  </style>
</head>
<body>
  @isset($age)
  <form class="print-day-form" method="get" action="{{ route('headoffice.venuePrintPack', $event) }}">
    <input type="hidden" name="age" value="{{ $age }}">
    <div><label for="age-venue-print-day">Day to print</label><select name="date" id="age-venue-print-day">
      <option value="" @selected(empty($selectedDate))>All days</option>
      @foreach($availableDays as $day)
      <option value="{{ $day }}" @selected($selectedDate === $day)>{{ \Carbon\Carbon::parse($day)->format('l j M Y') }}</option>
      @endforeach
      @if($selectedDate && !$availableDays->contains($selectedDate))
      <option value="{{ $selectedDate }}" selected>{{ \Carbon\Carbon::parse($selectedDate)->format('l j M Y') }} — no scheduled matches</option>
      @endif
    </select></div>
    <button type="submit">Show day</button>
    @if($selectedDate)<a href="{{ route('headoffice.venuePrintPack', ['event' => $event, 'age' => $age]) }}">All days</a>@endif
    <a href="{{ route('headoffice.printOptions', $event) }}">Back to print options</a>
  </form>
  @endisset
  @isset($draw)
  <form class="print-day-form" method="get" action="{{ route('fixture.create.pdf') }}">
    <input type="hidden" name="fixtures" value="{{ $draw->id }}"><input type="hidden" name="preview" value="1">
    <div><label for="team-print-day">Day to print</label><select name="date" id="team-print-day">
      <option value="" @selected(empty($selectedDate))>All days</option>
      @foreach($availableDays as $day)
      <option value="{{ $day }}" @selected($selectedDate === $day)>{{ \Carbon\Carbon::parse($day)->format('l j M Y') }}</option>
      @endforeach
      @if($selectedDate && !$availableDays->contains($selectedDate))
      <option value="{{ $selectedDate }}" selected>{{ \Carbon\Carbon::parse($selectedDate)->format('l j M Y') }} — no scheduled matches</option>
      @endif
    </select></div>
    <button type="submit">Show day</button>
    @if($selectedDate)<a href="{{ route('fixture.create.pdf', ['fixtures' => $draw->id, 'preview' => 1]) }}">All days</a>@endif
  </form>
  @endisset
  <div class="print-toolbar">
    <button type="button" onclick="window.print()">Print</button>
    @isset($age)
      <a href="{{ route('headoffice.venuePrintPack', ['event' => $event, 'age' => $age, 'date' => $selectedDate, 'download' => 1]) }}">Save as PDF</a>
    @elseisset($draw)
      <a href="{{ route('fixture.create.pdf', ['fixtures' => $draw->id, 'date' => $selectedDate]) }}">Save as PDF</a>
    @else
      <a href="{{ route('fixture.create.pdf.venue', ['fixtures' => $fixtures->pluck('id')->all()]) }}">Save as PDF</a>
    @endisset
  </div>
  <main class="print-sheet">
    @isset($venueSections)
      @forelse($venueSections as $section)
      <section class="venue-print-section" data-venue-id="{{ $section['venue']->id }}">
        @include('backend.draw.pdf.pdf-team', ['name' => $name.' · '.$section['venue']->name, 'fixtures' => $section['fixtures']])
      </section>
      @empty
      <h1>{{ $name }}</h1><p>No fixtures found for {{ $selectedDate ?: 'all days' }}.</p>
      @endforelse
    @else
      @include('backend.draw.pdf.pdf-team')
    @endisset
  </main>
</body>
</html>
