<main id="fm-app">
  @unless($config['readOnly'] ?? false)
  <div class="fm-intro"><div><strong id="fm-phase">Players and starting positions</strong><p>Drag a player into a box, or click a box to choose a player. Drag one placed player onto another to swap their positions.
    {{ ($config['workflow'] ?? '') === 'playoffs' ? 'Winners advance; losers are eliminated.' : 'Winners advance; losers continue for finishing positions.' }}
    @if(($config['workflow'] ?? '') === 'feed_in_monrad') Earlier-round losers cross into the feed-in draw against later-round losers. Semifinal losers play for third; the feed-in winner takes fifth. @endif
  </p></div><span id="fm-status" class="fm-badge">Draft</span></div>
  @endunless
  <div id="fm-message" role="status" aria-live="polite"></div>
  <p id="fm-swap-help" class="fm-help" hidden>Drag a player’s name onto another player’s name to swap their draw positions. Match times and courts stay the same; the schedule updates with the players.</p>
  <p id="fm-withdrawn" class="fm-rule" hidden></p>
  <div class="fm-toolbar" id="fm-toolbar">
    <label>Starting bracket <select id="fm-size"><option>4</option><option>8</option><option>16</option><option>32</option><option>64</option></select></label>
    <span class="fm-help" title="The suggested size is the smallest bracket that fits the eligible players. Empty opening paths can be marked as byes.">Choose the smallest bracket that fits all players.</span>
    <button type="button" id="fm-undo">Undo</button>
    <button type="button" id="fm-add-player" hidden>{{ ($config['paidEntryRequired'] ?? false) ? 'Add paid player' : 'Add system player' }}</button>
    <button type="button" id="fm-byes">Mark remaining empty paths as byes</button>
    <button type="button" id="fm-example" hidden>Load 22-player example</button>
    <span class="fm-spacer"></span>
    <button type="button" id="fm-save" title="Keeps player positions editable and does not create matches.">Save draft</button>
    <button type="button" id="fm-generate" class="fm-primary" title="Creates the matches and freezes starting positions until the draw is reopened.">Generate fixtures</button>
    <button type="button" id="fm-publish" class="fm-primary" hidden>Publish draw</button>
    <button type="button" id="fm-reopen" hidden>Edit starting positions</button>
    <button type="button" id="fm-withdrawals" hidden>Continue as late withdrawal</button>
    <button type="button" id="fm-withdrawal-redraw" hidden>Redraw without player</button>
  </div>
  <div class="fm-workspace">
    <aside id="fm-sidebar"><h2>Players <span id="fm-count"></span></h2><label for="fm-search">Search players</label><input id="fm-search" type="search" placeholder="Name…"><p class="fm-help">Drag names from this list or between draw boxes. Placed players stay green in the list. Drag a name back here to clear its placement, or click a box for more options.</p><div id="fm-players"></div></aside>
    <section class="fm-board-wrap" aria-label="Monrad bracket"><div class="fm-board-heading"><h2 id="fm-board-title">Main draw</h2><span>Scroll across to later rounds →</span></div><div id="fm-scroll" tabindex="0" aria-label="Scrollable bracket"><div id="fm-board"></div></div></section>
  </div>
  <section id="fm-print-content" class="fm-print-only" aria-label="Printable draw"></section>
  <section id="fm-results" hidden><h2>Final positions</h2><div id="fm-positions"></div></section>
</main>
<dialog id="fm-add-player-dialog" aria-labelledby="fm-add-player-title">
  <form method="dialog"><button class="fm-close" aria-label="Close system player search">×</button></form>
  <h2 id="fm-add-player-title">{{ ($config['paidEntryRequired'] ?? false) ? 'Add paid player' : 'Add system player' }}</h2>
  <p class="fm-help">{{ ($config['paidEntryRequired'] ?? false) ? 'Add any system player as a paid event entry. Existing checkouts must be handled on the Entries page.' : 'Add an existing player to this draw only, or select the paid-entry option below to also add them to the event entries.' }} Then place them in a starting position.</p>
  <label><input id="fm-add-paid-entry" type="checkbox"> Add as paid entry — paid outside the app</label>
  <p class="fm-help">Select this only after receiving payment outside the app. It also adds the player to the event's category entries using the existing private-payment record. The category must be unlocked.</p>
  <form id="fm-system-player-search-form"><label for="fm-system-player-search">Player name</label><input id="fm-system-player-search" type="search" minlength="2" maxlength="100" required placeholder="At least 2 characters"><button type="submit">Search system players</button></form>
  <p id="fm-system-player-error" role="alert"></p>
  <div id="fm-system-player-results" aria-live="polite"></div>
</dialog>
<dialog id="fm-slot-dialog" aria-labelledby="fm-slot-title">
  <form method="dialog"><button class="fm-close" aria-label="Close player picker">×</button></form>
  <h2 id="fm-slot-title">Starting position</h2><p id="fm-slot-description"></p>
  <p id="fm-slot-error" role="alert"></p>
  <div id="fm-pick-search-fallback"><label for="fm-pick-search">Find a player</label><input id="fm-pick-search" type="search" placeholder="Search names…"></div>
  <label for="fm-pick">Player</label><select id="fm-pick" size="6"></select>
  <div class="fm-dialog-actions"><button type="button" id="fm-place" class="fm-primary">Place / move player</button><button type="button" id="fm-swap">Swap players</button><button type="button" id="fm-remove">Remove player / bye</button><button type="button" id="fm-bye">Set this path as a bye</button></div>
  <p class="fm-help">Remove a direct entrant to reopen their earlier qualifying path. Occupied earlier paths must be cleared individually.</p>
</dialog>
<dialog id="fm-score-dialog" aria-labelledby="fm-score-title">
  <form method="dialog"><button class="fm-close" aria-label="Close score entry">×</button></form>
  <h2 id="fm-score-title">Enter result</h2><p id="fm-score-players"></p>
  <p class="fm-help" id="fm-score-format">Enter completed sets.</p>
  <label for="fm-result-type">Result type</label>
  <select id="fm-result-type" class="form-select mb-3"><option value="played">Played match</option><option value="walkover">Walkover (W/O)</option><option value="retired">Retired (RET)</option></select>
  <div id="fm-walkover-panel" hidden>
    <label for="fm-walkover-winner">Walkover winner</label>
    <select id="fm-walkover-winner" class="form-select"></select>
    <p class="fm-help">Record a walkover without set scores. Both players remain in the draw.</p>
  </div>
  @include('shared.scoring.set-grid', ['scoreGridPrefix' => 'fm-score'])
  <div id="fm-retired-panel" hidden>
    <label for="fm-retired-player">Who retired?</label>
    <select id="fm-retired-player" class="form-select"></select>
    <p class="fm-help">Enter the score when play stopped, including any unfinished set. The opponent wins this match.</p>
  </div>
  <p id="fm-score-error" role="alert"></p>
  <label id="fm-reset-label" hidden><input type="checkbox" id="fm-reset"> Reset the affected later results listed above</label>
  <div class="fm-dialog-actions"><button type="button" id="fm-score-save" class="fm-primary">Save result</button><button type="button" id="fm-score-delete">Delete result</button></div>
</dialog>
<script id="fm-config" type="application/json">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/tennis-bracket-layout.js') }}?v={{ filemtime(public_path('js/tennis-bracket-layout.js')) }}" defer></script>
<script src="{{ asset('js/flexible-monrad.js') }}?v={{ filemtime(public_path('js/flexible-monrad.js')) }}" defer></script>
