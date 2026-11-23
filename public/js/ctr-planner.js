(() => {
  'use strict';
  const placements = document.getElementById('ctr-placement-form');
  const confirm = document.getElementById('ctr-confirm');
  if (!placements || !confirm) return;
  let dirty = false;
  const markDirty = () => {
    dirty = true;
    confirm.disabled = true;
    confirm.textContent = 'Review changed placements first';
  };
  placements.addEventListener('input', markDirty);
  placements.addEventListener('change', markDirty);
  document.getElementById('ctr-confirm-form').addEventListener('submit', event => {
    if (dirty) { event.preventDefault(); placements.querySelector('button[type="submit"]').focus(); }
  });
})();
