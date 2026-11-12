(() => {
  const root = document.querySelector('[data-roster-report]');
  if (!root) return;
  const el = (s) => root.querySelector(s);
  const recipients = el('[data-recipients]');
  let snapshot = null;
  let busy = false;
  let compositionVersion = 0;
  const status = (text) => {
    const target = el('[data-status]');
    target.className = 'mt-2';
    target.textContent = text;
  };
  const feedback = (type, title, message) => {
    const target = el('[data-status]');
    target.className = `alert alert-${type === 'error' ? 'danger' : type} mt-3`;
    const heading = document.createElement('strong');
    heading.className = 'd-block'; heading.textContent = title;
    const detail = document.createElement('span'); detail.className = 'd-block'; detail.textContent = message;
    target.replaceChildren(heading, detail);
    target.scrollIntoView({block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
    if (window.toastr?.[type]) window.toastr[type](message, title, {
      closeButton: true, progressBar: true, timeOut: 8000, extendedTimeOut: 2000,
      escapeHtml: true, positionClass: 'toast-top-right',
    });
  };
  const invalidate = () => { compositionVersion += 1; snapshot = null; el('[data-review]').hidden = true; };
  const compositionFields = {subject: el('[data-subject]'), message: el('[data-message]'), from_name: el('[data-from-name]'), from_address: el('[data-from-address]'), reply_to: el('[data-reply-to]')};
  const composition = () => Object.fromEntries(Object.entries(compositionFields).map(([key, field]) => [key, field.value]));
  Object.values(compositionFields).forEach((field) => ['input', 'change'].forEach((event) => field.addEventListener(event, () => {
    invalidate(); status('Email changed. Preview it again before sending.');
  })));
  async function request(url, data) {
    const response = await fetch(url, {method: data ? 'POST' : 'GET', headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, ...(data ? {body: JSON.stringify(data)} : {})});
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Request failed. Please try again.');
    return result;
  }
  async function run(action, {loadingText = 'Loading…', onError} = {}) {
    if (busy) return;
    busy = true;
    root.querySelectorAll('button').forEach((b) => { b.disabled = true; });
    status(loadingText);
    try { await action(); } catch (error) { if (onError) onError(error); else status(error.message); }
    finally { busy = false; root.querySelectorAll('button').forEach((b) => { b.disabled = false; }); }
  }
  if (!window.jQuery?.fn.select2) {
    status('Recipient search could not load. Reload the page to try again.');
    el('[data-preview]').disabled = true;
    return;
  }
  const $recipients = window.jQuery(recipients);
  $recipients.select2({
    width: '100%',
    placeholder: 'Search account name or email',
    minimumInputLength: 2,
    maximumInputLength: 80,
    maximumSelectionLength: 30,
    tags: false,
    ajax: {
      url: root.dataset.url,
      dataType: 'json',
      delay: 350,
      headers: {Accept: 'application/json'},
      data: (params) => ({q: params.term}),
      transport: (params, success, failure) => {
        const search = window.jQuery.ajax(params);
        search.then(success).fail((response, reason) => {
          if (reason !== 'abort') {
            el('[data-recipient-status]').textContent = response.status === 401 || response.status === 419
              ? 'Your session expired. Reload the page and sign in again.'
              : response.status === 403 ? 'You no longer have access to search recipients.'
                : 'Recipient search failed. Please try again.';
          }
          failure(response);
        });
        return search;
      },
      processResults: (data) => {
        el('[data-recipient-status]').textContent = data.users.length === 20
          ? 'Showing the first 20 matches. Refine your search to find other accounts.' : '';
        return {results: data.users.map((user) => ({id: user.id, text: `${user.name} <${user.email}>`}))};
      },
    },
    language: {
      noResults: () => 'No matching accounts found.',
      maximumSelected: () => 'Choose at most 30 recipients.',
      errorLoading: () => 'Recipient search failed. Please try again.',
    },
  });
  $recipients.on('change', () => {
    invalidate(); status('Recipients changed. Preview the email and PDF again before sending.');
  });
  el('[data-preview]').addEventListener('click', () => run(async () => {
    invalidate();
    if (!Object.values(compositionFields).every((field) => field.reportValidity())) throw new Error('Complete the sender, subject and message before previewing.');
    const version = compositionVersion;
    const reviewed = await request(root.dataset.previewUrl, {recipient_ids: Array.from(recipients.selectedOptions, (option) => Number(option.value)), ...composition()});
    if (version !== compositionVersion) { status('The email or recipients changed while preparing the preview. Preview it again before sending.'); return; }
    snapshot = reviewed;
    el('[data-reviewed-recipients]').textContent = `To: ${snapshot.recipients.map((u) => `${u.name} <${u.email}>`).join(', ')}`;
    el('[data-reviewed-subject]').textContent = snapshot.subject;
    el('[data-reviewed-sender]').textContent = `From: ${snapshot.from_name} <${snapshot.from_address}> · Reply-to: ${snapshot.reply_to}`;
    el('[data-email]').textContent = snapshot.message; el('[data-pdf]').src = snapshot.pdf_url; el('[data-pdf-link]').href = snapshot.pdf_url;
    el('[data-review]').hidden = false; status('Review both previews. Sending shares a private copy with these recipients.');
  }));
  el('[data-send]').addEventListener('click', () => run(async () => {
    if (!snapshot) throw new Error('Prepare the preview first.');
    const button = el('[data-send]');
    const originalText = button.textContent;
    button.textContent = 'Queueing email and PDF…'; button.setAttribute('aria-busy', 'true');
    try {
      const data = await request(snapshot.send_url, composition());
      invalidate();
      if (data.queued > 0) {
        feedback('success', 'Report queued', `Email and PDF queued for ${data.queued} ${data.queued === 1 ? 'recipient' : 'recipients'}. Delivery is pending.`);
      } else {
        feedback('info', 'No new emails queued', 'This reviewed report was already queued. No additional emails were added.');
      }
    } finally {
      button.textContent = originalText; button.removeAttribute('aria-busy');
    }
  }, {
    loadingText: 'Queueing the reviewed email and PDF…',
    onError: (error) => feedback('error', 'Could not queue report', `${error.message} Queueing was not confirmed. You can retry the reviewed send safely.`),
  }));
})();
