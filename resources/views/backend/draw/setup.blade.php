@extends('layouts.backend')
@section('title', 'Choose draw format — '.$draw->drawName)
@section('content')
@include('backend.draw.partials.workspace-header', ['workspaceContext' => 'settings'])
@include('backend.draw.partials.workspace-links', ['workspaceTab' => 'settings'])
<div class="mx-auto" style="max-width:900px">
  <p class="text-primary mb-1">Step 1 · Draw format</p>
  <h3 class="mb-2">How should this draw start?</h3>
  <p class="text-muted mb-4">{{ $draw->drawName }} · Choose the format first. Player placement and format-specific settings come next.</p>
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ route('draw.setup.store', $draw) }}">
    @csrf
    <fieldset @disabled($draw->locked || $draw->published)>
      <legend class="visually-hidden">Draw format</legend>
      <div class="row g-3 mb-4">
        @foreach($options as $value => [$label, $description])
          <div class="col-12 col-md-6">
            <label class="card h-100 p-4 d-flex flex-row gap-3" style="cursor:pointer">
              <input type="radio" name="workflow" value="{{ $value }}" class="form-check-input flex-shrink-0" required
                @checked(old('workflow', $draw->settings?->workflow) === $value)>
              <span><strong class="d-block mb-2">{{ $label }}</strong><span class="text-muted">{{ $description }}</span></span>
            </label>
          </div>
        @endforeach
      </div>
      <button class="btn btn-primary" type="submit">Continue to setup →</button>
    </fieldset>
  </form>
  <p class="text-muted small mt-3">Existing assignments and fixtures are protected. Changing format requires an empty draw.</p>
</div>
@endsection
