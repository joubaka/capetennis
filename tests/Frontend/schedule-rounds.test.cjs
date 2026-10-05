const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
const logic = source.slice(source.indexOf('  const readDrawRounds ='), source.indexOf('  const buildScheduleDraft ='));
function client() {
  const choices = [1, 2].flatMap(draw => ['all', '1', '2', '3'].map(value => ({value, checked:value === 'all', dataset:{draw:String(draw)}, addEventListener(type, callback) { this.change = callback; }})));
  let dirty = 0;
  const document = {querySelectorAll(selector) {
    const match = selector.match(/data-draw="(\d+)"/);
    return choices.filter(input => (!match || input.dataset.draw === match[1]) && (!selector.includes(':checked') || input.checked));
  }};
  const read = new Function('document', 'markScheduleDirty', `${logic}; return readDrawRounds;`)(document, () => dirty++);
  return {choices, read, dirty:() => dirty, change(draw,value,checked) { const input=choices.find(input=>input.dataset.draw===String(draw)&&input.value===value); input.checked=checked; input.change(); }};
}
test('all rounds is the default and specific rounds are scoped to each draw', () => {
  const api = client();
  assert.deepEqual(api.read(), []);
  api.change(1,'1',true);
  api.change(1,'3',true);
  api.change(2,'2',true);
  assert.deepEqual(api.read(), [{draw_id:1, rounds:[1,3]}, {draw_id:2, rounds:[2]}]);
  assert.equal(api.choices.find(input=>input.dataset.draw==='1'&&input.value==='all').checked,false);
  assert.equal(api.dirty(),3);
});
test('all rounds clears specifics and clearing the last specific returns to all', () => {
  const api=client();api.change(1,'2',true);api.change(1,'all',true);
  assert.deepEqual(api.read(),[]);
  api.change(1,'3',true);api.change(1,'3',false);
  assert.deepEqual(api.read(),[]);
  assert.ok(source.includes('draw_rounds: readDrawRounds().filter(row => values(\'.draw-choice\').includes(row.draw_id))'));
  assert.ok(source.includes('.court-allocation, .draw-round-choice, .draw-start,'));
});
test('locked draw choices are omitted from allocation saves but retained for selected preview scope', () => {
  const api = client(); api.change(1,'1',true); api.change(2,'3',true);
  const editableDrawIds = [1];
  const savedExpression = source.slice(source.indexOf('  const buildScheduleDraft ='), source.indexOf('  const selectedAssignedVenueIds =')).match(/draw_rounds: (.+),/)[1];
  const saved = new Function('readDrawRounds', 'drawIds', `return ${savedExpression};`)(api.read, editableDrawIds);
  assert.deepEqual(saved, [{draw_id:1, rounds:[1]}]);
  const previewExpression = source.slice(source.indexOf('  const buildPayload ='), source.indexOf('  const post =')).match(/draw_rounds: (.+),/)[1];
  assert.deepEqual(new Function('readDrawRounds', 'values', `return ${previewExpression};`)(api.read, () => [2]), [{draw_id:2, rounds:[3]}]);
});
