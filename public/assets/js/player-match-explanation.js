(() => {
  const closePanel = panel => {
    const figure = panel.closest('[data-match-progress-chart]');
    panel.hidden = true;
    const button = figure.querySelector('button[aria-controls="' + panel.id + '"]');
    button?.setAttribute('aria-expanded', 'false'); button?.focus();
  };
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      const panel = event.target.closest('[data-match-explanation]');
      if (panel && !panel.hidden) { event.preventDefault(); closePanel(panel); }
    }
  });
  document.addEventListener('click', event => {
    const close = event.target.closest('[data-match-explanation-close]');
    if (close) {
      const panel = close.closest('[data-match-explanation]');
      closePanel(panel);
      return;
    }
    const trigger = event.target.closest('[data-match-explanation-target]');
    if (!trigger) return;
    const figure = trigger.closest('[data-match-progress-chart]');
    const target = figure?.querySelector('#' + trigger.dataset.matchExplanationTarget);
    if (!target) return;
    if (!target.hidden) { closePanel(target); return; }
    figure.querySelectorAll('[data-match-explanation]').forEach(panel => { panel.hidden = panel !== target; });
    figure.querySelectorAll('button[data-match-explanation-target]').forEach(button => {
      button.setAttribute('aria-expanded', String(button.dataset.matchExplanationTarget === target.id));
    });
    target.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  });
})();
