/**
 * bracket-toolbar.js
 * Handles bracket admin toolbar actions:
 *   - Generate main bracket
 *   - Generate plate/2nd-3rd bracket
 *
 * Expected globals: AdminCore, window.drawId
 * Emits: `bracket:generated` after successful generation.
 */
import AdminCore from '../core/index.js';

const BracketToolbar = (() => {
  function _generateUrl(type) {
    const map = {
      main:  `/draws/${window.drawId}/generate-main-bracket`,
      plate: `/draws/${window.drawId}/generate-second-third-bracket`,
    };
    return map[type] || null;
  }

  function _generate(type, label) {
    AdminCore.confirm.show(
      `Generate ${label}`,
      `This will overwrite any existing ${label} fixtures. Continue?`,
      () => {
        const url = _generateUrl(type);
        if (!url) return AdminCore.toast.error('Unknown bracket type.');

        AdminCore.loading.show();

        $.ajax({
          url,
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        })
          .done((resp) => {
            if (!resp.success) {
              AdminCore.toast.error(resp.message || `Failed to generate ${label}.`);
              return;
            }
            AdminCore.toast.success(`${label} generated.`);
            $(document).trigger('bracket:generated', [type, resp]);
          })
          .fail((xhr) => {
            const msg = xhr.responseJSON?.message || `Error generating ${label}.`;
            AdminCore.toast.error(msg);
          })
          .always(() => AdminCore.loading.hide());
      }
    );
  }

  function init() {
    $(document).on('click', '[data-action="generate-main-bracket"]', () => _generate('main', 'Main Playoff'));
    $(document).on('click', '[data-action="generate-plate-bracket"]', () => _generate('plate', 'Plate Playoff'));
  }

  return { init };
})();

export default BracketToolbar;
