const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/views/backend/team-fixtures/partials/participant-revision-script.blade.php', 'utf8').replace(/<\/?script>/g, '');
let listener;
const forms = new Map(['editScoreForm', 'scoreForm'].map(id => [id, {
  field: null,
  querySelector() { return this.field; },
  append(field) { this.field = field; }
}]));
const document = {
  addEventListener(type, callback, capture) { assert.equal(type, 'click'); assert.equal(capture, true); listener = callback; },
  getElementById(id) { return forms.get(id); },
  createElement() { return {}; }
};
vm.runInNewContext(source, { document });
const click = (revision, admin = false) => listener({target: {closest() { return {
  dataset: {participantRevision: revision}, classList: {contains: () => admin}
}; }}});
click('a'.repeat(64));
assert.equal(forms.get('editScoreForm').field.value, 'a'.repeat(64));
assert.equal(forms.get('editScoreForm').field.name, 'participant_revision');
click('b'.repeat(64));
assert.equal(forms.get('editScoreForm').field.value, 'b'.repeat(64), 'Changing fixtures must replace the old modal token.');
click('c'.repeat(64), true);
assert.equal(forms.get('scoreForm').field.value, 'c'.repeat(64));
click(undefined);
assert.equal(forms.get('scoreForm').field.value, 'c'.repeat(64));
console.log('Team participant revision modal wiring: passed');
