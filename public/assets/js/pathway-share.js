(function () {
  'use strict';
  document.addEventListener('click', async function (event) {
    if (event.target.closest('[data-pathway-print]')) {
      window.print();
      return;
    }
    const button = event.target.closest('[data-pathway-copy]');
    if (!button) return;
    const kit = button.closest('[data-pathway-share]');
    const message = kit.querySelector('textarea');
    const status = kit.querySelector('[data-pathway-share-status]');
    try {
      await navigator.clipboard.writeText(message.value);
      status.textContent = 'Message copied. Review it in your chosen app before sending.';
    } catch (_) {
      message.focus();
      message.select();
      status.textContent = 'Select and copy the message above using your device’s copy command.';
    }
  });
})();
