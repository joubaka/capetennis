<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title }}</title>
  <link rel="stylesheet" href="{{ asset('css/flexible-monrad.css') }}?v={{ filemtime(public_path('css/flexible-monrad.css')) }}">
  @include('draw.partials.bracket-assets')
</head>
<body class="fm-surface">
<header class="fm-header">
  <div><span class="fm-eyebrow">CAPE TENNIS / DRAW BUILDER</span><h1>{{ $title }}</h1></div>
  <nav aria-label="Draw actions">
    @if($config['setupUrl'] ?? null)<a href="{{ $config['setupUrl'] }}">Draw format</a>@endif
    @if($config['backUrl'])<a href="{{ $config['backUrl'] }}">Manage draw</a>@endif
    <button id="fm-print" type="button">Print draw</button>
    @if(isset($draw) && $draw->oop_published)<a href="#fm-timetable">Schedule</a>@endif
    @if($config['publicUrl'])<a id="fm-public" href="{{ $config['publicUrl'] }}" target="_blank" rel="noopener">Public view</a>@endif
  </nav>
</header>
@include('backend.draw.partials.flexible-monrad-editor')
@if(isset($draw) && $draw->oop_published)
  <section class="fm-public-timetable"><h2>Schedule</h2><div id="fm-timetable"></div></section>
@endif
</body>
</html>
