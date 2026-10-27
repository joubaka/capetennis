(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.CapeScheduleMoveConflicts = api;
})(typeof globalThis === 'object' ? globalThis : this, function () {
  'use strict';
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const time = value => {
    if (!value) return 'Not scheduled';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('en-GB', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'});
  };
  const matchLabel = row => `${row.draw_name} · Match ${row.match ?? '—'}`;
  const location = row => `${time(row.scheduled_at)} · ${row.venue_name} · Court ${row.court}`;
  function checkingContext(preview, allowedDrawIds, fixtureKey, slot) {
    const allowed = new Set(allowedDrawIds.map(Number));
    const rows = [...new Map([...(preview?.matches || []), ...(preview?.existing_matches || []), ...(preview?.unscheduled || [])]
      .filter(row => allowed.has(Number(row.draw_id))).map(row => [row.fixture_key, row])).values()];
    const moving = rows.find(row => row.fixture_key === fixtureKey);
    const describe = row => ({fixture_key:row.fixture_key, draw_name:row.draw_name, match:row.match, scheduled_at:row.scheduled_at,
      venue_name:(preview.venues || []).find(venue => Number(venue.id) === Number(row.venue_id))?.name || 'Venue', court:row.court});
    const dependencies = row => (row.dependencies || []).map(key => typeof key === 'number' || /^\d+$/.test(String(key)) ? 'individual:'+key : key);
    const related = moving ? rows.filter(row => row.fixture_key !== fixtureKey && (dependencies(moving).includes(row.fixture_key) || dependencies(row).includes(fixtureKey)))
      .map(row => ({...describe(row), relationship:dependencies(row).includes(fixtureKey) ? 'dependent' : 'qualifier'})) : [];
    return {moving:moving ? describe(moving) : null,
      requested:{scheduled_at:slot.time,venue_name:(preview?.venues || []).find(venue => Number(venue.id) === Number(slot.venue))?.name || 'Selected venue',court:slot.court},
      related:related.slice(0,8),omitted_related:Math.max(0,related.length-8),
      checks_alternatives:[...(preview?.matches || []),...(preview?.unscheduled || [])].some(row => row.fixture_key === fixtureKey)};
  }
  function checkingHtml(report) {
    return `<div data-move-check-context><div class="d-flex align-items-center gap-2 mb-3"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><span role="status">Request in progress</span><span class="small text-muted ms-auto" data-move-check-elapsed role="timer" aria-live="off">Elapsed: 0s</span></div>
      <p class="fw-semibold mb-1">${report.moving ? escape(matchLabel(report.moving)) : 'Selected match'}</p>${report.requested ? `<p class="small text-muted">Requested: ${escape(location(report.requested))}</p>` : ''}
      <h6>Checks included in this request</h6><ul><li>Requested venue, court availability and other bookings.</li><li>Qualifying matches and required player rest.</li><li>Later matches that depend on this match.</li><li>Keeping every other match in place.</li>${report.checks_alternatives ? '<li>Nearby alternatives if this unsaved preview move does not fit.</li>' : ''}</ul>
      <p class="small text-muted">The server has not returned a result yet. These checks do not indicate completed steps.</p>
      ${(report.related || []).length ? `<h6>Related matches being checked</h6><ul class="list-unstyled d-grid gap-2">${report.related.map(row => `<li class="border rounded p-2"><div class="small fw-semibold">${row.relationship === 'dependent' ? 'Later dependent match' : 'Earlier qualifying match'}</div><div>${escape(matchLabel(row))}</div><div class="small text-muted">${escape(location(row))}</div></li>`).join('')}</ul>` : '<p class="small text-muted">Any other relevant bookings are checked by the server.</p>'}
      ${report.omitted_related ? `<p class="small text-muted">${Number(report.omitted_related)} more related matches are included in the checks.</p>` : ''}
      <p class="small mb-0">You can hide these details. The request will continue; hiding this window does not cancel the move.</p></div>`;
  }
  function html(report) {
    const issues = [...(report.issues || [])].sort((left, right) => Number(right.type === 'dependent') - Number(left.type === 'dependent'));
    const labels = {dependent:'Move this later match first', qualifier:'Qualifying match', court:'Court already booked', player_rest:'Player availability', availability:'Scheduling rule'};
    const requirements = {dependent:'This later match must start at or after', qualifier:'The match you are moving may start at or after', court:'This court booking finishes, including its gap, at', player_rest:'These players are free after this match at'};
    return `${report.moving ? `<p class="fw-semibold mb-1">${escape(matchLabel(report.moving))}</p>` : ''}${report.requested ? `<p class="small text-muted">Requested: ${escape(location(report.requested))}</p>` : ''}<p>${escape(report.message || 'This match could not be moved.')}</p>
      <ul class="list-unstyled d-grid gap-3">${issues.map(issue => `<li class="border rounded p-3"><div class="fw-semibold">${escape(labels[issue.type] || 'Schedule conflict')}</div>${issue.match ? `<div class="mt-1">${escape(matchLabel(issue.match))}</div><div class="small text-muted">${escape(location(issue.match))}</div>` : ''}<p class="mb-1 mt-2">${escape(issue.message)}</p>${issue.required_at ? `<div class="small fw-semibold">${escape(requirements[issue.type] || 'Required time:')} ${escape(time(issue.required_at))}.</div><div class="small text-muted">This is a timing requirement; court availability is checked when you move the match.</div>` : ''}${issue.match?.fixture_key ? `<button type="button" class="btn btn-sm btn-outline-primary mt-2" data-conflict-review="${escape(issue.match.fixture_key)}">Review Match ${escape(issue.match.match ?? '—')}</button>` : ''}</li>`).join('')}</ul>
      ${report.omitted_issues ? `<p class="small text-muted">${Number(report.omitted_issues)} more conflicts also need attention.</p>` : ''}
      ${(report.suggestions || []).length ? `<h6>Checked alternative</h6>${report.suggestions.map((slot, index) => `<div class="border rounded p-3 mb-2"><div>${escape(location(slot))}</div>${slot.current_slot ? '<div class="small text-muted">No nearby alternative was found. Your current slot still fits; move the blocking matches first if you need the requested time.</div>' : '<div class="small text-muted">Checked with every other match kept in place. It will be checked again when selected.</div>'}<button type="button" class="btn btn-primary mt-2" data-conflict-suggestion="${index}">${slot.current_slot ? 'Keep current slot' : 'Use this slot'}</button></div>`).join('')}` : '<p class="small text-muted">Review the blocking matches first or choose another time. Each move is checked again.</p>'}
      <p class="small text-muted mb-0">Other match times stay unchanged.</p>`;
  }
  function mount(options) {
    const doc = options.document || document;
    const element = doc.getElementById('scheduleMoveConflictModal');
    const body = doc.getElementById('schedule-move-conflict-body');
    const modal = options.bootstrap.Modal.getOrCreateInstance(element);
    const title = doc.getElementById('scheduleMoveConflictLabel');
    const dismiss = doc.getElementById('schedule-move-conflict-dismiss');
    const now = options.now || Date.now;
    const schedule = options.setInterval || setInterval;
    const cancel = options.clearInterval || clearInterval;
    let report = null;
    let mode = 'closed';
    let timer = null;
    let startedAt = 0;
    let desiredVisible = false;
    let waitingForOtherModal = false;
    let returnFocus = null;
    let followup = false;
    element.addEventListener('hide.bs.modal', () => { desiredVisible = false; });
    element.addEventListener('shown.bs.modal', () => { if (!desiredVisible) modal.hide(); });
    element.addEventListener('hidden.bs.modal', () => {
      if (desiredVisible) modal.show();
      else if (!followup && returnFocus?.isConnected && returnFocus.getClientRects().length) returnFocus.focus();
      followup = false;
    });
    const afterClose = callback => {
      followup = true;
      desiredVisible = false;
      element.addEventListener('hidden.bs.modal', callback, {once:true});
      modal.hide();
    };
    const clearChecking = () => {
      if (timer !== null) cancel(timer);
      timer = null;
      body.setAttribute('aria-busy','false');
    };
    const present = () => {
      desiredVisible = true;
      if (waitingForOtherModal) return;
      const open = doc.querySelector('.modal.show');
      if (open && open !== element) {
        waitingForOtherModal = true;
        open.addEventListener('hidden.bs.modal', () => { waitingForOtherModal = false; if (desiredVisible) modal.show(); }, {once:true});
        options.bootstrap.Modal.getOrCreateInstance(open).hide();
      } else modal.show();
    };
    element.addEventListener('click', event => {
      if (event.target.closest('[data-bs-dismiss="modal"]')) desiredVisible = false;
      const suggestion = event.target.closest('[data-conflict-suggestion]');
      const review = event.target.closest('[data-conflict-review]');
      if (mode !== 'conflict') return;
      if (suggestion && report?.suggestions?.[Number(suggestion.dataset.conflictSuggestion)]) {
        const slot = report.suggestions[Number(suggestion.dataset.conflictSuggestion)];
        afterClose(() => options.onSuggestion?.(slot));
      } else if (review) afterClose(() => options.onReview?.(review.dataset.conflictReview));
    });
    return {
      show(value) {
        if (mode !== 'checking' && !desiredVisible) returnFocus = doc.activeElement;
        clearChecking(); mode = 'conflict'; report = value;
        title.textContent = 'Why this match cannot move'; dismiss.textContent = 'Return to schedule';
        body.innerHTML = html(value); present();
      },
      checking(value) {
        if (mode === 'checking') return;
        returnFocus = doc.activeElement;
        mode = 'checking'; report = value; startedAt = now();
        title.textContent = 'Checking this move'; dismiss.textContent = 'Hide details';
        body.innerHTML = checkingHtml(value); body.setAttribute('aria-busy','true');
        timer = schedule(() => {
          const seconds = Math.max(0,Math.floor((now()-startedAt)/1000));
          const elapsed = body.querySelector('[data-move-check-elapsed]');
          if (elapsed) elapsed.textContent = 'Elapsed: '+(seconds >= 60 ? Math.floor(seconds/60)+'m ' : '')+(seconds%60)+'s';
        },1000);
        present();
      },
      reopenChecking() { if (mode === 'checking') present(); },
      finishChecking() {
        if (mode !== 'checking') return;
        clearChecking(); mode = 'closed'; desiredVisible = false; modal.hide();
      },
    };
  }
  return {html, checkingHtml, checkingContext, mount};
});
