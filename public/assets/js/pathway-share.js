(function () {
  'use strict';
  function revealHashTarget() {
    if (!window.location.hash) return;
    let target;
    try {
      target = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
    } catch (_) {
      return;
    }
    if (!target) return;
    for (let node = target; node; node = node.parentElement) {
      if (node.tagName === 'DETAILS') node.open = true;
    }
    requestAnimationFrame(function () { target.scrollIntoView({ block: 'start' }); });
  }
  window.addEventListener('hashchange', revealHashTarget);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', revealHashTarget);
  else revealHashTarget();

  let printDisclosure;
  let printWasOpen;
  window.addEventListener('beforeprint', function () {
    printDisclosure = document.querySelector('[data-pathway-share]')?.closest('details');
    printWasOpen = printDisclosure?.open;
    if (printDisclosure) printDisclosure.open = true;
  });
  window.addEventListener('afterprint', function () {
    if (printDisclosure) printDisclosure.open = printWasOpen;
  });
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
