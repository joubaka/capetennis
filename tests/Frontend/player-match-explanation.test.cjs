const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('delegated grid selection opens only one saved panel, toggles and returns keyboard focus', () => {
  const handlers = {};
  let focused = null;
  const panels = [0, 1].map(id => ({id: 'pair-' + id, hidden: true, focus() { focused = this; }, scrollIntoView() {}}));
  const buttons = panels.map(panel => ({dataset: {matchExplanationTarget: panel.id}, expanded: 'false', setAttribute(_name, value) { this.expanded = value; }, focus() { focused = this; }}));
  const figure = {
    querySelector(selector) { return selector.startsWith('#') ? panels.find(p => '#' + p.id === selector) : buttons.find(b => selector.includes('"' + b.dataset.matchExplanationTarget + '"')); },
    querySelectorAll(selector) { return selector === '[data-match-explanation]' ? panels : buttons; }
  };
  panels.forEach(panel => {panel.closest = () => figure;});
  buttons.forEach(button => {button.closest = () => figure;});
  const document = {addEventListener(name, callback) {handlers[name] = callback;}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/player-match-explanation.js', 'utf8'), {document});
  const click = button => handlers.click({target: {closest: selector => selector === '[data-match-explanation-target]' ? button : null}});
  click(buttons[0]); assert.equal(panels[0].hidden, false); assert.equal(buttons[0].expanded, 'true'); assert.equal(focused, panels[0]);
  click(buttons[1]); assert.equal(panels[0].hidden, true); assert.equal(panels[1].hidden, false); assert.equal(buttons[0].expanded, 'false');
  let prevented = false;
  handlers.keydown({key: 'Escape', target: {closest: () => panels[1]}, preventDefault() {prevented = true;}});
  assert.equal(prevented, true); assert.equal(panels[1].hidden, true); assert.equal(focused, buttons[1]); assert.equal(buttons[1].expanded, 'false');
  click(buttons[0]); click(buttons[0]); assert.equal(panels[0].hidden, true); assert.equal(focused, buttons[0]);
  // The delegated document listener also works for a newly rendered fragment's trigger.
  const arrow = {dataset: {matchExplanationTarget: panels[1].id}, closest: () => figure};
  click(arrow); assert.equal(panels[1].hidden, false);
  handlers.click({target: {closest: selector => selector === '[data-match-explanation-close]' ? {closest: () => panels[1]} : null}});
  assert.equal(panels[1].hidden, true); assert.equal(focused, buttons[1]);
});
