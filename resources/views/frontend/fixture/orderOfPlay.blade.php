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
      size: A4 landscape;
      margin: 10mm;
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
      white-space: normal !important;
      overflow-wrap: anywhere;
      font-size: 10px !important;
      line-height: 1.3;
    }

    th {
      background: #000 !important;
      color: #fff !important;
      -webkit-print-color-adjust: exact;
    }

    /* Hide buttons for printing */
    .btn, .view-toggle, .mt-4, .text-end, .order-filter {
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
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    #order { margin-bottom: 0; }
    .table-responsive { overflow: visible; }
    #order h3 { font-size: 14px; }
  }
</style>

@php
  // 🔗 Build base URL for toggle buttons
  $baseRoute = url("event/{$event->id}/venue/{$venue->id}/order");

@endphp



<div class="container" id="order">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
      <h3 class="mb-1">Order of Play</h3>
      <strong>{{ $event->name }}</strong><br>
      <strong class="venue-title">{{ $venue->name }}</strong>
    </div>

    {{-- 🗓 Toggle Buttons --}}
    <div class="btn-group view-toggle" role="group">
      @foreach($availableDates as $availableDate)
        <a href="{{ $baseRoute }}/{{ $availableDate }}{{ $selectedDrawId ? '?draw_id='.$selectedDrawId : '' }}"
           class="btn btn-outline-primary {{ $date === $availableDate ? 'active' : '' }}">
          {{ \Carbon\Carbon::parse($availableDate)->format('D j M') }}
        </a>
      @endforeach
      <a href="{{ $baseRoute }}/all{{ $selectedDrawId ? '?draw_id='.$selectedDrawId : '' }}"
         class="btn btn-outline-dark {{ strtolower($date) === 'all' ? 'active' : '' }}">All Days</a>
    </div>
  </div>

  <form method="get" class="order-filter mb-3">
    <label for="order-draw" class="form-label">Age group / draw</label>
    <div class="d-flex gap-2">
      <select name="draw_id" id="order-draw" class="form-select">
        <option value="">All draws at this venue</option>
        @foreach($availableDraws as $availableDraw)
          <option value="{{ $availableDraw->id }}" @selected((int) $selectedDrawId === (int) $availableDraw->id)>{{ $availableDraw->drawName }}</option>
        @endforeach
      </select>
      <button type="submit" class="btn btn-outline-primary">Apply</button>
    </div>
  </form>

  {{-- 📅 Date Display --}}
  <div class="mb-3">
    @if(strtolower($date) === 'all')
      <h5 class="text-muted">Showing all published fixtures</h5>
    @else
      <h5 class="text-muted">
        {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}
      </h5>
    @endif
  </div>

  <div class="table-responsive"><table class="table table-bordered align-middle">
    <thead class="table-dark">
      <tr><th colspan="5">{{ $venue->name }} · {{ strtolower($date) === 'all' ? 'All published days' : \Carbon\Carbon::parse($date)->format('l, d M Y') }}</th></tr>
      <tr>
        <th style="width: 6%">Time</th>
        <th style="width: 16%">Draw / Match</th>
        <th style="width: 25%">Home</th>
        <th style="width: 25%">Away</th>
        <th style="width: 20%">Result</th>
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
            <td colspan="5">{{ $venue->name }} · {{ strtoupper($day) }}</td>
          </tr>

          @foreach($dayFixtures as $fx)
            <tr>
              <td>{{ \Carbon\Carbon::parse($fx->scheduled_at)->format('H:i') }}</td>
              <td>{{ $fx->draw->drawName }}<br><small>M{{ $fx->match_nr ?? $fx->id }}</small></td>
              <td>
                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])
              </td>
              <td>
                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])
              </td>
              <td class="fw-bold text-center">{{ $fx->result ?: '____  ____  ____' }}</td>
            </tr>
          @endforeach
        @endforeach
      @else
        {{-- 📅 Single-day view --}}
        @forelse($fixtures as $fx)
          <tr>
            <td>{{ \Carbon\Carbon::parse($fx->scheduled_at)->format('H:i') }}</td>
            <td>{{ $fx->draw->drawName }}<br><small>M{{ $fx->match_nr ?? $fx->id }}</small></td>
            <td>
              @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])
            </td>
            <td>
              @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])
            </td>
            <td class="fw-bold text-center">{{ $fx->result ?: '____  ____  ____' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted">No matches scheduled for this day.</td>
          </tr>
        @endforelse
      @endif
    </tbody>
  </table></div>

  <div class="mt-4 text-end">
    <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
  </div>
</div>
@endsection
