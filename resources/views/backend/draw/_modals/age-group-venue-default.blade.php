@php
  $venueDefaultEvent = $event ?? ($draw->event ?? null);
  $venueDefaultChoices = $venueDefaultEvent ? $venueDefaultEvent->draws->map(function ($candidate) {
    return ['id' => $candidate->id, 'name' => $candidate->drawName, 'key' => app(\App\Services\Scheduling\AgeGroupVenueDefaultService::class)->key($candidate)];
  })->values()->all() : [];
@endphp
<div class="form-check mt-3">
  <input class="form-check-input" type="checkbox" name="age_group_default" value="1" id="age-group-venue-default">
  <label class="form-check-label" for="age-group-venue-default">Use these venues as the default for this age group</label>
</div>
<div id="age-group-venue-options" class="mt-2" hidden>
  <label class="form-label" for="age-group-venue-scope">Apply to</label>
  <select class="form-select form-select-sm" name="age_group_default_scope" id="age-group-venue-scope" disabled>
    <option value="age">All genders — boys, girls and mixed doubles</option>
    <option value="gender">Only this draw's gender</option>
  </select>
  <input type="hidden" name="age_group_draw_selection" value="1" id="age-group-draw-selection" disabled>
  <div class="form-text mb-2">All matching draws and squads are selected. Uncheck any draw you want to keep unchanged. Future draws in this scope will use this default. Saved bookings, court allocations, and locked or published draws must be reviewed before changing their venues.</div>
  <div id="age-group-venue-draws" class="border rounded p-2" style="max-height: 180px; overflow-y: auto;"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('venuesForm');
  const checkbox = document.getElementById('age-group-venue-default');
  if (!form || !checkbox) return;
  const choices = @json($venueDefaultChoices);
  const options = document.getElementById('age-group-venue-options');
  const scope = document.getElementById('age-group-venue-scope');
  const selection = document.getElementById('age-group-draw-selection');
  const list = document.getElementById('age-group-venue-draws');
  let sourceId = @json(isset($draw) ? $draw->id : null);
  const feedback = document.createElement('div');
  feedback.className = 'alert alert-danger mt-3';
  feedback.setAttribute('role', 'alert');
  feedback.hidden = true;
  options.after(feedback);
  function renderChoices() {
    const source = choices.find(draw => String(draw.id) === String(sourceId));
    list.replaceChildren();
    choices.filter(draw => source?.key && draw.key && draw.key.age === source.key.age &&
      (scope.value === 'age' || draw.key.gender === source.key.gender)).forEach(draw => {
      const row = document.createElement('label');
      row.className = 'd-flex gap-2 align-items-start mb-1';
      const input = document.createElement('input');
      input.type = 'checkbox'; input.name = 'age_group_draw_ids[]'; input.value = draw.id;
      input.checked = true; input.disabled = !checkbox.checked; input.className = 'form-check-input';
      const name = document.createElement('span'); name.textContent = draw.name;
      row.append(input, name); list.append(row);
    });
    if (!list.children.length) list.textContent = 'This draw needs an unambiguous age group before a default can be saved.';
  }
  checkbox.addEventListener('change', function () {
    options.hidden = !checkbox.checked; scope.disabled = !checkbox.checked; selection.disabled = !checkbox.checked;
    renderChoices();
  });
  scope.addEventListener('change', renderChoices);
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.btn-add-venues');
    if (!button) return;
    sourceId = button.dataset.drawId;
    checkbox.checked = false; options.hidden = true; scope.value = 'age'; scope.disabled = true; selection.disabled = true;
    feedback.hidden = true; renderChoices();
  });
  form.addEventListener('submit', async function (event) {
    if (!checkbox.checked) return;
    event.preventDefault(); event.stopImmediatePropagation();
    const button = form.querySelector('[type="submit"]');
    if (button.disabled) return;
    button.disabled = true; feedback.hidden = true;
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = response.status >= 500 ? 'The server could not confirm the venue save. Refresh and check the saved venues and defaults before retrying. Contact an administrator if this continues.' :
          response.status === 419 ? 'Your session expired. Refresh the page, sign in if needed, and retry.' :
          response.status === 403 ? 'You do not have permission to update these draws.' :
          Object.values(result.errors || {}).flat().join(' ') || result.message || 'Could not save venue defaults. Review your selections and retry.';
        throw new Error(message);
      }
      window.location.reload();
    } catch (error) {
      const message = error instanceof TypeError ? 'The save response could not be confirmed. Check your connection, then refresh and check the venues before retrying.' : error.message;
      feedback.textContent = message; feedback.hidden = false;
      if (window.toastr) window.toastr.error(message, '', {escapeHtml: true});
    } finally { button.disabled = false; }
  }, true);
});
</script>
