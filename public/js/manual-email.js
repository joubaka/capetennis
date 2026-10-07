(function () {
  'use strict';
  let nextId = 0;
  function sizeControls(root) {
    root.querySelectorAll('button, input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select').forEach(control => { control.style.minHeight = '44px'; });
    root.querySelectorAll('label:has(input[type="checkbox"])').forEach(label => { label.style.minHeight = '44px'; });
  }

  function modalFor(form) {
    const existing = form?.closest('.modal');
    if (existing) return existing;
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.id = 'manual-email-' + (++nextId);
    modal.setAttribute('aria-label', 'Compose and approve email');
    modal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"></div>';
    document.body.append(modal);
    return modal;
  }

  function errorIn(modal, message) {
    let alert = modal.querySelector('[data-mail-error]');
    if (!alert) {
      alert = document.createElement('div');
      alert.dataset.mailError = '';
      alert.className = 'alert alert-danger m-3';
      alert.setAttribute('role', 'alert');
      modal.querySelector('.modal-content').prepend(alert);
    }
    alert.textContent = message;
  }

  async function review(response, modal, compose) {
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The email could not be prepared. Please try again.');
    }
    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const fragment = doc.querySelector('[data-mail-compose-fragment] form');
    if (fragment) {
      if (compose) compose.hidden = true;
      modal.querySelector('[data-mail-review-panel]')?.remove();
      fragment.dataset.mailComposePanel = '';
      modal.querySelector('.modal-dialog').append(fragment);
      sizeControls(fragment);
      if (!compose) modal.querySelector('.modal-content:not([data-mail-compose-panel])')?.remove();
      fragment.addEventListener('submit', event => { event.preventDefault(); prepare(fragment, modal, fragment); });
      modal.addEventListener('hidden.bs.modal', () => { fragment.remove(); if (compose) compose.hidden = false; }, { once: true });
      bootstrap.Modal.getOrCreateInstance(modal).show();
      return;
    }
    const content = doc.querySelector('[data-mail-review]');
    if (!content) throw new Error('The email review could not be loaded. Refresh the page before trying again.');
    content.querySelectorAll('script').forEach(script => script.remove());
    const old = modal.querySelector('[data-mail-review-panel]');
    old?.remove();
    const panel = document.createElement('div');
    panel.className = 'modal-content';
    panel.dataset.mailReviewPanel = '';
    panel.innerHTML = '<div class="modal-header"><h5 class="modal-title">Review and queue email</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div>';
    panel.querySelector('.modal-body').append(content);
    sizeControls(panel);
    if (compose) {
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'btn btn-outline-secondary m-3 align-self-start';
      edit.textContent = 'Edit email';
      edit.addEventListener('click', () => { panel.remove(); compose.hidden = false; });
      panel.append(edit);
    }
    content.querySelectorAll('a').forEach(link => {
      if (/composer|editing|new send intent/i.test(link.textContent) && compose) {
        link.remove();
      }
    });
    // A changed ranking selection must be reviewed afresh before it can be approved.
    content.querySelectorAll('input[name="excluded_player_ids[]"]').forEach(input => input.addEventListener('change', () => {
      const approve = content.querySelector('#approve-ranking-mail');
      if (approve) { approve.disabled = true; approve.textContent = 'Update selection before approving'; }
    }));
    content.querySelectorAll('form').forEach(form => {
      if (form.action.endsWith('/preview')) {
        form.addEventListener('submit', event => { event.preventDefault(); prepare(form, modal, compose); });
      } else {
        form.addEventListener('submit', () => {
          form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; button.textContent = 'Queueing…'; });
        });
      }
    });
    if (compose) compose.hidden = true;
    else modal.querySelector('.modal-content:not([data-mail-review-panel]):not([data-mail-compose-panel])')?.remove();
    modal.querySelector('.modal-dialog').append(panel);
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  async function prepare(form, modal, compose) {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (button?.disabled) return;
    if (button) button.disabled = true;
    modal.querySelector('[data-mail-error]')?.remove();
    try {
      const body = new FormData(form);
      // Confirmation belongs to the final sample approval, not the composition step.
      body.set('confirm_recipients', '1');
      const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' } });
      await review(response, modal, compose);
    } catch (error) { errorIn(modal, error.message); }
    finally { if (button) button.disabled = false; }
  }

  window.CapeMailReview = {
    async open(url) {
      // Reuse the existing composer popup when a legacy button prepared the batch.
      const modal = document.querySelector('.modal.show') || modalFor();
      const compose = modal.querySelector('.modal-content');
      if (compose) {
        modal.addEventListener('hidden.bs.modal', () => {
          modal.querySelector('[data-mail-review-panel]')?.remove();
          compose.hidden = false;
        }, { once: true });
      }
      if (!compose) modal.querySelector('.modal-dialog').innerHTML = '<div class="modal-content"><div class="modal-body">Preparing email…</div></div>';
      bootstrap.Modal.getOrCreateInstance(modal).show();
      try { await review(await fetch(url, { headers: { Accept: 'text/html' } }), modal, compose); }
      catch (error) { errorIn(modal, error.message); }
      if (!compose) modal.querySelector('.modal-content:not([data-mail-review-panel]):not([data-mail-compose-panel])')?.remove();
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-mail-compose], form[data-mail-launch]').forEach(form => {
      form.dataset.mailBound = '';
      const alreadyModal = !!form.closest('.modal');
      const modal = modalFor(form);
      form.querySelectorAll('[name="confirm_recipients"]').forEach(input => {
        input.required = false;
        input.closest('.form-check')?.setAttribute('hidden', '');
      });
      if (form.hasAttribute('data-mail-launch') && !alreadyModal) {
        modal.querySelector('.modal-dialog').innerHTML = '<div class="modal-content"><div class="modal-body">Preparing email…</div></div>';
        form.addEventListener('submit', event => {
          event.preventDefault();
          if (!modal.querySelector('.modal-content')) modal.querySelector('.modal-dialog').innerHTML = '<div class="modal-content"><div class="modal-body">Preparing email…</div></div>';
          bootstrap.Modal.getOrCreateInstance(modal).show();
          prepare(form, modal, null);
        });
        return;
      }
      if (!alreadyModal) {
        const launch = document.createElement('button');
        launch.type = 'button';
        launch.className = 'btn btn-primary mb-3';
        launch.textContent = 'Write email';
        form.before(launch);
        if (!form.classList.contains('modal-content')) {
          form.classList.remove('card', 'card-body', 'mb-4');
          form.classList.add('modal-content');
          const body = document.createElement('div');
          body.className = 'modal-body';
          while (form.firstChild) body.append(form.firstChild);
          form.append(body);
          const heading = document.createElement('div');
          heading.className = 'modal-header mb-3';
          heading.innerHTML = '<h5 class="modal-title">Write email</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
          form.prepend(heading);
        }
        modal.querySelector('.modal-dialog').append(form);
        launch.addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(modal).show());
        if (new URLSearchParams(location.search).has('compose')) bootstrap.Modal.getOrCreateInstance(modal).show();
      }
      modal.querySelector('.modal-dialog').classList.add('modal-dialog-scrollable');
      sizeControls(modal);
      form.querySelectorAll('[name="confirm_recipients"]').forEach(input => {
        input.required = false;
        input.closest('.form-check')?.setAttribute('hidden', '');
      });
      modal.addEventListener('hidden.bs.modal', () => {
        modal.querySelector('[data-mail-review-panel]')?.remove();
        form.hidden = false;
      });
      form.addEventListener('submit', event => {
        if (event.submitter?.hasAttribute('formaction')) return;
        event.preventDefault();
        prepare(form, modal, form);
      });
    });
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!form.matches('form[data-mail-launch]:not([data-mail-bound])')) return;
    event.preventDefault();
    const modal = modalFor();
    modal.querySelector('.modal-dialog').innerHTML = '<div class="modal-content"><div class="modal-body">Preparing email…</div></div>';
    bootstrap.Modal.getOrCreateInstance(modal).show();
    prepare(form, modal, null);
  });
})();
