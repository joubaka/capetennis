<style>
  .ct-install-app {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 1080;
    display: none;
    align-items: center;
    gap: .5rem;
    border: 0;
    border-radius: 999px;
    padding: .7rem 1rem;
    color: #fff;
    background: #12358f;
    box-shadow: 0 .4rem 1.2rem rgba(18, 53, 143, .3);
    font-weight: 600;
  }
  .ct-install-app:hover,
  .ct-install-app:focus { background: #0b276f; color: #fff; }
  .ct-install-app.is-visible { display: inline-flex; }
  @media (max-width: 575.98px) {
    .ct-install-app { right: .75rem; bottom: .75rem; }
  }
</style>

<button type="button" class="ct-install-app" id="ct-install-app" aria-haspopup="dialog">
  <i class="ti ti-device-mobile-down" aria-hidden="true"></i>
  <span>Install Cape Tennis</span>
</button>

<div class="modal fade" id="ct-install-help" tabindex="-1" aria-labelledby="ct-install-help-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ct-install-help-title">Install Cape Tennis</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Add Cape Tennis to your home screen so it opens like an app.</p>
        <ol class="mb-0 ps-3" id="ct-install-steps"></ol>
      </div>
    </div>
  </div>
</div>

<script>
  (() => {
    const installButton = document.getElementById('ct-install-app');
    const installSteps = document.getElementById('ct-install-steps');
    let installPrompt = null;
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('{{ asset('service-worker.js') }}').catch(() => {});
      });
    }

    if (standalone) return;

    const showHelp = () => {
      const steps = isIos
        ? ['Tap the Share button in Safari.', 'Choose Add to Home Screen.', 'Tap Add.']
        : ['Open your browser menu.', 'Choose Install app or Add to home screen.', 'Confirm Install.'];
      installSteps.replaceChildren(...steps.map(step => {
        const item = document.createElement('li');
        item.textContent = step;
        return item;
      }));
      bootstrap.Modal.getOrCreateInstance(document.getElementById('ct-install-help')).show();
    };

    window.addEventListener('beforeinstallprompt', event => {
      event.preventDefault();
      installPrompt = event;
      installButton.classList.add('is-visible');
    });

    if (isIos) installButton.classList.add('is-visible');

    installButton.addEventListener('click', async () => {
      if (!installPrompt) return showHelp();
      installPrompt.prompt();
      await installPrompt.userChoice;
      installPrompt = null;
      installButton.classList.remove('is-visible');
    });

    window.addEventListener('appinstalled', () => installButton.classList.remove('is-visible'));
  })();
</script>
