const test = require('node:test');
const assert = require('node:assert/strict');
const integrate = require('../../public/assets/js/my-tennis-workspace.js');

test('embedded rating updates the persistent header snapshot and hides only its repeated hero', () => {
  const snapshot = {score: 66.6}, host = {replaceChildren(value) {this.child = value;}};
  const hero = {dataset: {}, hidden: false, querySelector: () => snapshot};
  const document = {querySelector: () => host}, panel = {querySelector: () => hero};
  assert.equal(integrate(document, panel), true);
  assert.equal(host.child, snapshot); assert.equal(hero.hidden, true);
  assert.equal(hero.dataset.embeddedRatingHero, '');
  const withheld = {reason: 'Results awaiting update'};
  hero.querySelector = () => withheld;
  integrate(document, panel); assert.equal(host.child, withheld);
});
test('standalone and incomplete fragments retain their own visible header', () => {
  const hero = {hidden: false, querySelector: () => null};
  assert.equal(integrate({querySelector: () => null}, {querySelector: () => hero}), false);
  assert.equal(integrate({querySelector: () => ({})}, {querySelector: () => hero}), false);
  assert.equal(hero.hidden, false);
});
