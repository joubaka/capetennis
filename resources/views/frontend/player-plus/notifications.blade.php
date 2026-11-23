@extends('layouts/contentNavbarLayout')
@section('title', 'Plus email preferences')
@section('content')
<div class="container py-4" style="max-width:720px">
  <h1 class="h3">Your Plus update emails</h1>
  <p>Morning summaries cover new events and unseen updates. Entered-event alerts are queued after public draws, schedules, announcements or event information stay unchanged for ten minutes. No update means no email.</p>
  <p>These preferences apply to your account, across all your linked Plus players.</p>
  @if(session('status'))<p class="alert alert-success" role="status">{{ session('status') }}</p>@endif
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  @if(!$ready)<p class="alert alert-info">Email preferences will be available when this feature launches.</p>@endif
  @php($snoozed = $preferences->snoozed_until && \Illuminate\Support\Carbon::parse($preferences->snoozed_until)->isFuture())
  <p><strong>Current setting:</strong> {{ !$preferences->enabled ? 'Off' : ($snoozed ? 'Snoozed until '.\Illuminate\Support\Carbon::parse($preferences->snoozed_until)->timezone('Africa/Johannesburg')->format('d M Y, H:i').' South African time' : 'On, only when there are updates') }}</p>
  <form method="post" action="{{ route('player-plus.notifications.update') }}">
    @csrf
    @method('PATCH')
    <fieldset @disabled(!$ready)>
      <legend class="h5">Choose what works for you</legend>
      @foreach(['daily' => 'Receive Plus updates / resume now', 'snooze7' => 'Snooze for 7 days', 'snooze30' => 'Snooze for 30 days', 'off' => 'Turn Plus update emails off'] as $value => $label)
        <label class="d-flex align-items-center gap-2 py-2" style="min-height:44px">
          <input type="radio" name="choice" value="{{ $value }}" @checked(old('choice', !$preferences->enabled ? 'off' : ($snoozed ? '' : 'daily')) === $value) required> {{ $label }}
        </label>
      @endforeach
      <button class="btn btn-primary my-3" style="min-height:44px">Save preference</button>
    </fieldset>
  </form>
  <p class="small text-muted">After resuming, the next checks quietly establish fresh starting points for morning summaries and entered-event alerts. Later new updates can be emailed without a catch-up backlog. Your choice applies to your account address. Extra notification-contact copies are sent only when all currently linked accounts allow updates; these contacts do not gain account access. Turning Plus updates off does not stop payment receipts, registration confirmations or essential organiser communications.</p>
  <a href="{{ route('my.tennis') }}">Back to My Tennis</a>
</div>
@endsection
