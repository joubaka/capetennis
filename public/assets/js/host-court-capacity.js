/* Court suggestions and financial projections are calculated by the server. */
(() => {
  'use strict';
  const form = document.querySelector('[data-host-proposal]');
  if (!form) return;
  const status = form.querySelector('[data-capacity-status]');
  const submit = form.querySelector('[data-host-submit]');
  const limits = Array.from(form.querySelectorAll('[data-entry-limit]'));
  const outputs = Array.from(form.querySelectorAll('[data-court-suggestion]'));
  const panel = form.querySelector('[data-financial-projection]');
  const values = panel.querySelector('[data-projection-values]');
  const unavailable = panel.querySelector('[data-projection-unavailable]');
  const money = cents => 'R' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  let ready = form.dataset.planReady === '1';
  let revision = 0;
  let controller;
  let timer;
  let needsSuggestedLimits = false;
  const mark = (message, valid) => {
    ready = valid;
    submit.disabled = !valid;
    status.textContent = message;
    status.classList.toggle('alert-info', valid);
    status.classList.toggle('alert-warning', !valid);
  };
  const clearProjection = message => {
    values.hidden = true;
    unavailable.hidden = false;
    unavailable.textContent = message;
  };
  const renderProjection = projection => {
    const fields = ['entries', 'fee', 'gross', 'processing', 'platform', 'retained'];
    if (!projection || !Array.isArray(projection.rows) || projection.rows.length !== 4 || !projection.totals || !projection.assumptions
        || !projection.rows.every(row => fields.every(key => Number.isSafeInteger(row[key])))
        || !['entries', 'gross', 'processing', 'platform', 'retained', 'convenor', 'rental', 'other_costs', 'remaining'].every(key => Number.isSafeInteger(projection.totals[key]))) {
      throw new Error('The financial projection could not be verified. Edit an amount to try again.');
    }
    projection.rows.forEach((row, i) => fields.forEach(key => {
      panel.querySelector(`[data-projection-cell="${i}-${key}"]`).textContent = key === 'entries' ? String(row[key]) : money(row[key]);
    }));
    panel.querySelectorAll('[data-projection-total]').forEach(element => {
      const key = element.dataset.projectionTotal;
      element.textContent = key === 'entries' ? String(projection.totals[key]) : money(projection.totals[key]);
    });
    const assumptions = projection.assumptions;
    panel.querySelector('[data-projection-rental-formula]').textContent = assumptions.courts + ' assigned courts × ' + assumptions.hours + ' hours × R' + assumptions.rental + ' per court/hour.';
    const loss = projection.totals.remaining < 0;
    panel.querySelector('[data-projection-result-label]').textContent = loss ? 'Projected loss' : 'Academy has left';
    const result = panel.querySelector('[data-projection-result]');
    result.classList.toggle('bg-label-danger', loss);
    result.classList.toggle('bg-label-primary', !loss);
    unavailable.hidden = true;
    values.hidden = false;
  };
  const update = async current => {
    controller = new AbortController();
    const auto = needsSuggestedLimits;
    const payload = new FormData();
    payload.set('_token', form.elements._token.value);
    payload.set('submission_token', form.elements.submission_token.value);
    payload.set('use_suggested_limits', auto ? '1' : '0');
    form.querySelectorAll('[data-capacity-input], [data-entry-limit], [data-projection-input]').forEach(input => payload.set(input.name, input.value));
    try {
      const response = await fetch(form.dataset.suggestionsUrl, { method: 'POST', body: payload, signal: controller.signal,
        credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      if (current !== revision) return;
      const result = await response.json();
      if (current !== revision) return;
      if (!response.ok) {
        const messages = Object.values(result.errors || {}).flat();
        throw new Error(messages[0] || 'Suggestions and finances could not be updated. Check the inputs and try again.');
      }
      if (result.model !== 'court-ratio-v2' || !Array.isArray(result.limits) || result.limits.length !== 4
          || !result.limits.every(value => Number.isInteger(value) && value >= 0 && value <= 1600)) {
        throw new Error('Court suggestions could not be verified. Change an assignment to try again.');
      }
      renderProjection(result.financial_projection);
      result.limits.forEach((value, i) => {
        outputs[i].textContent = String(value);
        limits[i].max = String(value);
        if (auto) limits[i].value = String(value);
      });
      needsSuggestedLimits = false;
      const hasEntries = result.financial_projection.totals.entries > 0;
      mark(result.assigned_total + ' of ' + result.inputs.courts + ' courts assigned · ' + result.suggested_entries + ' suggested places.'
        + (hasEntries ? '' : ' Add at least one entry before submitting.'), hasEntries);
    } catch (error) {
      if (current !== revision || error.name === 'AbortError') return;
      outputs.forEach(output => { output.textContent = '—'; });
      const message = error instanceof SyntaxError ? 'The projection is unavailable. Edit an input to retry.' : error.message;
      clearProjection(message);
      mark(message, false);
    } finally {
      if (current === revision) panel.setAttribute('aria-busy', 'false');
    }
  };
  form.querySelectorAll('[data-capacity-input], [data-entry-limit], [data-projection-input]').forEach(input => input.addEventListener('input', () => {
    if (input.hasAttribute('data-capacity-input')) needsSuggestedLimits = true;
    revision++;
    const current = revision;
    if (controller) controller.abort();
    clearTimeout(timer);
    outputs.forEach(output => { output.textContent = '—'; });
    clearProjection('Updating your financial projection…');
    panel.setAttribute('aria-busy', 'true');
    mark('Updating suggestions and finances…', false);
    timer = setTimeout(() => update(current), 250);
  }));
  form.addEventListener('submit', event => { if (!ready) event.preventDefault(); });
})();
