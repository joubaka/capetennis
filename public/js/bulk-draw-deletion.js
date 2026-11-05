(function (window, document) {
  'use strict';
  const toolbar = document.querySelector('[data-draw-delete-toolbar]');
  if (!toolbar) return;
  const selections = Array.from(document.querySelectorAll('[data-delete-draw]'));
  const submit = toolbar.querySelector('[data-draw-delete-submit]');
  const count = toolbar.querySelector('[data-draw-delete-count]');
  const feedback = toolbar.querySelector('[data-draw-delete-feedback]');
  let pending = false;
  function selected() { return selections.filter(input => input.checked).map(input => Number(input.value)); }
  function update() { count.textContent = selected().length + ' draws selected'; submit.disabled = pending || selected().length === 0; }
  function show(message) { feedback.hidden = false; feedback.textContent = message; }
  function busy(value) {
    pending = value;
    selections.concat(Array.from(toolbar.querySelectorAll('button'))).forEach(control => { control.disabled = value; });
    toolbar.setAttribute('aria-busy', String(value)); update();
  }
  selections.forEach(input => {
    input.addEventListener('click', event => event.stopPropagation());
    input.addEventListener('change', update);
  });
  toolbar.querySelector('[data-draw-delete-select-all]').addEventListener('click', () => { if (pending) return; selections.forEach(input => { input.checked = true; }); update(); });
  toolbar.querySelector('[data-draw-delete-clear]').addEventListener('click', () => { if (pending) return; selections.forEach(input => { input.checked = false; }); update(); });
  async function request(url, method, payload) {
    const response = await window.fetch(url, {method, credentials: 'same-origin',
      headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify(payload)});
    const result = await response.json();
    if (!response.ok || result.success !== true) throw new Error(Object.values(result.errors || {}).flat().join('\n') || result.message || 'The request could not be confirmed.');
    return result;
  }
  submit.addEventListener('click', async () => {
    if (pending || window.HeadOfficeDrawPublicationPending || window.HeadOfficeDrawPublicationUnconfirmed) return;
    const ids = selected();
    if (!ids.length) return;
    window.HeadOfficeDrawPublicationPending = true;
    busy(true); show('Checking selected draws…');
    let deleting = false;
    try {
      const review = await request(toolbar.dataset.previewUrl, 'POST', {draw_ids: ids});
      if (Number(review.event_id) !== Number(toolbar.dataset.eventId) || !/^[a-f0-9]{64}$/.test(review.revision)
          || !Array.isArray(review.draws) || review.draws.length !== ids.length || new Set(review.draws.map(draw => draw.id)).size !== ids.length
          || review.draws.some(draw => !ids.includes(draw.id) || typeof draw.name !== 'string')) throw new Error('The selected draws could not be confirmed. Refresh and review again.');
      const message = review.draws.map(draw => draw.name).join('\n') + '\n\n' + review.individual_fixtures + ' individual fixtures and ' + review.team_fixtures + ' team fixtures will also be deleted. Teams, registrations and payments will be retained. This cannot be undone.';
      const confirmed = window.Swal ? (await window.Swal.fire({title: 'Delete ' + ids.length + ' selected draws?', text: message, icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Delete selected draws', confirmButtonColor: '#dc3545'})).isConfirmed
        : window.confirm('Delete ' + ids.length + ' selected draws?\n\n' + message);
      if (!confirmed) { show('Deletion cancelled.'); return; }
      deleting = true; show('Deleting selected draws…');
      const result = await request(toolbar.dataset.deleteUrl, 'DELETE', {draw_ids: ids, revision: review.revision});
      if (result.deleted !== true || Number(result.event_id) !== Number(toolbar.dataset.eventId)
          || !Array.isArray(result.draws) || result.draws.length !== ids.length || new Set(result.draws.map(draw => draw.id)).size !== ids.length
          || result.draws.some(draw => !ids.includes(draw.id))) throw new Error('Deletion could not be confirmed.');
      window.location.reload();
    } catch (error) {
      show((deleting ? 'Deletion could not be confirmed. Refresh the draw list before retrying. ' : '') + error.message);
    } finally { window.HeadOfficeDrawPublicationPending = false; busy(false); }
  });
  update();
})(window, document);
