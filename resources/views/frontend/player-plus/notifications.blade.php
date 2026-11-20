@extends('layouts/contentNavbarLayout')
@section('title', 'Plus email preferences')
@section('content')
<div class="container py-4" style="max-width:720px">
  <h1 class="h3">Your Plus morning emails</h1>
  <p>One summary when there are new events, important public changes or unread announcements. No update means no email.</p>
  <p>These preferences apply to your account, across all your linked Plus players.</p>
  @if(session('status'))<p class="alert alert-success" role="status">{{ session('status') }}</p>@endif
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  @if(!$ready)<p class="alert alert-info">Email preferences will be available when this feature launches.</p>@endif
  @php($snoozed = $preferences->snoozed_until && \Illuminate\Support\Carbon::parse($preferences->snoozed_until)->isFuture())
  <p><strong>Current setting:</strong> {{ !$preferences->enabled ? 'Off' : ($snoozed ? 'Snoozed until '.\Illuminate\Support\Carbon::parse($preferences->snoozed_until)->timezone('Africa/Johannesburg')->format('d M Y, H:i').' South African time' : 'Daily, only when there are updates') }}</p>
  <form method="post" action="{{ route('player-plus.notifications.update') }}">
    @csrf
    @method('PATCH')
    <fieldset @disabled(!$ready)>
      <legend class="h5">Choose what works for you</legend>
      @foreach(['daily' => 'Receive morning updates / resume now', 'snooze7' => 'Snooze for 7 days', 'snooze30' => 'Snooze for 30 days', 'off' => 'Turn morning emails off'] as $value => $label)
        <label class="d-flex align-items-center gap-2 py-2" style="min-height:44px">
          <input type="radio" name="choice" value="{{ $value }}" @checked(old('choice', !$preferences->enabled ? 'off' : ($snoozed ? '' : 'daily')) === $value) required> {{ $label }}
        </label>
      @endforeach
      <button class="btn btn-primary my-3" style="min-height:44px">Save preference</button>
    </fieldset>
  </form>
  <p class="small text-muted">After resuming, your first morning check quietly establishes a fresh starting point. Later new updates can be emailed, without a catch-up backlog. Turning these emails off does not stop payment receipts, registration confirmations or essential event communications.</p>
  <a href="{{ route('my.tennis') }}">Back to My Tennis</a>
</div>
@endsection
