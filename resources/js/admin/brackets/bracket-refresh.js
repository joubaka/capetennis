/**
 * bracket-refresh.js
 * Listens for bracket mutation events and triggers a view reload.
 * Decouples bracket-view from bracket-score and bracket-toolbar.
 *
 * Usage: import and call BracketRefresh.init() after BracketView.init().
 */
import BracketView from './bracket-view.js';

const BracketRefresh = (() => {
  function init(stages) {
    $(document).on('bracket:scoreUpdated bracket:generated', () => {
      BracketView.load(stages);
    });
  }

  return { init };
})();

export default BracketRefresh;
