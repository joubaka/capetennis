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
