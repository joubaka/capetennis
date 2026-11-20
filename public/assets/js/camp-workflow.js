(function () {
  'use strict';
  const root = document.querySelector('[data-camp-workflow]');
  if (!root) return;
  const reset = () => document.querySelectorAll('form[data-camp-busy]').forEach(form => {
    delete form.dataset.campBusy;
    form.removeAttribute('aria-busy');
    form.querySelectorAll('[data-camp-submit-copy]').forEach(input => input.remove());
    form.querySelectorAll('[data-camp-button-label]').forEach(button => {
      if (button.tagName === 'INPUT') button.value = button.dataset.campButtonLabel;
      else button.textContent = button.dataset.campButtonLabel;
      button.disabled = button.dataset.campWasDisabled === '1';
      delete button.dataset.campButtonLabel;
      delete button.dataset.campWasDisabled;
    });
  });
  window.addEventListener('pageshow', reset);
  document.addEventListener('submit', event => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!root.contains(form)) return;
    if (form.method.toLowerCase() !== 'post') return;
    if (form.dataset.campBusy) { event.preventDefault(); return; }
    if (!form.querySelector('input[name="_camp_form"]')) {
      const marker=document.createElement('input'); marker.type='hidden';marker.name='_camp_form';marker.value=form.action;form.append(marker);
    }
    const submitter = event.submitter;
    if (submitter?.name) {
      const copy = document.createElement('input');
      copy.type = 'hidden'; copy.name = submitter.name; copy.value = submitter.value;
      copy.dataset.campSubmitCopy = '1'; form.append(copy);
    }
    form.dataset.campBusy = '1'; form.setAttribute('aria-busy', 'true');
    form.querySelectorAll('button[type="submit"],button:not([type]),input[type="submit"]').forEach(button => {
      button.dataset.campButtonLabel = button.tagName === 'INPUT' ? button.value : button.textContent;
      button.dataset.campWasDisabled = button.disabled ? '1' : '0';
      button.disabled = true;
      if (button === submitter) {
        if (button.tagName === 'INPUT') button.value = 'Please wait…';
        else button.textContent = 'Please wait…';
      }
    });
  });
  root.addEventListener('invalid', event => {
    let parent = event.target.parentElement;
    while (parent && parent !== root) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
  }, true);
  root.addEventListener('change', event => {
    const form=event.target.form;
    if (form?.action.includes('/submissions/') && ['player_id','create_new','division_id'].includes(event.target.name)) {
      const confirmation=form.querySelector('[name="identity_confirmed"]');
      if (confirmation) confirmation.checked=false;
    }
  });
  document.querySelectorAll('[data-camp-select-all]').forEach(toggle => {
    toggle.addEventListener('change', () => {
      let count = 0;
      toggle.form.querySelectorAll('input[type="checkbox"]').forEach(input => {
        if (input.name === toggle.dataset.campSelectAll && !input.disabled && input.dataset.campReady !== '0') input.checked = toggle.checked && count++ < 200;
      });
      toggle.form.dispatchEvent(new Event('change', {bubbles:true}));
    });
  });
  document.querySelectorAll('form[data-camp-selection]').forEach(form => {
    const update = () => {
      const count = Array.from(form.querySelectorAll('input[type="checkbox"]')).filter(input => input.name===form.dataset.campSelection && input.checked && !input.disabled).length;
      const status = form.querySelector('[data-camp-selection-count]');
      if (status) status.textContent = count ? count+' players selected.' : 'Choose players to continue (up to 200 at once).';
      const button = form.querySelector('button:not([type]),button[type="submit"]');
      if (button) button.disabled = count===0;
    };
    form.addEventListener('change',event => {
      const confirmation = form.querySelector('[name="identity_confirmed"]');
      if (confirmation && event.target !== confirmation) confirmation.checked=false;
      update();
    }); window.addEventListener('pageshow',update); update();
  });
  document.querySelectorAll('[data-camp-copy]').forEach(button => button.addEventListener('click', async () => {
    const input = document.getElementById(button.dataset.campCopy);
    try { await navigator.clipboard.writeText(input.value); window.AppFeedback?.success('Coach link copied.'); }
    catch { input.focus(); input.select(); window.AppFeedback?.info('Select and copy this private link.'); }
  }));
  const invalid = document.querySelector('[aria-invalid="true"]');
  if (invalid) {
    let parent = invalid.parentElement;
    while (parent) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
    invalid.focus();
  }
}());
