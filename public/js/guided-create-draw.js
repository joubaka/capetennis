(function () {
  'use strict';
  document.querySelectorAll('[data-guided-create]').forEach(function (form) {
    var details = form.querySelector('[data-create-guide-details]');
    var next = form.querySelector('[data-create-guide-continue]');
    var back = form.querySelector('[data-create-guide-back]');
    var submit = form.querySelector('[data-create-guide-submit]');
    if (!details || !next || !back || !submit) return;
    function showDetails(show) {
      details.hidden = !show; next.hidden = show; back.hidden = !show; submit.hidden = !show;
      form.querySelector('[data-create-guide-status]').textContent = show ? 'Step 2 of 6 · Players / category' : 'Step 1 of 6 · Competition';
      form.querySelector('[data-create-guide-intro]').hidden = show;
      if (show) details.querySelector('select, input:not([type="hidden"])')?.focus();
    }
    next.addEventListener('click', function () { showDetails(true); });
    back.addEventListener('click', function () { showDetails(false); });
    form.addEventListener('submit', function (event) { if (details.hidden) { event.preventDefault(); event.stopImmediatePropagation(); showDetails(true); } }, true);
    showDetails(false);
  });
})();
