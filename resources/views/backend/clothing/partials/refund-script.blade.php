<script>
document.querySelectorAll('[data-refund-mode]').forEach(select => {
  const update = () => select.form.querySelectorAll('[data-refund-field]').forEach(field => {
    const active = field.dataset.refundField === select.value;
    field.hidden = !active;
    field.querySelectorAll('input').forEach(input => { input.disabled = !active; input.required = active; });
  });
  const method = select.form.querySelector('[name="method"]');
  const bank = select.form.querySelector('[data-refund-bank]');
  const updateBank = () => {
    const active = method.value === 'payfast';
    bank.hidden = !active;
    bank.querySelectorAll('input,select').forEach(input => { input.disabled = !active; });
  };
  method.addEventListener('change', updateBank);
  updateBank();
  select.addEventListener('change', update);
  update();
});
</script>
