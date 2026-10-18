const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function reviewFlow(cancel = false) {
  let click;
  const posts = [], dialogs = [];
  const button = { disabled: false, data: {'review-url': '/review', url: '/progress', 'draw-name': 'Test draw'} };
  const document = {getElementById: () => ({checked: true})};
  const window = {location: {reload() {}}};
  function $(value) {
    if (typeof value === 'function') { value(); return; }
    let text = '';
    const chain = {
      ready(callback) { callback(); return chain; },
      on(_, selector, callback) { click = callback; return chain; },
      text(value) { text = String(value); return chain; },
      html() { return text.replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
      prop(name, value) { button[name] = value; return chain; },
      attr(name, value) { return value === undefined ? 'csrf' : chain; },
      removeAttr() { return chain; },
      data(name) { return button.data[name]; }
    };
    return chain;
  }
  $.get = async () => ({review: {snapshot: 'fresh-snapshot', groups: [
    {id: 11, name: '<Group A>', standings: [{position:1, player:'<Player>', played:1, wins:1, losses:0, sets_won:1, sets_lost:0, games_won:6, games_lost:2}]},
    {id: 12, name: 'Group B', standings: []}
  ]}});
  $.post = async (url, data) => { posts.push({url, data}); return {message:'Progressed'}; };
  const Swal = {showLoading() {}, showValidationMessage() {}, close() {}, async fire(options) {
    dialogs.push(options);
    if (options.preConfirm) options.preConfirm();
    return {isConfirmed: options.preConfirm ? !cancel : true};
  }};
  vm.runInNewContext(fs.readFileSync('public/js/draw-progression.js', 'utf8'), {$, document, window, Swal, toastr: {warning() {}, error() {}}});
  return {posts, dialogs, button, click: () => click.call(button)};
}

test('canonical progression requires reviewing every group and submits the returned snapshot', async () => {
  const flow = reviewFlow();
  assert.equal(flow.posts.length, 0);
  await flow.click();
  assert.equal(flow.posts.length, 1);
  assert.equal(flow.posts[0].url, '/progress');
  assert.deepEqual(Array.from(flow.posts[0].data.confirmed_group_ids), [11, 12]);
  assert.equal(flow.posts[0].data.standings_snapshot, 'fresh-snapshot');
  assert.equal(flow.posts[0].data._token, 'csrf');
  assert.equal(flow.dialogs[1].titleText, 'Confirm <Group A>');
  assert.match(flow.dialogs[1].html, /&lt;Player&gt;/);
  assert.equal(flow.button.disabled, false);
});

test('cancelled group review never progresses the draw', async () => {
  const flow = reviewFlow(true);
  await flow.click();
  assert.equal(flow.posts.length, 0);
  assert.equal(flow.button.disabled, false);
});
