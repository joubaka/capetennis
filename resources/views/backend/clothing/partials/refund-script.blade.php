<script>
document.querySelectorAll('[data-clothing-refund-selection]').forEach(form => {
  const mode = form.querySelector('[name="mode"]');
  const update = () => {
    const quantityMode = mode.value === 'quantity';
    form.querySelector('[data-refund-amount-fields]').hidden = quantityMode;
    form.querySelectorAll('[data-refund-amount-fields] input, [data-refund-amount-fields] select').forEach(input => { input.disabled = quantityMode; input.required = !quantityMode; });
    form.querySelectorAll('[data-refund-row]').forEach(row => {
      const checkbox = row.querySelector('[data-select-refund]');
      const quantity = row.querySelector('[data-refund-quantity]');
      quantity.disabled = !quantityMode || !checkbox.checked || checkbox.disabled;
      quantity.required = !quantity.disabled;
    });
  };
  form.querySelector('[data-refund-select-all]').addEventListener('click', () => {
    mode.value = 'quantity';
    form.querySelectorAll('[data-refund-row]').forEach(row => {
      const checkbox = row.querySelector('[data-select-refund]');
      if (!checkbox.disabled) {
        checkbox.checked = true;
        const quantity = row.querySelector('[data-refund-quantity]');
        quantity.value = quantity.max;
      }
    });
    update();
  });
  form.addEventListener('change', update);
  update();
});
</script>

<script>
document.querySelectorAll('[data-check-payfast]').forEach(form => {
  const button = form.querySelector('button');
  const original = button.textContent;
  form.addEventListener('submit', event => {
    if (form.dataset.submitting) { event.preventDefault(); return; }
    form.dataset.submitting = '1';
    button.disabled = true;
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm me-2';
    spinner.setAttribute('aria-hidden', 'true');
    button.replaceChildren(spinner, document.createTextNode('Checking PayFast…'));
    button.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', () => {
    delete form.dataset.submitting;
    button.disabled = false;
    button.removeAttribute('aria-busy');
    button.textContent = original;
  });
});
</script>
