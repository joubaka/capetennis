@extends('layouts/layoutMaster')

@section('title', 'Edit Agreement')

@section('content')
<div class="container-xl">

  <div class="card mb-4">
    <div class="card-body">
      <h4 class="mb-0">Edit Agreement – {{ $agreement->title }} ({{ $agreement->version }})</h4>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('backend.agreements.update', $agreement) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-3">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input type="text" name="title" class="form-control" value="{{ old('title', $agreement->title) }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Version <span class="text-danger">*</span></label>
          <input type="text" name="version" class="form-control" value="{{ old('version', $agreement->version) }}" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Content (HTML) <span class="text-danger">*</span></label>
          <textarea name="content" class="form-control" rows="15" required>{{ old('content', $agreement->content) }}</textarea>
          <small class="text-muted">You may use HTML for formatting.</small>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-check"></i> Update Agreement
          </button>
          <a href="{{ route('backend.agreements.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection
