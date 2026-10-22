<script>
(function () {
  const forms = document.querySelectorAll('[data-clothing-settlement-form]');
  forms.forEach(form => {
    const kind = form.querySelector('[name="kind"]');
    const recipient = form.querySelector('[name="recipient_user_id"]');
    if (window.jQuery && jQuery.fn.select2) {
      jQuery(recipient).select2({
        ajax: { url: recipient.dataset.usersUrl, dataType: 'json', delay: 250,
          data: params => ({ q: params.term }), processResults: data => ({ results: data }) },
        minimumInputLength: 2, placeholder: 'Search by name or email', width: '100%'
      });
    }
    const syncFields = () => {
      form.querySelector('[name="evidence"]').required = kind.value !== 'payout';
      form.querySelector('[name="evidence"]').minLength = kind.value === 'payout' ? 1 : 10;
      form.querySelector('[data-clothing-notes-optional]').hidden = kind.value !== 'payout';
      form.querySelector('[type="submit"]').textContent = kind.value === 'payout' ? 'Save payment' : 'Save adjustment';
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
