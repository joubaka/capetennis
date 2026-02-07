@extends('layouts/layoutMaster')

@section('title', "Order of Play – {$venue->name} – {$date}")

@section('content')
<style>
  #order {
    margin-bottom: 150px;
  }

  .day-heading {
    background-color: #f5f5f5;
    font-weight: bold;
    text-transform: uppercase;
  }

  .view-toggle .btn {
    text-transform: uppercase;
    font-weight: 600;
  }

  .view-toggle .btn.active {
    pointer-events: none;
  }

  .table td, .table th {
    vertical-align: middle !important;
  }

  .table td.fw-bold {
    font-size: 1rem;
    letter-spacing: 0.5px;
  }

 
  @media print {
    @page {
      margin: 0.5cm;
    }

    body {
      font-size: 11px !important;
      color: #000;
    }

    table {
      width: 100%;
      border-collapse: collapse !important;
    }

    th, td {
      padding: 2px 4px !important;
      white-space: nowrap !important; /* single line */
      font-size: 10px !important;
      line-height: 2;
    }

    th {
      background: #000 !important;
      color: #fff !important;
      -webkit-print-color-adjust: exact;
    }

    /* Hide buttons for printing */
    .btn, .view-toggle, .mt-4, .text-end {
      display: none !important;
    }

    .table td, .table th {
      border: 1px solid #888 !important;
    }

    /* Smaller badges */
    .badge {
      font-size: 9px !important;
      padding: 2px 4px !important;
    }

    /* Compact day headings */
    .day-heading td {
      background: #f0f0f0 !important;
      font-weight: bold;
      font-size: 11px !important;
      text-transform: uppercase;
      text-align: center;
      -webkit-print-color-adjust: exact;
    }

    /* Wider result column for handwriting */
    td:last-child {
      min-width: 140px !important;
      text-align: left !important;
      border-bottom: 1px dotted #bbb !important;
    }
  }
</style>

@php
  // 🎨 Color palette for region badges
  $colorPalette = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];

  // 🧠 Global color map to ensure same region keeps color, and no duplicates until palette exhausted
  static $regionColorMap = [];
  static $colorIndex = 0;

  function regionBadge($region, $palette) {
      if (!$region) return '';

      $short = $region->short_name ?? $region->name ?? 'Unknown';
      $id = $region->id ?? crc32($short);

      // Access global variables
      global $regionColorMap, $colorIndex;

      // If this region doesn't have a color yet, assign next available color
      if (!isset($regionColorMap[$id])) {
          $regionColorMap[$id] = $palette[$colorIndex % count($palette)];
          $colorIndex++;
      }

      $color = $regionColorMap[$id];
      return '<span class="badge bg-' . $color . '">' . e($short) . '</span>';
  }

  // 🔗 Build base URL for toggle buttons
  $baseRoute = url("event/{$event->id}/venue/{$venue->id}/order");

  $today = now();
  $friday = $today->copy()->next('Friday')->format('Y-m-d');
  $saturday = $today->copy()->next('Saturday')->format('Y-m-d');
  $sunday = $today->copy()->next('Sunday')->format('Y-m-d');
@endphp



<div class="container" id="order">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
      <h3 class="mb-1">Order of Play</h3>
      <strong>{{ $event->name }}</strong><br>
      Venue: {{ $venue->name }}
    </div>

    {{-- 🗓 Toggle Buttons --}}
    <div class="btn-group view-toggle" role="group">
      <a href="{{ $baseRoute }}/{{ $friday }}"
         class="btn btn-outline-primary {{ $date == $friday ? 'active' : '' }}">Friday</a>
      <a href="{{ $baseRoute }}/{{ $saturday }}"
         class="btn btn-outline-primary {{ $date == $saturday ? 'active' : '' }}">Saturday</a>
      <a href="{{ $baseRoute }}/{{ $sunday }}"
         class="btn btn-outline-primary {{ $date == $sunday ? 'active' : '' }}">Sunday</a>
      <a href="{{ $baseRoute }}/all"
         class="btn btn-outline-dark {{ strtolower($date) === 'all' ? 'active' : '' }}">All Days</a>
    </div>
  </div>

  {{-- 📅 Date Display --}}
  <div class="mb-3">
    @if(strtolower($date) === 'all')
      <h5 class="text-muted">Showing all fixtures (Friday–Sunday)</h5>
    @else
      <h5 class="text-muted">
        {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}
      </h5>
    @endif
  </div>

  <table class="table table-bordered align-middle">
    <thead class="table-dark">
      <tr>
        <th style="width: 6%">Time</th>
        <th style="width: 18%">Draw</th>
        <th style="width: 25%">Home</th>
        <th style="width: 25%">Away</th>
        <th style="width: 26%">Result</th>
      </tr>
    </thead>

    <tbody>
      @if(strtolower($date) === 'all')
        {{-- 🗓 Grouped by Day --}}
        @php
          $grouped = $fixtures->groupBy(function ($fx) {
              return \Carbon\Carbon::parse($fx->scheduled_at)->format('l, d M Y');
          });
        @endphp

        @foreach($grouped as $day => $dayFixtures)
          <tr class="day-heading text-center">
            <td colspan="5">{{ strtoupper($day) }}</td>
          </tr>

          @foreach($dayFixtures as $fx)
            <tr>
              <td>{{ \Carbon\Carbon::parse($fx->scheduled_at)->format('H:i') }}</td>
              <td>{{ $fx->draw->drawName }}</td>
              <td>
                ({{ $fx->home_rank_nr }})
                {{ $fx->team1->pluck('full_name')->implode(' + ') ?: 'TBD' }}
                {!! regionBadge($fx->region1Name, $colorPalette) !!}
              </td>
              <td>
                ({{ $fx->away_rank_nr }})
                {{ $fx->team2->pluck('full_name')->implode(' + ') ?: 'TBD' }}
                {!! regionBadge($fx->region2Name, $colorPalette) !!}
              </td>
              <td class="fw-bold text-center">{{ $fx->result ?? '' }}</td>
            </tr>
          @endforeach
        @endforeach
      @else
        {{-- 📅 Single-day view --}}
        @forelse($fixtures as $fx)
          <tr>
            <td>{{ \Carbon\Carbon::parse($fx->scheduled_at)->format('H:i') }}</td>
            <td>{{ $fx->draw->drawName }}</td>
            <td>
              ({{ $fx->home_rank_nr }})
              {{ $fx->team1->pluck('full_name')->implode(' + ') ?: 'TBD' }}
              {!! regionBadge($fx->region1Name, $colorPalette) !!}
            </td>
            <td>
              ({{ $fx->away_rank_nr }})
              {{ $fx->team2->pluck('full_name')->implode(' + ') ?: 'TBD' }}
              {!! regionBadge($fx->region2Name, $colorPalette) !!}
            </td>
            <td class="fw-bold text-center">{{ $fx->result ?? '' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted">No matches scheduled for this day.</td>
          </tr>
        @endforelse
      @endif
    </tbody>
  </table>

  <div class="mt-4 text-end">
    <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
  </div>
</div>
@endsection
