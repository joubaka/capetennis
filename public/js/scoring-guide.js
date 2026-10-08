(function () {
  function initialize() {
    const guide = document.getElementById('scoring-guide');
    if (!guide || !window.bootstrap?.Modal) return;
    const modal = bootstrap.Modal.getOrCreateInstance(guide);
    if (guide.dataset.automatic === '1') modal.show();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
  else initialize();
})();
