@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Results')

@section('content')
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Results</h4>
        <div class="text-muted">{{ $event->name }}</div>
      </div>
      <a href="{{ route('events.show', $event->id) }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i> Back to Event
      </a>
    </div>
  </div>

  <div class="row">
    @forelse($categories as $category)
      @php $results = $categoryResults->get($category->category_id, collect()); @endphp
      @if($results->count() > 0)
        <div class="col-xl-3 col-md-4 mb-4">
          <div class="card h-100">
            <div class="card-header">
              <h5 class="mb-0">{{ $category->category->name }}</h5>
            </div>
            <div class="card-body">
              <ul class="list-group list-group-flush">
                @foreach($results as $result)
                  <li class="list-group-item d-flex align-items-center gap-2 py-2">
                    <span class="badge bg-label-success rounded p-2">{{ $result->position }}</span>
                    <span>{{ $result->registration->display_name }}</span>
                  </li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>
      @endif
    @empty
      <div class="col-12">
        <div class="alert alert-warning">No categories found for this event.</div>
      </div>
    @endforelse
  </div>

</div>
@endsection
