const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.resolve(__dirname, '../../public/js/scoring-playoff-prompt.js'), 'utf8');

function scenario(confirm = true) {
    const after = {drawName: 'Example draw', total: 12, completed: 12, pending: true, url: '/progress', reviewUrl: '/review'};
    const prompts = [], reviews = [];
    const context = {window: {}, document: {querySelector: () => ({dataset: {playoffProgression: JSON.stringify({7: after})}})},
        Swal: {fire: async options => { prompts.push(options); return {isConfirmed: confirm}; }}};
    context.window.CTDrawProgression = {review: async options => {reviews.push(options); await options.onSuccess();}};
    vm.runInNewContext(source, context);
    return {api: context.window.CTScoringPlayoffs, after, prompts, reviews};
}
const match = {drawId: '7', engine: 'standard', stage: 'RR', hasResult: '0'};
const before = {total: 12, completed: 11, pending: true};

test('last saved round robin result prompts once and reuses the standings review before refresh', async () => {
    const app = scenario(); let refreshed = 0;
    await app.api.afterSave(match, before, async () => refreshed++);
    await app.api.afterSave(match, before, async () => refreshed++);
    assert.equal(app.prompts.length, 1); assert.equal(app.reviews.length, 1); assert.equal(refreshed, 1);
    assert.equal(app.reviews[0].reviewUrl, '/review'); assert.equal(app.reviews[0].url, '/progress');
    assert.match(app.prompts[0].text, /score is saved/);
});

test('cancel leaves the score saved and does not progress or refresh', async () => {
    const app = scenario(false); let refreshed = false;
    await app.api.afterSave(match, before, async () => refreshed = true);
    assert.equal(app.prompts.length, 1); assert.equal(app.reviews.length, 0); assert.equal(refreshed, false);
});

test('playoff, team, unauthorized and incomplete or resolved draws do not prompt', async () => {
    for (const [changedMatch, changedBefore, update] of [
        [{stage: 'MAIN'}, {}, {}], [{engine: 'team'}, {}, {}], [{}, {}, {pending: false}],
        [{drawId: '99'}, {}, {}], [{}, {}, {completed: 11}],
    ]) {
        const app = scenario(); Object.assign(app.after, update);
        await app.api.afterSave({...match, ...changedMatch}, {...before, ...changedBefore}, async () => {});
        assert.equal(app.prompts.length, 0);
    }
});

test('a final result saved over existing scores still offers pending progression', async () => {
    const app = scenario();
    await app.api.afterSave({...match, hasResult: '1'}, {...before, completed: 12}, async () => {});
    assert.equal(app.prompts.length, 1);
    assert.equal(app.reviews.length, 1);
});
