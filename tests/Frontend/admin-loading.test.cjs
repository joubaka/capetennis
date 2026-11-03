const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function harness(shared = true) {
  const document = {createElement(tag) {return {tag, attributes: {}, children: [], setAttribute(k, v) {this.attributes[k] = v;}, append(...items) {this.children.push(...items);}};}, createTextNode(text) {return {textContent: text};}};
  let factoryCalls = 0;
  const window = {document};
  if (shared) window.CapeLoading = {spinner(options) {factoryCalls++; assert.equal(options.small, true); return {className: 'shared-spinner'};}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin/core/loading.js', 'utf8'), {window});
  function element(disabled = false) {
    return {content: '<strong>Save</strong>', children: [], props: {disabled}, attrs: {},
      html(value) {if (value === undefined) return this.content; this.content = value; return this;},
      prop(key, value) {if (value === undefined) return this.props[key]; this.props[key] = value; return this;},
      attr(key, value) {if (value === undefined) return this.attrs[key]; this.attrs[key] = value; return this;},
      removeAttr(key) {delete this.attrs[key]; return this;}, empty() {this.content = ''; this.children = []; return this;},
      append(child) {this.children.push(child); return this;}};
  }
  return {loader: window.AdminLoading, element, calls: () => factoryCalls};
}

test('admin button uses shared indicator, treats labels as text and restores prior disabled/busy state', () => {
  const h = harness(), button = h.element(true); button.attrs['aria-busy'] = 'false';
  const restore = h.loader.button(button, '<img src=x>');
  assert.equal(h.calls(), 1); assert.equal(button.props.disabled, true); assert.equal(button.attrs['aria-busy'], 'true');
  assert.equal(button.children[1].textContent, ' <img src=x>');
  restore(); assert.equal(button.props.disabled, true); assert.equal(button.content, '<strong>Save</strong>'); assert.equal(button.attrs['aria-busy'], 'false');
  const enabled = h.element(); h.loader.button(enabled)(); assert.equal(enabled.props.disabled, false); assert.equal(enabled.attrs['aria-busy'], undefined);
});

test('admin content status uses one shared indicator and clear removes busy state', () => {
  const h = harness(), target = h.element(); h.loader.spinner(target, '<b>Working</b>');
  const status = target.children[0]; assert.equal(status.attributes.role, 'status'); assert.equal(h.calls(), 1);
  assert.equal(status.children[1].textContent, '<b>Working</b>'); assert.equal(target.attrs['aria-busy'], 'true');
  h.loader.clear(target); assert.equal(target.children.length, 0); assert.equal(target.attrs['aria-busy'], undefined);
});

test('admin fallback retains canonical ring and source/public helpers stay identical', () => {
  const h = harness(false), target = h.element(); h.loader.spinner(target);
  const spinner = target.children[0].children[0]; assert.equal(spinner.className, 'ct-loading-spinner ct-loading-spinner--small');
  assert.equal(spinner.attributes['aria-hidden'], 'true');
  for (const file of ['loading.js', 'modal.js']) assert.equal(fs.readFileSync('resources/js/admin/core/' + file, 'utf8'), fs.readFileSync('public/assets/js/admin/core/' + file, 'utf8'));
});
