@extends('layouts/layoutMaster')

@section('title', $series->name)

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-1">{{ $series->name }}</h3>
        <div class="text-muted">
          {{ $series->year }} • Best {{ $stats['best_of'] }} results
        </div>
      </div>

      <span class="badge {{ $stats['published'] ? 'bg-success' : 'bg-secondary' }}">
        {{ $stats['published'] ? 'Published' : 'Draft' }}
      </span>
    </div>
  </div>

  <div class="row g-3">

    {{-- RANKINGS --}}
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-trophy ti-md text-success"></i>
          <h5 class="mb-0">Rankings</h5>
        </div>

        <div class="card-body d-grid gap-2">
          <a href="{{ route('ranking.frontend.show', $series) }}"
             class="btn btn-success">
            View Rankings
          </a>

          <form method="POST" action="{{ route('ranking.calculate', $series) }}">
            @csrf
            <button class="btn btn-outline-success w-100">
              Recalculate Rankings
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- SERIES SETUP --}}
    <div class="col-xl-4 col-md-6">
      <div class="card h-100 border-start border-warning border-3">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-adjustments ti-md text-warning"></i>
          <h5 class="mb-0">Series Setup</h5>
        </div>

      <div class="card-body d-grid gap-2">

  <a href="{{ route('series.events', $series) }}"
     class="btn btn-outline-secondary">
    Manage Events
  </a>

  <a href="{{ route('series.settings', $series) }}"
     class="btn btn-outline-warning">
    Series Settings
  </a>

  <a href="{{ route('ranking.points.update', $series) }}"
     class="btn btn-outline-primary">
    Points Allocation
  </a>

  <a href="{{ route('ranking.series.list', $series) }}"
     class="btn btn-outline-info">
    Ranking Lists
  </a>

</div>

      </div>
    </div>

    {{-- QUICK STATS --}}
    <div class="col-xl-4 col-md-12">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-chart-bar ti-md text-info"></i>
          <h5 class="mb-0">Series Info</h5>
        </div>

        <div class="card-body">
          <ul class="list-unstyled mb-0 d-grid gap-1">
            <li>
              Events
              <span class="fw-semibold float-end">{{ $stats['events'] }}</span>
            </li>
            <li>
              Rank Type
              <span class="fw-semibold float-end">{{ $stats['rank_type'] }}</span>
            </li>
            <li>
              Best Results Counted
              <span class="fw-semibold float-end">{{ $stats['best_of'] }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

  </div>

  {{-- EVENTS --}}
  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0">Events in Series</h5>
    </div>

    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($series->events as $event)
            <tr>
              <td>{{ $event->name }}</td>
              <td>{{ optional($event->start_date)->format('d M Y') }}</td>
              <td class="text-end">
                <a href="{{ route('admin.events.overview', $event) }}"
                   class="btn btn-sm btn-outline-primary">
                  Open Event
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
