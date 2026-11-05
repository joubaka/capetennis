@php($connection = app(\App\Services\Performance\TeamSheetProfileLinkService::class)->status(auth()->user(), $selectedPlayer))
<section class="card mb-4" aria-labelledby="teamsheet-connection-title" data-teamsheet-profile-link="v1">
  <div class="card-body">
    <h3 id="teamsheet-connection-title" class="h5">TeamSheet profile</h3>
    @if(session('teamsheet_success'))
      <p class="alert alert-success" role="status">{{ session('teamsheet_success') }}</p>
    @endif
    @error('teamsheet')
      <p class="alert alert-danger" role="alert">{{ $message }}</p>
    @enderror
    @if(!$connection['available'])
      <p class="text-muted mb-0">Profile linking is currently unavailable. You can still sign in to TeamSheet to access your profile there.</p>
    @elseif($connection['linked'])
      <p>Connected · Verified singles results can count toward the player’s rating.</p>
      @if($connection['can_unlink'])
        <form method="post" action="{{ route('my.tennis.teamsheet.unlink', $selectedPlayer) }}">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-secondary" type="submit">Disconnect TeamSheet</button>
        </form>
      @else
        <p class="text-muted mb-0">Contact Cape Tennis support if this connection needs changing.</p>
      @endif
    @else
      <form method="post" action="{{ route('my.tennis.teamsheet.start', $selectedPlayer) }}">
        @csrf
        <p class="small text-muted">Sign in to TeamSheet and select the same player. You must be the player or their linked parent on both sites.</p>
        <button class="btn btn-primary" type="submit">Link TeamSheet</button>
      </form>
    @endif
    <a class="btn btn-outline-primary mt-3" href="https://www.teamsheet.co.za/" referrerpolicy="no-referrer">Open TeamSheet</a>
  </div>
</section>
