<script>
document.querySelectorAll('[data-refund-mode]').forEach(select => {
  const form = select.form;
  const summary = form.querySelector('[data-refund-summary]');
  const quantity = form.querySelector('[data-refund-field="quantity"] input');
  const amount = form.querySelector('[name="amount"]');
  const cents = value => {
    if (!/^\d+(?:\.\d{1,2})?$/.test(value)) return null;
    const [whole, fraction = ''] = value.split('.');
    const result = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(result) ? result : null;
  };
  const updateSummary = () => {
    if (!summary) return;
    const remaining = Number(form.dataset.refundRemainingCents);
    let refundCents = remaining;
    let guidance = '';
    if (select.value === 'quantity') {
      const count = Number(quantity.value);
      const total = Number(form.dataset.refundTotalQuantity);
      const used = Number(form.dataset.refundUsedQuantity);
      const line = Number(form.dataset.refundLineCents);
      if (!/^\d+$/.test(quantity.value) || !Number.isSafeInteger(count) || count < 1 || count > Number(form.dataset.refundRemainingQuantity) || total <= 0) {
        guidance = Number(form.dataset.refundRemainingQuantity) < 1 ? 'No units remain available. Choose a refund amount instead.' : 'Enter a whole quantity between 1 and ' + form.dataset.refundRemainingQuantity + '.';
      } else {
        refundCents = Math.min(Math.floor(line * (used + count) / total) - Math.floor(line * used / total), remaining);
      }
    } else if (select.value === 'amount') {
      refundCents = cents(amount.value);
      if (refundCents === null || refundCents <= 0 || refundCents > remaining) guidance = 'Enter a valid rand amount within this item’s remaining amount, with no more than two decimal places.';
    } else if (select.value !== 'full') {
      guidance = 'Choose a refund size.';
    }
    if (!guidance && (!Number.isSafeInteger(refundCents) || refundCents <= 0 || refundCents > Number(form.dataset.refundOrderCents))) guidance = 'This refund exceeds the order’s remaining payment. Choose a smaller refund.';
    if (!guidance && method.value === 'payfast' && refundCents > Number(form.dataset.refundPayfastCents)) guidance = 'This refund exceeds the remaining PayFast payment. Choose a smaller refund or refund to the wallet.';
    if (guidance) {
      summary.textContent = guidance;
      return;
    }
    const money = value => 'R' + (value / 100).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const size = refundCents === Number(form.dataset.refundLineCents) ? 'Full item refund.' : (refundCents === remaining ? 'Full remaining item refund.' : 'Partial item refund.');
    summary.textContent = size + ' Refund amount: ' + money(refundCents) + (method.value === 'payfast' ? ' will be submitted to PayFast for the original payer.' : ' will be credited to the original payer’s wallet.') + ' ' + money(remaining - refundCents) + ' remains refundable for this item.';
  };
  const update = () => {
    form.querySelectorAll('[data-refund-field]').forEach(field => {
      const active = field.dataset.refundField === select.value;
      field.hidden = !active;
      field.querySelectorAll('input').forEach(input => { input.disabled = !active; input.required = active; });
    });
    updateSummary();
  };
  const method = select.form.querySelector('[name="method"]');
  const bank = select.form.querySelector('[data-refund-bank]');
  const updateBank = () => {
    const active = method.value === 'payfast';
    bank.hidden = !active;
    bank.querySelectorAll('input,select').forEach(input => { input.disabled = !active; });
  };
  method.addEventListener('change', updateBank);
  method.addEventListener('change', updateSummary);
  quantity.addEventListener('input', updateSummary);
  amount.addEventListener('input', updateSummary);
  updateBank();
  select.addEventListener('change', update);
  update();
});
</script>
