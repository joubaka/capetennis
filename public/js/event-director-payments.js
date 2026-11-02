(() => {
  const initialized = new WeakSet();
  const money = cents => `R ${(cents / 100).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

  function refresh(form) {
    const full = form.querySelector('[data-payment-mode]')?.value === 'full_net';
    const panel = form.querySelector('[data-net-settlement]');
    if (!panel) return;
    const amount = form.querySelector('[name="amount"]');
    const reference = form.querySelector('[name="reference"]');
    const button = form.querySelector('[data-payment-submit]');
    const date = form.querySelector('[name="paid_at"]');
    panel.hidden = !full;
    panel.querySelectorAll('input').forEach(input => {
      input.disabled = !full;
      input.required = full;
    });
    reference.required = full;
    form.querySelector('[data-reference-label]').textContent = full ? 'Payment reference' : 'Payment reference (optional)';
    date.closest('.col-md-6').hidden = full;
    date.disabled = full;
    amount.readOnly = full;
    button.disabled = false;
    button.textContent = full ? 'Record full net payment' : 'Record payment';
    form.querySelector('[data-funding-label]').textContent = full ? 'Registration entries and linked clothing reconciliation' : 'Registration entries';
    if (!full) {
      if (amount.dataset.netFilled) amount.value = '';
      delete amount.dataset.netFilled;
      return;
    }
    let preview = null;
    try { preview = JSON.parse(form.querySelector('[name="convenor_id"]').selectedOptions[0]?.dataset.settlement || 'null'); } catch (_) { /* No eligible preview. */ }
    const output = panel.querySelector('[data-net-preview]');
    output.replaceChildren();
    if (!preview?.available) {
      output.textContent = preview?.unavailable_reason || 'Choose a director with a positive, eligible net balance. Clothing deductions cannot exceed their registration balance.';
      amount.value = '';
      button.disabled = true;
      return;
    }
    const lines = [
      `Registration balance to settle: ${money(preview.registration_cents)}`,
      ...preview.clothing.map(leg => `${leg.region_name} clothing ${leg.contribution_cents < 0 ? 'deduction' : 'payable'}: ${money(leg.contribution_cents)}`),
      `Net amount already paid: ${money(preview.cash_cents)}`,
    ];
    if (preview.clothing.some(leg => leg.pending_cents > 0)) lines.push('Pending clothing refunds remain reserved and are excluded from this payment.');
    for (const text of lines) {
      const line = document.createElement('div');
      line.textContent = text;
      output.append(line);
    }
    amount.value = (preview.cash_cents / 100).toFixed(2);
    amount.dataset.netFilled = '1';
    panel.querySelector('[name="preview_token"]').value = preview.preview_token;
  }

  function initialize() {
    document.querySelectorAll('[data-director-payment-form]').forEach(form => {
      if (initialized.has(form)) return;
      initialized.add(form);
      refresh(form);
    });
  }
  document.addEventListener('change', event => {
    const form = event.target.closest('[data-director-payment-form]');
    if (form && (event.target.name === 'payment_mode' || event.target.name === 'convenor_id')) {
      const confirmation = form.querySelector('[name="settlement_confirmed"]');
      if (confirmation) confirmation.checked = false;
      refresh(form);
    }
  });
  initialize();
  new MutationObserver(initialize).observe(document.body, { childList: true, subtree: true });
})();
