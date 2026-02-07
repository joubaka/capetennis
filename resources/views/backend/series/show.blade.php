@extends('layouts/layoutMaster')

@section('title', $series->name)

@section('content')
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ $series->name }}</h4>

    <a href="{{ route('admin.series.events', $series) }}"
       class="btn btn-primary">
      Manage Events
    </a>
  </div>

  <div class="row g-4">

    {{-- SERIES INFO --}}
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Series Info</h5>
        </div>
        <div class="card-body">
          <p class="mb-2">
            <strong>Status:</strong>
            <span class="badge bg-{{ $series->active ? 'success' : 'secondary' }}">
              {{ $series->active ? 'Active' : 'Inactive' }}
            </span>
          </p>

          @if($series->description)
            <p class="mb-0">
              <strong>Description</strong><br>
              {{ $series->description }}
            </p>
          @endif
        </div>
      </div>
    </div>

    {{-- QUICK STATS --}}
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Series Stats</h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li>
              Events
              <span class="fw-semibold float-end">
                {{ $series->events_count }}
              </span>
            </li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
