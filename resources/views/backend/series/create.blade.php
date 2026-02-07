@extends('layouts/layoutMaster')

@section('title', 'Create Series')

@section('content')
<div class="container-xl">

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Create Series</h5>
    </div>

    <div class="card-body">
      <form method="POST" action="{{ route('admin.series.store') }}">
        @csrf

        <div class="mb-3">
          <label class="form-label">Series Name</label>
          <input type="text"
                 name="name"
                 class="form-control"
                 required>
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description"
                    class="form-control"
                    rows="3"></textarea>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input"
                 type="checkbox"
                 name="active"
                 value="1"
                 checked>
          <label class="form-check-label">
            Active
          </label>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-primary">
            Save Series
          </button>
          <a href="{{ route('admin.series.index') }}"
             class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>

      </form>
    </div>
  </div>

</div>
@endsection
