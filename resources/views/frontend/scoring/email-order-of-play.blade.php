@extends('layouts/layoutMaster')
@section('title', 'Email order of play — '.$event->name)
@section('content')
<div class="container py-4" style="max-width: 980px">
  <a href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue, 'schedule_source' => $scope['source'], 'date' => $scope['date'], 'draw_ids' => $scope['draw_ids']]) }}" class="d-inline-block mb-3">← Back to scoring</a>
  <div class="card"><div class="card-body">
    <h1 class="h4">Email order of play</h1>
    <p class="mb-1"><strong>{{ $event->name }}</strong> · {{ $venue->name }}</p>
    <p class="mb-1">{{ $scope['date'] ? \Carbon\Carbon::parse($scope['date'])->format('l j F Y') : 'All saved days' }} · <strong>{{ count($rows) }} {{ Str::plural('match', count($rows)) }}</strong></p>
    <p class="small text-muted">Draws: {{ implode(', ', $drawNames) }}</p>
    <div class="alert {{ $scope['source'] === 'working' ? 'alert-warning' : 'alert-info' }}" role="status">
      {{ $scope['source'] === 'working' ? 'Working schedule — private staff copy. Sending this PDF does not publish the schedule.' : 'Published schedule — uses the saved public match times.' }}
      This PDF contains only the scheduled matches shown for this venue and the selected day and draws.
    </div>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($rows)
      <a class="btn btn-outline-primary mb-3" target="_blank" rel="noopener" href="{{ route('frontend.scoring.email.preview', ['event' => $event, 'venue' => $venue, ...$scope, 'review_token' => $reviewToken]) }}">Preview PDF attachment</a>
    @else
      <p class="alert alert-warning">There are no scheduled matches to email in this selection.</p>
    @endif
    <form method="POST" action="{{ route('frontend.scoring.email.send', [$event, $venue]) }}" id="email-order-of-play-form">
      @csrf
      <input type="hidden" name="source" value="{{ $scope['source'] }}">
      @if($scope['date'])<input type="hidden" name="date" value="{{ $scope['date'] }}">@endif
      @foreach($scope['draw_ids'] as $id)<input type="hidden" name="draw_ids[]" value="{{ $id }}">@endforeach
      <input type="hidden" name="request_key" value="{{ old('request_key', $requestKey) }}">
      <input type="hidden" name="review_token" value="{{ old('review_token', $reviewToken) }}">
      <label class="form-label" for="order-of-play-recipient">Recipient email</label>
      <input class="form-control mb-2" id="order-of-play-recipient" name="recipient" type="email" required maxlength="254" value="{{ old('recipient') }}" aria-describedby="order-of-play-recipient-help">
      <p class="small text-muted" id="order-of-play-recipient-help">Enter the convenor’s or another intended recipient’s email. Check the address and PDF before sending.</p>
      <button type="submit" class="btn btn-primary" @disabled(!$rows)>Send order of play</button>
    </form>
  </div></div>
</div>
@endsection
@section('page-script')
<script>
document.getElementById('email-order-of-play-form').addEventListener('submit', function () {
  const button = this.querySelector('button[type="submit"]');
  button.disabled = true;
  button.textContent = 'Queuing email…';
});
</script>
@endsection
