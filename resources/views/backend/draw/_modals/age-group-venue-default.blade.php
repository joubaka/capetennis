<div class="form-check mt-3">
  <input class="form-check-input" type="checkbox" name="age_group_default" value="1" id="age-group-venue-default">
  <label class="form-check-label" for="age-group-venue-default">Use these venues as the default for this age group and gender</label>
  <div class="form-text">Apply to every matching squad and draw in this event, including future draws. Saved bookings, court allocations, and locked or published draws must be reviewed before changing their venues.</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('venuesForm');
  const checkbox = document.getElementById('age-group-venue-default');
  if (!form || !checkbox) return;
  document.addEventListener('click', function (event) {
    if (event.target.closest('.btn-add-venues')) checkbox.checked = false;
  });
  // Capture only the bulk-default action; the existing modal handles single-draw saves.
  form.addEventListener('submit', async function (event) {
    if (!checkbox.checked) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Could not save venue defaults.');
      window.location.reload();
    } catch (error) {
      if (window.toastr) window.toastr.error(error.message);
      else window.alert(error.message);
    } finally { button.disabled = false; }
  }, true);
});
</script>
