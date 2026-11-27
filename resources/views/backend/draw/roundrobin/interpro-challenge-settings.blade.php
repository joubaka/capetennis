@if(\App\Services\Draw\InterproRoundRobinFormat::selected($draw))
@can('appendInterproChallenges',$draw)
@php
  $existingChallengePairs=\App\Services\Draw\InterproRoundRobinFormat::challengePairs($draw);
@endphp
<div class="card mb-3" id="interpro-challenge-settings">
  <div class="card-header"><h5 class="mb-0">Interpro challenge matches</h5></div>
  <div class="card-body">
    <p>Choose challenges before confirming the qualifying groups; the matches are played after the base playoffs. Existing matches, scores, scheduled times and publication are retained. Existing challenge choices cannot be removed here.</p>
    <form method="POST" action="{{ route('draw.interpro-challenges.append',$draw) }}">
      @csrf
      <input type="hidden" name="revision" value="{{ app(\App\Services\Draw\InterproChallengeSelectionService::class)->revision($draw) }}">
      <div class="d-flex flex-wrap gap-3 mb-3">
      @foreach([2,4,6,8,10] as $upper)
        <label class="form-check mb-0">
          @if(in_array($upper,$existingChallengePairs,true))<input type="hidden" name="challenge_pairs[]" value="{{ $upper }}">@endif
          <input class="form-check-input" type="checkbox" name="challenge_pairs[]" value="{{ $upper }}"
            @checked(in_array($upper,$existingChallengePairs,true)) @disabled(in_array($upper,$existingChallengePairs,true))>
          <span class="form-check-label">{{ $upper }} v {{ $upper+1 }}</span>
        </label>
      @endforeach
      </div>
      @error('challenge_pairs')<p class="text-danger">{{ $message }}</p>@enderror
      <button class="btn btn-outline-primary" type="submit">Add selected challenges</button>
      <p class="small text-muted mt-2 mb-0">New matches have no booking. Schedule them after every base playoff has finished, then review and publish their schedule separately.</p>
    </form>
  </div>
</div>
@endcan
@endif
