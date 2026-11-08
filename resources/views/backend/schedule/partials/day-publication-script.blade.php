@once
  <style>
    [data-draw-day-publication] > summary { min-height: 44px; cursor: pointer; }
    [data-draw-day-publication] .btn:not([hidden]) { min-height: 44px; display: inline-flex; align-items: center; }
  </style>
  <script src="{{ asset('js/schedule-day-publication.js') }}?v={{ filemtime(public_path('js/schedule-day-publication.js')) }}" defer></script>
@endonce
