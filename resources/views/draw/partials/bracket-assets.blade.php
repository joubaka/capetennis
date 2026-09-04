@once
  <link rel="stylesheet" href="{{ asset('css/tennis-bracket.css') }}?v={{ filemtime(public_path('css/tennis-bracket.css')) }}">
  <script defer src="{{ asset('js/tennis-bracket.js') }}?v={{ filemtime(public_path('js/tennis-bracket.js')) }}"></script>
@endonce
