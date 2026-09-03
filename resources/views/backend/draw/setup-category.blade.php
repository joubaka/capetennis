@extends('layouts/contentNavbarLayout')
@section('title', 'Choose players category — '.$draw->drawName)
@section('content')
<div class="mx-auto" style="max-width:650px">
  <a href="{{ route('draw.setup.show', $draw) }}" class="btn btn-sm btn-outline-secondary mb-4">Back to draw format</a>
  <p class="text-primary mb-1">{{ $label }}</p>
  <h3>Which category will play?</h3>
  <p class="text-muted">Choose a category from this event, then place its players in the bracket.</p>
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('draw.setup.store', $draw) }}">
    @csrf
    <input type="hidden" name="workflow" value="{{ $workflow }}">
    <label for="setup-category" class="form-label">Player category</label>
    <select id="setup-category" name="category_event_id" class="form-select mb-3" required>
      <option value="">Choose a category</option>
      @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->category?->name ?? 'Category #'.$category->id }}</option>@endforeach
    </select>
    @if($categories->isEmpty())<p class="alert alert-warning">Add a player category to the event first.</p>@endif
    <button class="btn btn-primary" @disabled($categories->isEmpty())>Continue to player placement →</button>
  </form>
</div>
@endsection
