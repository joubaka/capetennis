@extends('layouts.backend')

@section('title', 'Create Agreement')

@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body">
      <h4 class="mb-0">Create New Agreement</h4>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('backend.agreements.store') }}" method="POST">
        @csrf

        <div class="mb-3">
          <label for="admin-field-1" class="form-label">Title <span class="text-danger">*</span></label>
          <input id="admin-field-1" type="text" name="title" class="form-control" value="{{ old('title', 'Code of Conduct') }}" required>
        </div>

        <div class="mb-3">
          <label for="admin-field-2" class="form-label">Version <span class="text-danger">*</span></label>
          <input id="admin-field-2" type="text" name="version" class="form-control" value="{{ old('version', 'v1') }}" required placeholder="e.g. v1, v2">
        </div>

        <div class="mb-3">
          <label for="admin-field-3" class="form-label">Content (HTML) <span class="text-danger">*</span></label>
          <textarea id="admin-field-3" name="content" class="form-control" rows="15" required>{{ old('content') }}</textarea>
          <small class="text-muted">You may use HTML for formatting.</small>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-check"></i> Create Agreement
          </button>
          <a href="{{ route('backend.agreements.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div>

</div>
@endsection
