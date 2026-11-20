(function () {
  'use strict';

  // The shared composer moves the form into its modal before it is shown.
  document.addEventListener('shown.bs.modal', function (event) {
    const $ = window.jQuery;
    if (!$?.fn?.select2) return;

    event.target.querySelectorAll('[data-trials-mail-select]').forEach(function (select) {
      if ($(select).hasClass('select2-hidden-accessible')) return;
      $(select).select2({
        dropdownParent: $(event.target),
        width: '100%',
        placeholder: 'All — type to search',
        closeOnSelect: false
      });
    });
  });
})();
