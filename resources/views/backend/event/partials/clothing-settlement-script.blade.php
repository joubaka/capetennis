<script>
(function () {
  const forms = document.querySelectorAll('[data-clothing-settlement-form]');
  forms.forEach(form => {
    const kind = form.querySelector('[name="kind"]');
    const syncFields = () => {
      form.querySelectorAll('[data-clothing-offset-field]').forEach(field => {
        const enabled = kind.value === 'offset';
        field.hidden = !enabled;
        field.querySelectorAll('input, select').forEach(input => {
          input.disabled = !enabled;
          input.required = enabled;
        });
      });
      form.querySelectorAll('[data-clothing-overpayment-field]').forEach(field => {
        const enabled = kind.value === 'payout';
        field.hidden = !enabled;
        field.querySelectorAll('input, textarea').forEach(input => input.disabled = !enabled);
      });
    };
    kind.addEventListener('change', syncFields);
    syncFields();
    form.addEventListener('submit', event => {
      if (form.dataset.submitting) { event.preventDefault(); return; }
      form.dataset.submitting = '1';
      form.querySelector('[type="submit"]').disabled = true;
    });
    window.addEventListener('pageshow', () => {
      delete form.dataset.submitting;
      form.querySelector('[type="submit"]').disabled = false;
      syncFields();
    });
  });
})();
</script>
