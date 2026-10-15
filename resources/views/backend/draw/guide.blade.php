@extends('layouts.backend')
@section('title', 'Set up draw – '.$draw->drawName)
@section('content')
<div class="container-xxl py-3" style="max-width:1000px" data-draw-guide>
  <a href="{{ route('headOffice.show', $event) }}" class="btn btn-link px-0 mb-2">← Back to event</a>
  <h1 class="h3">{{ $draw->drawName }}</h1>
  <p class="text-muted">Set up your {{ $guide['team'] ? 'team' : 'individual' }} draw. Your progress comes from the saved draw, so you can leave and continue later.</p>
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @if($draw->locked)<div class="alert alert-warning">This draw is locked. Review is available; use the existing workspace controls before changing protected setup.</div>@endif
  @foreach($guide['warnings'] as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
  <ol class="list-unstyled d-flex flex-wrap gap-2 mb-4" aria-label="Draw setup progress">
    @foreach($guide['steps'] as $index => $step)
      <li class="border rounded px-3 py-2 {{ $index === $guide['next'] ? 'border-primary' : '' }}" @if($index === $guide['next']) aria-current="step" @endif>
        <span class="small">{{ $index + 1 }}. {{ $step['label'] }}</span>
        <span class="d-block small {{ $step['complete'] ? 'text-success' : 'text-muted' }}">{{ $step['complete'] ? 'Complete' : ($index === $guide['next'] ? 'Continue here' : 'Still to do') }}</span>
      </li>
    @endforeach
  </ol>
  @php($current = $guide['steps'][$guide['next']])
  <section class="card card-body mb-3">
    <p class="small text-primary mb-1">Step {{ $guide['next'] + 1 }} of 6</p>
    <h2 class="h4">{{ $current['label'] }}</h2>
    <p>{{ $current['detail'] }}</p>
    @if($guide['next'] === 1 && !$guide['team'] && !$draw->category_event_id && !$draw->locked && !$draw->published && !$draw->oop_published)
      @if($categories->isEmpty())
        <p>No player categories are configured for this event yet.</p><a class="btn btn-primary align-self-start" href="{{ route('headOffice.show', $event) }}">Open event categories &amp; entries</a>
      @else
      <form method="POST" action="{{ route('draw.guide.category', [$event, $draw]) }}">@csrf
        <label for="guide-category" class="form-label">Player category</label>
        <select id="guide-category" name="category_event_id" class="form-select mb-3" required><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->category?->name }}</option>@endforeach</select>
        <button class="btn btn-primary">Save category &amp; continue</button>
      </form>
      @endif
    @elseif($guide['next'] === 3 && $guide['team'] && !$guide['generated'] && !$draw->locked && !$draw->published)
      <p>{{ $guide['participants'] }} teams are selected. Review the teams, format and pair choices before creating the ties.</p>
      <a href="{{ route('draws.manage', $draw) }}" class="btn btn-outline-primary align-self-start mb-3">Review teams &amp; format</a>
      <form method="POST" action="{{ route('draw.guide.generate-team', [$event, $draw]) }}">@csrf
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_generation" value="1" required><span class="form-check-label">I have reviewed the selected teams and want to generate their ties.</span></label>
        <button class="btn btn-primary">Generate team ties &amp; continue</button>
      </form>
    @else
      <a href="{{ $current['url'] }}" class="btn btn-primary align-self-start">{{ $guide['next'] === 5 ? 'Review publication controls' : 'Continue to '.$current['label'] }}</a>
    @endif
    <p class="small text-muted mt-3 mb-0">Saving setup does not publish changes. Review the current draw and schedule publication separately.</p>
  </section>
  <details class="card card-body"><summary>Review completed steps or open another step</summary><div class="d-grid gap-2 mt-3">@foreach($guide['steps'] as $index => $step)<a class="btn btn-outline-secondary text-start" href="{{ $step['url'] }}">{{ $index + 1 }}. {{ $step['label'] }}</a>@endforeach</div></details>
</div>
@endsection
