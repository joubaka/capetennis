<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title }}</title>
  <link rel="stylesheet" href="{{ asset('css/flexible-monrad.css') }}?v={{ filemtime(public_path('css/flexible-monrad.css')) }}">
</head>
<body>
<header class="fm-header">
  <div><span class="fm-eyebrow">CAPE TENNIS / DRAW BUILDER</span><h1>{{ $title }}</h1></div>
  <nav aria-label="Draw actions">
    @if($config['setupUrl'] ?? null)<a href="{{ $config['setupUrl'] }}">Draw format</a>@endif
    @if($config['backUrl'])<a href="{{ $config['backUrl'] }}">Manage draw</a>@endif
    <button id="fm-print" type="button">Print draw</button>
    @if($config['publicUrl'])<a id="fm-public" href="{{ $config['publicUrl'] }}" target="_blank" rel="noopener">Public view</a>@endif
  </nav>
</header>
<main id="fm-app">
  <div class="fm-intro"><div><strong id="fm-phase">Step 2 · Bracket size and starting positions</strong><p>Drag a player into a box, or click a box to choose a player.
    {{ ($config['workflow'] ?? '') === 'playoffs' ? 'Winners advance; losers are eliminated.' : 'Winners advance; losers continue for finishing positions.' }}
  </p></div><span id="fm-status" class="fm-badge">Draft</span></div>
  <p class="fm-rule">{{ match ($config['workflow'] ?? 'custom_monrad') {
    'playoffs' => 'Knockout only: place players in the opening round. No round robin or placement matches.',
    'monrad' => 'Full placement: place everyone in the opening round. No round robin is needed.',
    default => 'Full placement: entering a quarterfinal directly guarantees a top-eight finish.',
  } }} Empty boxes are not byes.</p>
  <div id="fm-message" role="status" aria-live="polite"></div>
  <p id="fm-withdrawn" class="fm-rule" hidden></p>
  <div class="fm-toolbar" id="fm-toolbar">
    <label>Starting bracket <select id="fm-size"><option>4</option><option>8</option><option>16</option><option selected>32</option><option>64</option></select></label>
    <button type="button" id="fm-undo">Undo</button>
    <button type="button" id="fm-byes">Mark remaining empty paths as byes</button>
    <button type="button" id="fm-example" hidden>Load 22-player example</button>
    <span class="fm-spacer"></span>
    <button type="button" id="fm-save">Save draft</button>
    <button type="button" id="fm-generate" class="fm-primary">Generate fixtures</button>
    <button type="button" id="fm-publish" class="fm-primary" hidden>Publish draw</button>
    <button type="button" id="fm-reopen" hidden>Edit starting positions</button>
    <button type="button" id="fm-withdrawals" hidden>Apply withdrawals</button>
  </div>
  <div class="fm-workspace">
    <aside id="fm-sidebar"><h2>Available players <span id="fm-count"></span></h2><label for="fm-search">Search players</label><input id="fm-search" type="search" placeholder="Name…"><p class="fm-help">Placed players leave this list. Click an occupied box to move, swap or remove a player. Removed players return here.</p><div id="fm-players"></div></aside>
    <section class="fm-board-wrap" aria-label="Monrad bracket"><div class="fm-board-heading"><h2 id="fm-board-title">Main draw</h2><span>Scroll across to later rounds →</span></div><div id="fm-scroll" tabindex="0" aria-label="Scrollable bracket"><div id="fm-board"></div></div></section>
  </div>
  <section id="fm-print-content" class="fm-print-only" aria-label="Printable draw"></section>
  <section id="fm-results" hidden><h2>Final positions</h2><div id="fm-positions"></div></section>
</main>
<dialog id="fm-slot-dialog" aria-labelledby="fm-slot-title">
  <form method="dialog"><button class="fm-close" aria-label="Close player picker">×</button></form>
  <h2 id="fm-slot-title">Starting position</h2><p id="fm-slot-description"></p>
  <p id="fm-slot-error" role="alert"></p>
  <label for="fm-pick-search">Find a player</label><input id="fm-pick-search" type="search" placeholder="Search names…">
  <label for="fm-pick">Player</label><select id="fm-pick" size="6"></select>
  <div class="fm-dialog-actions"><button type="button" id="fm-place" class="fm-primary">Place / move player</button><button type="button" id="fm-swap">Swap players</button><button type="button" id="fm-remove">Remove player / bye</button><button type="button" id="fm-bye">Set this path as a bye</button></div>
  <p class="fm-help">Remove a direct entrant to reopen their earlier qualifying path. Occupied earlier paths must be cleared individually.</p>
</dialog>
<dialog id="fm-score-dialog" aria-labelledby="fm-score-title">
  <form method="dialog"><button class="fm-close" aria-label="Close score entry">×</button></form>
  <h2 id="fm-score-title">Enter result</h2><p id="fm-score-players"></p>
  <label for="fm-sets">Set scores, in the player order above</label><input id="fm-sets" placeholder="6-4, 6-3" autocomplete="off">
  <p class="fm-help" id="fm-score-format">Enter completed sets, separated by commas: 6–0 to 6–4, 7–5 or 7–6.</p>
  <p id="fm-score-error" role="alert"></p>
  <label id="fm-reset-label" hidden><input type="checkbox" id="fm-reset"> Reset the affected later results listed above</label>
  <div class="fm-dialog-actions"><button type="button" id="fm-score-save" class="fm-primary">Save result</button><button type="button" id="fm-score-delete">Delete result</button></div>
</dialog>
<script id="fm-config" type="application/json">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/tennis-bracket-layout.js') }}?v={{ filemtime(public_path('js/tennis-bracket-layout.js')) }}" defer></script>
<script src="{{ asset('js/flexible-monrad.js') }}?v={{ filemtime(public_path('js/flexible-monrad.js')) }}" defer></script>
</body>
</html>
