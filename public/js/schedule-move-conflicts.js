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
    let report = null;
    let returnFocus = null;
    let followup = false;
    element.addEventListener('hidden.bs.modal', () => {
      if (!followup && returnFocus?.isConnected && returnFocus.getClientRects().length) returnFocus.focus();
      followup = false;
    });
    const afterClose = callback => {
      followup = true;
      element.addEventListener('hidden.bs.modal', callback, {once:true});
      modal.hide();
    };
    element.addEventListener('click', event => {
      const suggestion = event.target.closest('[data-conflict-suggestion]');
      const review = event.target.closest('[data-conflict-review]');
      if (suggestion && report?.suggestions?.[Number(suggestion.dataset.conflictSuggestion)]) {
        const slot = report.suggestions[Number(suggestion.dataset.conflictSuggestion)];
        afterClose(() => options.onSuggestion?.(slot));
      } else if (review) afterClose(() => options.onReview?.(review.dataset.conflictReview));
    });
    return {show(value) {
      if (!element.classList?.contains('show')) returnFocus = doc.activeElement;
      report = value; body.innerHTML = html(value);
      const open = doc.querySelector('.modal.show');
      if (open && open !== element) {
        open.addEventListener('hidden.bs.modal', () => modal.show(), {once:true});
        options.bootstrap.Modal.getOrCreateInstance(open).hide();
      } else modal.show();
    }};
  }
  return {html, mount};
});
