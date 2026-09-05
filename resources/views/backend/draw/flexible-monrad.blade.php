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
    <nav class="ct-public-draw-nav" role="tablist" aria-label="Draw sections">
      <button id="fm-schedule-tab" type="button" class="nav-link active" role="tab" aria-selected="true"
              aria-controls="fm-schedule-panel" data-fm-public-tab="schedule">Match times</button>
      <button id="fm-draw-tab" type="button" class="nav-link" role="tab" aria-selected="false"
              aria-controls="fm-draw-panel" data-fm-public-tab="draw">Draw</button>
    </nav>
  </div>
  <section id="fm-schedule-panel" class="fm-public-panel fm-public-timetable" role="tabpanel" aria-labelledby="fm-schedule-tab">
    <h2>Match times &amp; courts</h2>
    <p class="fm-public-timetable-help">Find your name, then confirm the date, time, venue and court.</p>
    @if($draw->oop_published)
      <div id="fm-timetable"></div>
    @else
      <div class="fm-public-schedule-pending" role="status">
        <strong>Match times have not been published yet.</strong>
        <span>The draw is available now; return here after the organiser releases the schedule.</span>
      </div>
    @endif
  </section>
  <div id="fm-draw-panel" class="fm-public-panel" role="tabpanel" aria-labelledby="fm-draw-tab" hidden>
    @include('backend.draw.partials.flexible-monrad-editor')
  </div>
@else
  <header class="fm-header">
    <div><span class="fm-eyebrow">CAPE TENNIS / DRAW BUILDER</span><h1>{{ $title }}</h1></div>
    <nav aria-label="Draw actions"><button id="fm-print" type="button">Print draw</button></nav>
  </header>
  @include('backend.draw.partials.flexible-monrad-editor')
@endif
@if(isset($draw))
<script>
  (() => {
    const buttons = [...document.querySelectorAll('[data-fm-public-tab]')];
    const panels = {
      schedule: document.getElementById('fm-schedule-panel'),
      draw: document.getElementById('fm-draw-panel'),
    };

    function selectTab(name, updateHash = false) {
      if (!panels[name]) name = 'schedule';
      Object.entries(panels).forEach(([key, panel]) => { panel.hidden = key !== name; });
      buttons.forEach(button => {
        const active = button.dataset.fmPublicTab === name;
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', String(active));
      });
      if (updateHash) history.replaceState(null, '', name === 'draw' ? '#draw' : '#schedule');
    }

    buttons.forEach(button => button.addEventListener('click', () => selectTab(button.dataset.fmPublicTab, true)));
    selectTab(['#draw', '#fm-board-title', '#fm-results', '#groups'].includes(location.hash) ? 'draw' : 'schedule');
  })();
</script>
@endif
</body>
</html>
