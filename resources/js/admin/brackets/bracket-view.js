/**
 * bracket-view.js
 * Fetches bracket data from the canonical API and renders fixture cards
 * into the existing Blade bracket containers.
 *
 * Expected globals: AdminCore (from admin/core/index.js), window.drawId
 */
import AdminCore from '../core/index.js';

const BracketView = (() => {
  const STAGE_LABELS = {
    MAIN: 'Playoff',
    PLATE: 'Plate',
    CONS: 'Consolation',
    BOWL: 'Bowl',
    SHIELD: 'Shield',
    SPOON: 'Spoon',
  };

  function _url() {
    return `/api/draws/${window.drawId}/brackets`;
  }

  /**
   * Render a single fixture card inside the appropriate container.
   * Containers are expected to have `data-stage` and `data-round` attributes.
   */
  function _renderFixture(fx) {
    const selector = `[data-bracket-fixture="${fx.fixture_id}"]`;
    const $el = $(selector);
    if (!$el.length) return;

    const score = fx.score_summary || '';
    const winner = fx.winner_registration_id;

    const p1Class = winner && winner === fx.registration1_id ? 'fw-bold text-success' : '';
    const p2Class = winner && winner === fx.registration2_id ? 'fw-bold text-success' : '';

    $el.find('[data-player="1"]').text(fx.player1 || 'TBD').attr('class', p1Class);
    $el.find('[data-player="2"]').text(fx.player2 || 'TBD').attr('class', p2Class);
    $el.find('[data-score]').text(score);
    $el.toggleClass('fixture--complete', fx.match_status === 1);
  }

  /**
   * Render stage-level summary (heading/counter).
   */
  function _renderStageSummary(stage, fixtures) {
    const $heading = $(`[data-bracket-stage="${stage}"] .bracket-stage-title`);
    if ($heading.length) {
      const label = STAGE_LABELS[stage] || stage;
      const total = fixtures.length;
      const done = fixtures.filter(f => f.match_status === 1).length;
      $heading.text(`${label} — ${done}/${total} matches played`);
    }
  }

  /**
   * Load bracket data from the API and update all visible containers.
   */
  function load(stages) {
    const params = stages ? `?stages[]=${stages.join('&stages[]=')}` : '';
    AdminCore.loading.show();

    $.getJSON(_url() + params)
      .done((resp) => {
        if (!resp.success) {
          AdminCore.toast.error(resp.message || 'Failed to load bracket.');
          return;
        }
        const byStage = resp.stages || {};
        Object.entries(byStage).forEach(([stage, fixtures]) => {
          fixtures.forEach(_renderFixture);
          _renderStageSummary(stage, fixtures);
        });
      })
      .fail(() => AdminCore.toast.error('Error loading bracket data.'))
      .always(() => AdminCore.loading.hide());
  }

  return { load };
})();

export default BracketView;
