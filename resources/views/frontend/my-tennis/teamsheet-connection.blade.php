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
      <p class="text-muted mb-0">TeamSheet profile linking will appear here when the secure connection is available.</p>
    @elseif($connection['linked'])
      <p>Connected. Verified TeamSheet singles results can contribute to {{ $selectedPlayer->full_name }}’s rating after the normal match-date checks.</p>
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
      <p>If {{ $selectedPlayer->full_name }} has a TeamSheet profile, sign in there and select the same player. You must be that player or their linked parent on both sites.</p>
      <form method="post" action="{{ route('my.tennis.teamsheet.start', $selectedPlayer) }}">
        @csrf
        <button class="btn btn-primary" type="submit">Link TeamSheet</button>
      </form>
    @endif
  </div>
</section>
