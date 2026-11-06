@once
@unless($liveResultsFragment ?? false)
  <script src="{{ asset('js/live-results.js') }}" defer></script>
@endunless
@endonce
