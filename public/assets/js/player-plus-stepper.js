(() => {
  'use strict';
  const page = document.querySelector('[data-plus-player-page]');
  const choices = page?.querySelector('[data-plus-plan-choices]');
  if (!choices) return;
  const forms = [...page.querySelectorAll('[data-plus-plan]')];
  const steps = [...page.querySelectorAll('[data-plus-step]')];
  const backStep = page.querySelector('[data-plus-step-back]');
  let pendingButton;
  let pendingLabel;
  forms.forEach(form => {
    form.addEventListener('submit', event => {
      if (pendingButton) {
        event.preventDefault();
        return;
      }
      if (!form.querySelector('[name="recurring_consent"]')) return;
      pendingButton = form.querySelector('button[type="submit"], button:not([type])');
      pendingLabel = pendingButton.textContent;
      pendingButton.disabled = true;
      pendingButton.textContent = 'Opening PayFast…';
    });
  });
  window.addEventListener('pageshow', event => {
    if (!event.persisted || !pendingButton) return;
    pendingButton.disabled = false;
    pendingButton.textContent = pendingLabel;
    pendingButton = null;
  });
  function show(plan, focus = true) {
    if (pendingButton) return;
    const selected = forms.find(form => form.dataset.plusPlan === plan);
    choices.hidden = Boolean(selected);
    forms.forEach(form => {
      form.hidden = form !== selected;
      // Returning to plan choice must never carry consent into a different plan.
      if (form !== selected) form.querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = false; });
    });
    if (backStep) {
      backStep.hidden = !selected;
      backStep.nextElementSibling.hidden = Boolean(selected);
    }
    steps.forEach(step => {
      const number = Number(step.dataset.plusStep);
      const current = selected ? 4 : 3;
      step.dataset.complete = String(number < current);
      if (number === current) step.setAttribute('aria-current', 'step');
      else step.removeAttribute('aria-current');
    });
    if (focus) (selected || choices).querySelector('h2')?.focus();
  }
  page.querySelectorAll('[data-plus-choose]').forEach(button => {
    button.addEventListener('click', () => show(button.dataset.plusChoose));
  });
  page.querySelectorAll('[data-plus-wallet-alternative]').forEach(button => { button.hidden = false; });
  page.querySelectorAll('[data-plus-change-plan]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', () => show(null));
  });
  backStep?.addEventListener('click', () => show(null));
  // Server validation errors remain next to the chosen form and required consents.
  show(page.dataset.plusReviewPlan || null, false);
})();
