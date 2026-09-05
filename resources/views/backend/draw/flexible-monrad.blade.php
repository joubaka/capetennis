<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title }}</title>
  <link rel="stylesheet" href="{{ asset('css/public-draw.css') }}?v={{ filemtime(public_path('css/public-draw.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/flexible-monrad.css') }}?v={{ filemtime(public_path('css/flexible-monrad.css')) }}">
  @include('draw.partials.bracket-assets')
</head>
<body class="fm-surface">
@if(isset($draw))
  <div class="fm-public-shell">
    @include('frontend.draw.partials.public-header', ['publicDrawPrintButtonId' => 'fm-print'])
    <nav class="ct-public-draw-nav" aria-label="Draw sections">
      <a class="is-active" href="#fm-board-title">Draw</a>
      @if($draw->oop_published)<a href="#fm-timetable">Match times</a>@endif
      <a href="#fm-results">Results</a>
    </nav>
  </div>
@else
  <header class="fm-header">
    <div><span class="fm-eyebrow">CAPE TENNIS / DRAW BUILDER</span><h1>{{ $title }}</h1></div>
    <nav aria-label="Draw actions"><button id="fm-print" type="button">Print draw</button></nav>
  </header>
@endif
@include('backend.draw.partials.flexible-monrad-editor')
@if(isset($draw) && $draw->oop_published)
  <section class="fm-public-timetable"><h2>Schedule</h2><div id="fm-timetable"></div></section>
@endif
</body>
</html>
