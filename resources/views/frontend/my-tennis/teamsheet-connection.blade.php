@php($connection = app(\App\Services\Performance\TeamSheetProfileLinkService::class)->status(auth()->user(), $selectedPlayer))
<section class="card mb-4 ct-teamsheet-profile" aria-labelledby="teamsheet-connection-title" data-teamsheet-profile-link="v1">
  <div class="card-body">
    <h3 id="teamsheet-connection-title" class="h5 ct-teamsheet-profile-brand">
      <img src="{{ asset('assets/img/logos/teamsheet-ts.png') }}" width="56" height="56" alt="">
      <span><strong>Team<span class="ct-teamsheet-profile-accent">Sheet</span></strong> profile</span>
    </h3>
    <p class="mb-0">Once your school signs up for TeamSheet, you can link your player profile and see your tennis ladder, school tournament and league match results as they’re published.</p>
    @if(session('teamsheet_success'))
      <p class="alert alert-success" role="status">{{ session('teamsheet_success') }}</p>
    @endif
    @error('teamsheet')
      <p class="alert alert-danger" role="alert">{{ $message }}</p>
    @enderror
    @if($connection['available'] && $connection['linked'])
      <p class="mt-3">Connected · Verified singles results can count toward the player’s rating.</p>
      @if($connection['can_unlink'])
        <form method="post" action="{{ route('my.tennis.teamsheet.unlink', $selectedPlayer) }}">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-secondary" type="submit">Disconnect TeamSheet</button>
        </form>
      @else
        <p class="text-muted mb-0">Contact Cape Tennis support if this connection needs changing.</p>
      @endif
    @elseif($connection['available'])
      <form class="mt-3" method="post" action="{{ route('my.tennis.teamsheet.start', $selectedPlayer) }}">
        @csrf
        <p class="small text-muted">Sign in to TeamSheet and select the same player. You must be the player or their linked parent on both sites.</p>
        <button class="btn ct-teamsheet-profile-primary" type="submit">Link TeamSheet</button>
      </form>
    @endif
    <a class="btn ct-teamsheet-profile-link mt-3" href="https://www.teamsheet.co.za/" referrerpolicy="no-referrer">Open TeamSheet</a>
  </div>
</section>
