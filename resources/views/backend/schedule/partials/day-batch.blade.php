<section id="day-batch-workflow" class="workspace-section mb-3">
  <div class="section-body">
    <h5>1. Select draws</h5>
    <p class="small text-muted">Choose all draws, several, or just one. Only the rounds and matches you select will be scheduled.</p>
    <div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="btn btn-sm btn-outline-primary" id="day-batch-all">Select all ready draws</button><button type="button" class="btn btn-sm btn-outline-secondary" id="day-batch-clear">Clear selection</button></div>
    <div id="day-batch-draws" class="row g-2 mb-3"></div>
    <div id="day-batch-order" class="mb-3"></div>
    <label class="form-label" for="day-batch-order-mode">Draw order</label>
    <select id="day-batch-order-mode" class="form-select mb-2"><option value="mixed">Mix draws · use the earliest available courts</option><option value="sequential">In order · finish each draw's round before the next draw</option></select>
    <p class="small text-muted">In order follows your draw list for each selected round, then moves to the next round. Mix draws fills available courts across the selection. Draw order replaces separate boys/girls priority; each draw keeps its own gender.</p>
    <button type="button" class="btn btn-primary" id="day-batch-next-courts">Continue to day &amp; courts</button>

    <section id="day-batch-courts-step" class="border-top pt-3 mt-3" hidden>
      <h5>2. Set up courts for this day</h5>
      <div class="row g-3 mb-3"><div class="col-md-4"><label class="form-label" for="day-batch-date">Playing day</label><select id="day-batch-date" class="form-select"></select></div><div class="col-6 col-md-4"><label class="form-label" for="day-batch-start">Starts</label><input id="day-batch-start" class="form-control" type="time" value="08:00" required></div><div class="col-6 col-md-4"><label class="form-label" for="day-batch-end">Finishes</label><input id="day-batch-end" class="form-control" type="time" value="18:00" required></div></div>
      <p class="small">These courts apply to this day's selected matches. Other days, draw defaults and saved times stay in place.</p>
      <button type="button" class="btn btn-sm btn-outline-primary mb-3" data-bs-toggle="modal" data-bs-target="#venue-management-modal">Add or edit event venues &amp; courts</button>
      <div id="day-batch-venues" class="mb-3"></div>
      <div class="row g-3 mb-3"><div class="col-6 col-md-4"><label class="form-label" for="day-batch-duration">Match minutes</label><input id="day-batch-duration" class="form-control" type="number" min="15" max="480" value="{{ $scheduleDraft['duration'] }}" required></div><div class="col-6 col-md-4"><label class="form-label" for="day-batch-rest">Player rest minutes</label><input id="day-batch-rest" class="form-control" type="number" min="0" max="480" value="{{ $scheduleDraft['player_rest'] }}" required></div><div class="col-6 col-md-4"><label class="form-label" for="day-batch-gap">Court gap minutes</label><input id="day-batch-gap" class="form-control" type="number" min="0" max="120" value="{{ $scheduleDraft['court_gap'] }}" required></div></div>
      <label class="d-block mb-2"><input id="day-batch-limit" class="form-check-input me-2" type="checkbox" @checked($scheduleDraft['limit_daily_matches'])>Optional: maximum 3 matches per player per day, including conditional play-offs</label>
      <div class="d-flex flex-wrap gap-2 align-items-center mb-3"><button type="button" id="day-batch-save-timing" class="btn btn-sm btn-outline-secondary">Save timing settings</button><span class="small text-muted">Save settings to enable or disable the event's daily limit. This does not change existing match times.</span></div>
      <label class="d-block mb-3"><input id="day-batch-replan" class="form-check-input me-2" type="checkbox">Also replan selected saved matches on this day</label>
      <details id="day-batch-team-options" class="mb-3" hidden><summary>Team scheduling options</summary><p class="small mt-2">Saved position bands remain in force. Without position bands, each team tie stays at one venue and court group. Use Advanced tools to change position bands or custom tie destinations.</p><div class="row g-2"><label class="col-md-6">Round progression<select id="day-batch-progression" class="form-select"><option value="team_ready">Next round when the team's preceding tie is ready</option><option value="all_round">Finish the whole preceding team round first</option></select></label><label class="col-md-6">Tie allocation<select id="day-batch-tie-allocation" class="form-select"><option value="balanced">Balance available team ties</option><option value="complete_tie">Prefer completing the current tie</option></select></label></div></details>
      <button type="button" id="day-batch-next-rounds" class="btn btn-primary">Continue to rounds</button>
    </section>

    <section id="day-batch-rounds-step" class="border-top pt-3 mt-3" hidden>
      <h5>3. Choose rounds for this day</h5>
      <p class="small text-muted">Add or remove rounds below. Expand a round to select position groups or specific matches. Matches already saved on another day stay fixed. Earlier qualifying matches must already be scheduled.</p>
      <div id="day-batch-rounds"></div>
      <button type="button" id="day-batch-preview" class="btn btn-primary">Preview selected matches</button>
    </section>
    <div id="day-batch-status" class="mt-3" role="status" aria-live="polite"></div>
  </div>
</section>
<button type="button" id="toggle-advanced-scheduling" class="btn btn-link mb-3">Advanced: complete programmes and draw defaults</button>
