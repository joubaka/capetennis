@php
  $auditInteractionEndpoint = \Illuminate\Support\Facades\Route::has('audit.interactions.store')
    ? route('audit.interactions.store')
    : null;
@endphp
<script>
(() => {
  const endpoint = @json($auditInteractionEndpoint);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!endpoint || !csrf || !navigator.sendBeacon) return;

  const clean = value => String(value || '').replace(/\s+/g, ' ').trim().slice(0, 160);
  const stableAction = element => {
    if (element.dataset.auditAction) return clean(element.dataset.auditAction).replace(/[^A-Za-z0-9._-]/g, '-');
    if (element.id) return `clicked.${clean(element.id).replace(/[^A-Za-z0-9._-]/g, '-')}`;
    if (element.name) return `clicked.${clean(element.name).replace(/[^A-Za-z0-9._-]/g, '-')}`;
    const href = element.getAttribute('href');
    if (href && !href.startsWith('javascript:') && href !== '#') {
      try {
        const url = new URL(href, window.location.href);
        return `navigate.${clean(url.pathname).replace(/[^A-Za-z0-9._/-]/g, '-')}`;
      } catch (_) {}
    }
    return `clicked.${element.tagName.toLowerCase()}`;
  };

  document.addEventListener('click', event => {
    const element = event.target.closest('button, a, [role="button"], input[type="submit"], input[type="button"]');
    if (!element || element.dataset.auditIgnore === 'true') return;

    let targetPath = null;
    const href = element.getAttribute('href');
    if (href && !href.startsWith('javascript:')) {
      try {
        const url = new URL(href, window.location.href);
        targetPath = url.origin === window.location.origin ? url.pathname : `[external] ${url.hostname}`;
      } catch (_) {}
    }

    const payload = new FormData();
    payload.append('_token', csrf);
    payload.append('action', stableAction(element).slice(0, 120));
    payload.append('label', clean(element.getAttribute('aria-label') || element.title || element.innerText || element.value));
    payload.append('element', element.tagName.toLowerCase());
    payload.append('page_path', window.location.pathname);
    payload.append('client_at', new Date().toISOString());
    payload.append('viewport_width', String(window.innerWidth));
    payload.append('viewport_height', String(window.innerHeight));
    if (targetPath) payload.append('target_path', targetPath);
    const orderId = element.dataset.auditOrderId || element.closest('[data-audit-order-id]')?.dataset.auditOrderId;
    if (orderId) payload.append('order_id', orderId);
    navigator.sendBeacon(endpoint, payload);
    if (element.form && (element.matches('button[type="submit"], input[type="submit"]') || element.tagName === 'BUTTON')) {
      element.form.dataset.auditClickRecorded = 'true';
    }
  }, {capture: true});

  const record = (action, element, extra = {}) => {
    if (!navigator.sendBeacon || !element || element.dataset.auditIgnore === 'true') return;
    const payload = new FormData();
    payload.append('_token', csrf);
    payload.append('action', action.slice(0, 120));
    payload.append('label', clean(element.getAttribute('aria-label') || element.title || element.innerText || element.name || element.id));
    payload.append('element', element.tagName.toLowerCase());
    payload.append('page_path', window.location.pathname);
    payload.append('client_at', new Date().toISOString());
    payload.append('viewport_width', String(window.innerWidth));
    payload.append('viewport_height', String(window.innerHeight));
    Object.entries(extra).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') payload.append(key, String(value));
    });
    navigator.sendBeacon(endpoint, payload);
  };

  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.auditIgnore === 'true') return;
    if (form.dataset.auditClickRecorded === 'true') return;
    const action = form.dataset.auditAction || 'form.submitted';
    const orderId = form.dataset.auditOrderId || form.querySelector('[name="order_id"], [name="custom_int5"]')?.value;
    record(action, form, {target_path: form.action ? new URL(form.action, window.location.href).pathname : null, order_id: orderId});
  }, {capture: true});

  document.addEventListener('change', event => {
    const element = event.target;
    if (!(element instanceof HTMLSelectElement) && !(element instanceof HTMLInputElement)) return;
    if (!['select-one', 'select-multiple', 'checkbox', 'radio'].includes(element.type)) return;
    // Record that a choice changed, never the selected value or typed content.
    record(element.dataset.auditAction || `field.changed.${clean(element.name || element.id || element.tagName).replace(/[^A-Za-z0-9._-]/g, '-')}`, element, {
      order_id: element.dataset.auditOrderId || element.closest('[data-audit-order-id]')?.dataset.auditOrderId
    });
  }, {capture: true});
})();
</script>
