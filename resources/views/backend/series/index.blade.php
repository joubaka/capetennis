@extends('layouts/layoutMaster')

@section('title', 'Series')

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Series</h4>
    <a href="{{ route('series.create') }}" class="btn btn-primary">
      Create Series
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Events</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($series as $s)
            <tr>
              <td>
                <strong>{{ $s->name }}</strong>
              </td>
              <td>
                {{ $s->events_count }}
              </td>
              <td>
                <span class="badge bg-{{ $s->active ? 'success' : 'secondary' }}">
                  {{ $s->active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="text-end">
                <a href="{{ route('series.show', $s) }}"
                   class="btn btn-sm btn-outline-primary">
                  View
                </a>
                <a href="{{ route('series.events', $s) }}"
                   class="btn btn-sm btn-outline-secondary">
                  Events
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-3">
                No series created yet
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
