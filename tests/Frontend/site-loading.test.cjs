const test = require('node:test');
const assert = require('node:assert/strict');
const createLoading = require('../../public/assets/js/site-loading.js');
function harness() {
  const nodes = [];
  const focus = {isConnected: true, restored: 0, focus() {this.restored++;}};
  const document = {activeElement: focus, body: {append(node) {nodes.push(node);}}, createElement(tag) {
    return {tag, open: false, listeners: {}, children: [], setAttribute() {},
      addEventListener(name, cb) {this.listeners[name] = cb;}, append(...items) {this.children.push(...items);},
      showModal() {this.open = true;}, close() {this.open = false;}, focus() {}};
  }};
  return {loader: createLoading(document), nodes, focus};
}
test('overlapping requests keep one modal until the last handle closes and restore focus once', () => {
  const h = harness(), first = h.loader.show({title: 'First'}), second = h.loader.show({title: 'Second'});
  const dialog = h.nodes[0];
  assert.equal(h.nodes.length, 1); assert.equal(dialog.children[1].textContent, 'Second');
  first.close(); first.close();
  assert.equal(dialog.open, true); assert.equal(h.focus.restored, 0);
  second.close(); assert.equal(dialog.open, false); assert.equal(h.focus.restored, 1);
});
test('Escape only cancels the currently displayed request and labels remain plain text', () => {
  const h = harness(); let cancelled = 0;
  const first = h.loader.show({title: '<img src=x>', onCancel() {cancelled++;}});
  const second = h.loader.show({title: 'Non-cancellable'});
  const dialog = h.nodes[0]; let prevented = 0;
  dialog.listeners.cancel({preventDefault() {prevented++;}});
  assert.equal(cancelled, 0); assert.equal(prevented, 1);
  second.close(); assert.equal(dialog.children[1].textContent, '<img src=x>');
  assert.equal(dialog.children[3].hidden, false);
  dialog.listeners.cancel({preventDefault() {}}); assert.equal(cancelled, 1); first.close();
});

test('shared compact indicators are decorative and do not open a modal', () => {
  const h = harness(); const spinner = h.loader.spinner({small: true});
  assert.equal(spinner.className, 'ct-loading-spinner ct-loading-spinner--small');
  assert.equal(h.nodes.length, 0);
});
