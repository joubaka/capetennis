const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

async function deletion({confirmed = true, response = {success: true}, failed = false} = {}) {
  const template = fs.readFileSync('resources/views/frontend/event/show.blade.php', 'utf8');
  const start = template.indexOf("$(document).on('click', '.deleteFileButton'");
  const source = template.slice(start, template.indexOf('</script>', start))
    .replace("{{ route('file.destroy', '__ID__') }}", '/file/__ID__');
  let handler;
  const posts = [];
  const alerts = [];
  let reloads = 0;
  const document = {};
  const $ = (target) => target === document
    ? {on: (_, selector, callback) => {assert.equal(selector, '.deleteFileButton'); handler = callback;}}
    : {data: () => 42, attr: () => 'test-csrf'};
  $.post = (url, payload) => {
    posts.push({url, payload});
    return {done(callback) {
      if (!failed) callback(response);
      return {fail(callback) {if (failed) callback(response);}};
    }};
  };
  const Swal = {fire: (...args) => {alerts.push(args); return Promise.resolve({isConfirmed: confirmed});}};
  vm.runInNewContext(source, {$, document, Swal, location: {reload: () => {reloads++;}}});
  handler.call({}, {preventDefault() {}});
  await Promise.resolve();
  return {posts, alerts, reloads};
}

test('confirmation sends one CSRF-protected delete and reloads only on success', async () => {
  const result = await deletion();
  assert.equal(result.posts.length, 1);
  assert.equal(result.posts[0].url, '/file/42');
  assert.equal(result.posts[0].payload._method, 'DELETE');
  assert.equal(result.posts[0].payload._token, 'test-csrf');
  assert.equal(result.reloads, 1);
});

test('cancellation sends no request', async () => {
  const result = await deletion({confirmed: false});
  assert.equal(result.posts.length, 0);
  assert.equal(result.reloads, 0);
});

test('rejected deletion and network errors keep the page and explain failure', async () => {
  for (const input of [
    {response: {success: false, msg: 'Deletion refused'}},
    {failed: true, response: {status: 403, responseJSON: {msg: 'Forbidden'}}},
    {failed: true, response: {status: 0}},
  ]) {
    const result = await deletion(input);
    assert.equal(result.posts.length, 1);
    assert.equal(result.reloads, 0);
    assert.equal(result.alerts.length, 2);
    assert.equal(result.alerts[1][0], 'Error');
    assert.equal(result.alerts[1][2], 'error');
  }
});
