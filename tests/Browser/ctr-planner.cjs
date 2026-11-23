/* Actual fictional Blade exports; every HTTP request is fulfilled locally. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const output = path.join(root, 'storage/app/testing/ctr-planner');
const publicRoot = path.join(root, 'public');
const mime = file => ({ '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2', '.png': 'image/png' }[path.extname(file)] || 'application/octet-stream');
const fixture = state => path.join(output, ({ review: 'preview', excluded: 'excluded2' }[state] || state) + '.html');
const playerSelector = '[data-ctr-player]';
const groupSelector = '[data-ctr-group]';
const definitions = {
  review: { sizes: [4, 4, 3], competitions: 3, matches: 15 },
  unrated: { sizes: [4, 4, 3], competitions: 3, matches: 15 },
  mixed: { sizes: [5, 4], competitions: 2, matches: 16 },
  six: { sizes: [3, 3], competitions: 1, matches: 9 },
  ten: { sizes: [4, 3, 3], competitions: 2, matches: 15 },
  twenty: { sizes: [4, 4, 4, 4, 4], competitions: 5, matches: 30 },
};
function formFields(request) {
  const body = request.postData() || '';
  const type = request.headers()['content-type'] || '';
  const boundary = type.match(/boundary=(?:"([^"]+)"|([^;]+))/)?.slice(1).find(Boolean);
  if (!boundary) return new URLSearchParams(body);
  const fields = new URLSearchParams();
  for (const part of body.split('--' + boundary)) {
    const match = part.match(/name="([^"]+)"\r\n\r\n([\s\S]*?)\r\n$/);
    if (match) fields.append(match[1], match[2]);
  }
  return fields;
}
async function until(predicate, label, milliseconds = 8000) {
  const deadline = Date.now() + milliseconds;
  while (!predicate() && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 20));
  assert.ok(predicate(), label);
}
async function snapshot(page) {
  return page.locator(playerSelector).evaluateAll(nodes => nodes.map(node => ({
    id: node.dataset.entryId, category: node.dataset.categoryId,
    group: node.closest('[data-ctr-group]').dataset.groupId,
    value: node.querySelector('[data-placement]').value,
    score: node.dataset.score, component: node.dataset.component,
  })));
}
async function counts(page) {
  return page.locator(groupSelector).evaluateAll(nodes => nodes.map(node => node.querySelectorAll('[data-ctr-player]').length));
}
async function nativeDrag(page, source, destination) {
  await source.scrollIntoViewIfNeeded();
  const from = await source.boundingBox();
  await page.mouse.move(from.x + 20, from.y + 20);
  await page.mouse.down();
  // Begin native dragging before scrolling to a destination outside a phone viewport.
  await page.mouse.move(from.x + 30, from.y + 30, { steps: 5 });
  await destination.scrollIntoViewIfNeeded();
  const to = await destination.boundingBox();
  const x = to.x + 25, y = Math.max(160, to.y + 25);
  await page.mouse.move(x, y, { steps: 15 });
  await page.mouse.move(x + 3, y + 3);
  await page.mouse.up();
}
async function assertBoard(page, expectedIds) {
  const rows = await snapshot(page);
  assert.equal(new Set(rows.map(row => row.id)).size, rows.length, 'No duplicate player cards');
  if (expectedIds) assert.deepEqual(rows.map(row => row.id).sort(), [...expectedIds].sort(), 'Exact original player set retained');
  for (const row of rows) assert.equal(row.value, row.group, 'Submitted placement matches displayed group');
  assert.equal(await page.locator(playerSelector).evaluateAll(nodes => nodes.every(node => {
    const category = node.closest('[data-ctr-category]');
    const allowed = new Set([...category.querySelectorAll('[data-ctr-group]')].map(group => group.dataset.groupId));
    return [...node.querySelector('[data-placement]').options].every(option => allowed.has(option.value));
  })), true, 'Move options stay within the player category');
  const categories = await page.locator('[data-ctr-category]').evaluateAll(nodes => nodes.map(category => [...category.querySelectorAll('[data-ctr-group]')].map(group => Number(group.dataset.groupId))));
  for (const ids of categories) assert.deepEqual(ids, [...ids].sort((a, b) => a - b), 'Group DOM order is numeric');
  const grouped = new Map();
  for (const row of rows) { const key = `${row.category}:${row.group}`; if (!grouped.has(key)) grouped.set(key, []); grouped.get(key).push(row); }
  for (const members of grouped.values()) {
    let unknownSeen = false;
    for (const row of members) { if (row.score === '') unknownSeen = true; else assert.equal(unknownSeen, false, 'Unrated players appear after rated players'); }
    if (new Set(members.map(row => row.component).filter(Boolean)).size <= 1) {
      const scores = members.filter(row => row.score !== '').map(row => Number(row.score));
      assert.deepEqual(scores, [...scores].sort((a, b) => b - a), 'Comparable ratings descend within each group');
    }
  }
  return rows;
}
async function main() {
  const exclusionOnly = process.env.CT_CTR_BROWSER_EXCLUSION_ONLY === '1';
  const ajaxOnly = process.env.CT_CTR_BROWSER_AJAX_ONLY === '1';
  const ajaxExtrasOnly = process.env.CT_CTR_BROWSER_AJAX_EXTRAS_ONLY === '1';
  const states = ['initial', 'review', 'unrated', 'mixed', 'blocked', 'created', 'six', 'ten', 'twenty', 'shortfall', 'excluded', 'categories', 'sparse'];
  for (const state of states) assert.ok(fs.existsSync(fixture(state)), `Missing fictional export ${state}.html`);
  const exclusionStates = ['exclusion-flow-initial', 'exclusion-flow-reviewed'];
  for (const state of exclusionStates) assert.ok(fs.existsSync(fixture(state)), `Missing exclusion workflow export ${state}.html`);
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const report = [], submitted = [], missing = new Set(), errors = [];
  let exclusionActive = false;
  let reviewMode = 'success', unanswered = 0, maximumUnanswered = 0;
  const reviews = [], heldReviews = [], accepted = new Map();
  const reviewContract = JSON.parse(fs.readFileSync(path.join(output, 'exclusion-flow-reviewed.json'), 'utf8'));
  try {
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
      const request = route.request(), url = new URL(request.url());
      if (!['localhost', '127.0.0.1', 'ct.test'].includes(url.hostname)) return route.abort();
      if (request.method() !== 'GET') {
        if (/\/ctr-planner\//.test(url.pathname)) submitted.push({ path: url.pathname, method: request.method(), body: request.postData() });
        if (/\/ctr-planner\/review$/.test(url.pathname) && request.headers().accept?.includes('application/json')) {
          const fields = formFields(request), token = fields.get('token');
          const mode = reviewMode;
          const record = { fields, token, revision: fields.get('revision'), mode, responseRevision: null };
          reviews.push(record);
          unanswered++; maximumUnanswered = Math.max(maximumUnanswered, unanswered);
          const categoryData = await page.locator('[data-ctr-category]').evaluateAll(nodes => nodes.map(node => ({
            id: node.dataset.categoryId,
            straight: (node.dataset.straightGroups || '').split(',').filter(Boolean),
            entries: [...node.querySelectorAll('[data-ctr-player]')].map(player => player.dataset.entryId),
          })));
          if (mode === 'hold') await new Promise(resolve => heldReviews.push({ record, release: resolve }));
          const expectedRevision = accepted.get(token) || record.revision;
          let code = 200, response;
          if (record.revision !== expectedRevision || mode === '409') { code = 409; response = { message: 'The saved plan changed. Reload the saved plan before continuing.' }; }
          else if (['401', '403', '419', '422', '429', '500'].includes(mode)) { code = Number(mode); response = { message: code === 422 ? 'Review the highlighted placements before saving.' : 'Cannot save the current plan.', errors: code === 422 ? { placements: ['Review the highlighted placements before saving.'] } : undefined }; }
          else {
            const counts = new Map(), excluded = fields.getAll('excluded_categories[]');
            let competitions = 0, matches = 0, included = 0;
            for (const category of categoryData) {
              if (excluded.includes(category.id)) continue;
              for (const id of category.entries) { const key = `${category.id}:${fields.get(`placements[${id}]`)}`; counts.set(key, (counts.get(key) || 0) + 1); included++; }
              for (const [key, count] of counts) if (key.startsWith(category.id + ':')) {
                matches += count * (count - 1) / 2;
                if (!category.straight.includes(key.split(':')[1])) competitions++;
              }
              if (category.straight.length) { competitions++; matches += 3; }
            }
            const content = [...fields].filter(([name]) => !['_token', 'revision'].includes(name)).sort(([a], [b]) => a.localeCompare(b));
            record.responseRevision = crypto.createHash('sha256').update(JSON.stringify(content)).digest('hex');
            response = { ...reviewContract, saved: true, token, revision: record.responseRevision, ready: true,
              excluded_category_ids: excluded.map(Number), totals: { competitions, matches, included_players: included, winner_prizes: competitions, sportsmanship_prizes: competitions } };
            accepted.set(token, record.responseRevision);
          }
          unanswered--;
          if (mode === 'network') return route.abort('failed');
          if (mode === 'html') return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>Sign in</title><p>Your session expired.</p>' });
          if (mode === 'malformed') return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ saved: true, revision: 'invalid' }) });
          await route.fulfill({ status: code, contentType: 'application/json', headers: { 'Cache-Control': 'private, no-store' }, body: JSON.stringify(response) });
          record.delivered = true;
          return;
        }
        // Serve the actual reviewed HTML, then explicitly load its local GET fixture.
        // A mocked HTTP redirect bypasses Playwright routing for the redirect target.
        // The feature test owns the real POST/redirect/GET persistence assertion.
        if (exclusionActive && /\/ctr-planner\/review$/.test(url.pathname)) return route.fulfill({ path: fixture('exclusion-flow-reviewed'), contentType: 'text/html' });
        return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Fictional submission captured</title><p>Captured locally; no application endpoint called.</p>' });
      }
      const state = url.pathname.match(/^\/ctr-qa\/([a-z-]+)$/)?.[1];
      if (states.includes(state) || exclusionStates.includes(state)) return route.fulfill({ path: fixture(state), contentType: 'text/html' });
      const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname).replace(/^\/ct\/public\//, '/'));
      if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({ path: asset, contentType: mime(asset) });
      if (['stylesheet', 'script', 'font'].includes(request.resourceType())) missing.add(url.pathname);
      return route.abort();
    });
    const open = async state => {
      await page.goto(`http://localhost/ctr-qa/${state}`, { waitUntil: 'networkidle' });
      const form = page.locator('#ctr-confirm-form');
      if (await form.count()) accepted.set(await form.locator('[name="token"]').inputValue(), await form.locator('[name="revision"]').inputValue());
    };
    const saved = () => page.waitForFunction(() => document.querySelector('#ctr-board-status')?.dataset.saveState === 'saved' && !document.querySelector('#ctr-confirm')?.disabled);
    const entry = id => page.locator(`${playerSelector}[data-entry-id="${id}"]`);
    for (const width of (process.env.CT_CTR_BROWSER_WIDTHS || '360,390,768,1440').split(',').map(Number)) {
      if (exclusionOnly || ajaxOnly || ajaxExtrasOnly) break;
      await page.setViewportSize({ width, height: 1000 });
      for (const state of (process.env.CT_CTR_BROWSER_INTERACTIONS_ONLY === '1' ? [] : states)) {
        await open(state);
        assert.equal(await page.locator('#ctr-planner').count(), 1);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${state} ${width}: document containment`);
        assert.match(await page.locator('#ctr-planner').innerText(), /CTR Certified/);
        if (state === 'initial') {
          assert.equal(await page.locator('#ctr-target').inputValue(), '4');
          assert.equal(await page.locator('#ctr-plan-all').isEnabled(), true);
        }
        if (definitions[state]) {
          const definition = definitions[state];
          assert.deepEqual(await counts(page), definition.sizes, `${state}: automatic group sizes`);
          await assertBoard(page);
          assert.match(await page.locator('#ctr-prize-total').innerText(), new RegExp(`${definition.competitions} winner prizes.*${definition.competitions} sportsmanship prizes.*${definition.competitions * 2} prizes`, 's'));
          assert.match(await page.locator('#ctr-programme-total').innerText(), new RegExp(`${definition.competitions} competitions.*${definition.matches} matches`, 's'));
          assert.equal(await page.locator('#ctr-confirm').isEnabled(), true, `${state}: saved reviewed proposal can be confirmed`);
          assert.equal(await page.locator('#ctr-review input[type=number]').count(), 0, 'Number editor replaced by group cards');
          assert.equal(await page.locator('[data-remove-group]:visible').count(), 0, 'Populated groups do not show empty-group removal');
          for (const selector of ['#ctr-confirm', '#ctr-review-changes', '[data-move-player]']) {
            const box = await page.locator(selector).first().boundingBox();
            assert.ok(box && box.height >= 40, `${selector} reachable touch target`);
          }
          assert.doesNotMatch(await page.locator('#ctr-review').innerText(), /CTR 0\.0/, 'Missing ratings never become zero');
          if (['six', 'ten'].includes(state)) {
            assert.match(await page.locator('[data-six-description]').innerText(), /A1 vs B1.*A2 vs B2.*A3 vs B3/s);
            assert.equal(await page.locator('[data-short-review]').isVisible(), false, 'Straight finals meet three-match target');
            if (state === 'six') assert.equal(await page.locator('[data-add-group]').isVisible(), false, 'Six-player template keeps its two source groups');
            if (state === 'ten') assert.deepEqual(await page.locator(groupSelector).evaluateAll(nodes => nodes.map(group => [...group.querySelectorAll('[data-ctr-player]')].map(player => Number(player.dataset.score)))), [[90, 89, 88, 87], [86, 83, 82], [85, 84, 81]], 'Independent four stays contiguous; linked six uses local snake seeds 1/4/5 and 2/3/6');
          }
        }
        if (state === 'blocked') { assert.match(await page.locator('#ctr-review').innerText(), /Not enough players/); assert.equal(await page.locator('#ctr-confirm').isDisabled(), true); }
        if (state === 'shortfall') {
          assert.match(await page.locator('#ctr-review').innerText(), /2 matches per player|two matches per player/);
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
          await page.locator('[data-shortfall-ack]').check();
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Acknowledgment must be saved by review first');
          await saved();
        }
        if (state === 'excluded') {
          assert.equal(await page.locator('[data-exclude-category]:checked').count(), 1);
          assert.equal(await page.locator('#ctr-confirm').isEnabled(), true);
          const original = await snapshot(page), excluded = page.locator('[data-exclude-category]:checked');
          await excluded.uncheck();
          assert.equal(await page.locator('[data-exclude-category]:checked').count(), 0);
          assert.equal(await page.locator('#ctr-review-changes').isDisabled(), true, 'Including the two-player category blocks review');
          const shortCategory = page.locator('[data-ctr-category]').filter({ hasText: 'Not enough players' });
          await shortCategory.locator('[data-exclude-category]').focus(); await page.keyboard.press('Space');
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Changed inclusion needs saved review');
          assert.deepEqual(await snapshot(page), original, 'Exclusion preserves every player and placement');
          await saved();
        }
        if (state === 'sparse') {
          await assertBoard(page);
          assert.deepEqual(await page.locator(groupSelector).evaluateAll(nodes => nodes.map(node => Number(node.dataset.groupId))), [1, 2, 10]);
        }
        if (state === 'created') { assert.equal(await page.locator('#ctr-created').count(), 1); assert.equal(await page.locator('#ctr-confirm').count(), 0); }
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: path.join(output, `${state}-${width}.png`), fullPage: true });
        report.push({ state, width, containment: true });
      }
      await open('review');
      const boardUrl = page.url();
      const original = await snapshot(page), ids = original.map(row => row.id), first = original[0], second = original[1];
      const target = original.find(row => row.group !== first.group);
      await entry(first.id).locator('[data-placement]').selectOption(target.group);
      await entry(first.id).locator('[data-move-player]').focus(); await page.keyboard.press('Enter');
      assert.deepEqual(await counts(page), [3, 5, 3], 'Accessible Move updates cards');
      assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Dirty placements disable confirmation');
      await saved();
      assert.equal(page.url(), boardUrl, 'Move autosave does not navigate');
      const beforeInvalid = reviews.length;
      await entry(second.id).locator('[data-placement]').selectOption(target.group);
      assert.deepEqual(await counts(page), [2, 6, 3]);
      assert.equal(await page.locator('#ctr-review-changes').isDisabled(), true, 'Groups outside three to five block review');
      await page.waitForTimeout(650);
      assert.equal(reviews.length, beforeInvalid, 'Invalid intermediate group sizes never autosave');
      await entry(second.id).locator('[data-placement]').selectOption(first.group);
      const originalGroup = page.locator(`${groupSelector}[data-category-id="${first.category}"][data-group-id="${first.group}"] [data-player-list]`);
      await page.evaluate(() => {
        window.ctrQaDragEvents = [];
        for (const type of ['dragstart', 'dragover', 'drop', 'dragend']) document.addEventListener(type, event => {
          if (window.ctrQaDragEvents.length < 20) window.ctrQaDragEvents.push({ type, entry: event.target.closest('[data-ctr-player]')?.dataset.entryId, group: event.target.closest('[data-ctr-group]')?.dataset.groupId });
        });
      });
      await nativeDrag(page, entry(first.id), originalGroup);
      if (JSON.stringify(await counts(page)) !== JSON.stringify([4, 4, 3])) console.log('Drag event diagnostics:', JSON.stringify(await page.evaluate(() => window.ctrQaDragEvents)));
      assert.deepEqual(await counts(page), [4, 4, 3], 'Native drag/drop restores group sizes');
      await assertBoard(page, ids);
      await saved();
      const beforeAdd = await page.locator(groupSelector).count();
      await page.locator('[data-add-group]').first().click();
      assert.equal(await page.locator(groupSelector).count(), beforeAdd + 1);
      const empty = page.locator(groupSelector).last();
      assert.equal(await empty.locator(playerSelector).count(), 0);
      await empty.locator('[data-remove-group]').click();
      assert.equal(await page.locator(groupSelector).count(), beforeAdd);
      await assertBoard(page, ids);
      assert.match(await page.locator('#ctr-prize-total').innerText(), /6 prizes/);
      const expected = await snapshot(page);
      await saved();
      const reviewed = reviews.at(-1).fields;
      assert.equal([...reviewed.keys()].filter(key => key.startsWith('placements[')).length, expected.length);
      for (const row of expected) assert.equal(reviewed.get(`placements[${row.id}]`), row.group, 'POST exactly matches visible membership');
      report.push({ interaction: 'move-drag-boundaries-membership', width, passed: true });
      console.log(`PASS ${width}px: visual states, Move, native drag/drop, size bounds and membership.`);
    }
    if (!exclusionOnly && !ajaxOnly && !ajaxExtrasOnly && states.includes('categories')) {
      await open('categories');
      const original = await snapshot(page), first = original[0];
      const foreign = page.locator(`${groupSelector}:not([data-category-id="${first.category}"]) [data-player-list]`).first();
      assert.equal(await foreign.count(), 1);
      await nativeDrag(page, entry(first.id), foreign);
      assert.deepEqual(await snapshot(page), original, 'Cross-category drag cannot change memberships');
      report.push({ interaction: 'cross-category-drag', passed: true });
    }
    if (!exclusionOnly && !ajaxOnly && !ajaxExtrasOnly) {
      await open('review'); await page.locator('#ctr-confirm').click(); await page.waitForLoadState('networkidle');
      const confirmed = new URLSearchParams(submitted.at(-1).body);
      assert.ok(confirmed.get('token')); assert.ok(confirmed.get('revision'));
      assert.equal([...confirmed.keys()].some(key => key.startsWith('placements')), false, 'Confirm submits saved proposal identity only');
    }
    exclusionActive = true;
    for (const width of (process.env.CT_CTR_BROWSER_WIDTHS || '360,390,768,1440').split(',').map(Number)) {
      if (ajaxExtrasOnly) break;
      await page.setViewportSize({ width, height: 1000 });
      await open('exclusion-flow-initial');
      const original = await snapshot(page);
      assert.equal(original.length, 20, 'Green twelve, Orange two and Yellow six stay in the plan');
      assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
      const orange = page.locator('[data-ctr-category][data-player-total="2"]');
      assert.equal(await orange.count(), 1);
      const orangeCategory = await orange.getAttribute('data-category-id');
      const oldRevision = await page.locator('#ctr-confirm-form [name="revision"]').inputValue();
      const boardUrl = page.url();
      await orange.locator('[data-exclude-category]').check();
      assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Unsaved exclusion cannot create draws');
      assert.match(await page.locator('#ctr-programme-total').innerText(), /4 competitions.*27 matches.*18 players/s);
      const scrollPosition = await page.evaluate(() => scrollY);
      await saved();
      const savedRequest = reviews.at(-1).fields;
      assert.equal(page.url(), boardUrl, 'Exclusion autosave remains on the current page');
      assert.ok(Math.abs(await page.evaluate(() => scrollY) - scrollPosition) <= 2, 'Autosave preserves scroll position');
      assert.equal(await orange.locator('[data-exclude-category]').evaluate(node => document.activeElement === node), true, 'Autosave preserves focus');
      assert.deepEqual(savedRequest.getAll('excluded_categories[]'), [orangeCategory]);
      assert.equal([...savedRequest.keys()].filter(key => key.startsWith('placements[')).length, 20, 'Review retains excluded-entry coverage');
      assert.deepEqual(await snapshot(page), original, 'In-page saved review preserves all player placements');
      assert.equal(await page.locator('[data-exclude-category]:checked').count(), 1);
      const reviewedRevision = await page.locator('#ctr-confirm-form [name="revision"]').inputValue();
      assert.notEqual(reviewedRevision, oldRevision, 'Both forms carry the saved exclusion revision');
      assert.equal(await page.locator('#ctr-placement-form [name="revision"]').inputValue(), reviewedRevision);
      assert.equal(await page.locator('#ctr-confirm').isEnabled(), true, 'Successful in-page review enables confirmation');
      assert.equal(await page.locator('#ctr-blocked').isVisible(), false, 'Resolved category warning does not remain as a blocker');
      assert.equal(await page.locator('[data-review-exclusion]:visible').count(), 0, 'Saved exclusions no longer show an unsaved action');
      assert.match(await page.locator('#ctr-prize-total').innerText(), /4 winner prizes.*4 sportsmanship prizes.*8 prizes/s);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      await page.evaluate(() => scrollTo(0, 0));
      await page.screenshot({ path: path.join(output, `exclusion-reviewed-${width}.png`), fullPage: true });
      await page.locator('#ctr-confirm').click(); await page.waitForLoadState('networkidle');
      const confirmRequest = new URLSearchParams(submitted.at(-1).body);
      assert.equal(confirmRequest.get('revision'), reviewedRevision);
      assert.equal(confirmRequest.get('token'), savedRequest.get('token'));
      assert.equal([...confirmRequest.keys()].some(key => key.startsWith('placements')), false);
      report.push({ interaction: 'exclude-autosave-without-navigation-confirm', width, passed: true });
      console.log(`PASS ${width}px: exclude Orange two, save in place and confirm Green/Yellow.`);
    }
    if (!exclusionOnly) {
      for (const width of [390, 1440]) {
        if (ajaxExtrasOnly) break;
        await page.setViewportSize({ width, height: 1000 });
        reviewMode = 'hold'; heldReviews.length = 0;
        await open('review');
        const rows = await snapshot(page), first = rows[0];
        const destination = rows.find(row => row.group !== first.group).group;
        const lastGroup = rows.at(-1).group;
        const startingRequests = reviews.length;
        await entry(first.id).locator('[data-placement]').selectOption(destination);
        await until(() => heldReviews.length === 1, 'First save is held in flight');
        assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
        await entry(first.id).locator('[data-placement]').selectOption(lastGroup);
        const newest = await snapshot(page);
        await page.waitForTimeout(500);
        assert.equal(reviews.length, startingRequests + 1, 'A second save cannot run concurrently');
        heldReviews[0].release();
        await until(() => heldReviews.length === 2, 'Latest edits are queued after the first acknowledgment');
        assert.equal(heldReviews[1].record.revision, heldReviews[0].record.responseRevision, 'Queued save uses the accepted server revision');
        assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Older success cannot enable creation while newer edits remain');
        assert.deepEqual(await snapshot(page), newest, 'Older response cannot overwrite current cards');
        assert.match(await page.locator('#ctr-programme-total').innerText(), /15 matches/, 'Older totals cannot overwrite current totals');
        heldReviews[1].release(); await saved();
        assert.equal(await page.locator('#ctr-confirm-form [name="revision"]').inputValue(), heldReviews[1].record.responseRevision);
        assert.equal(maximumUnanswered, 1, 'At most one review is in flight');
        report.push({ interaction: 'serialized-latest-edit-race', width, passed: true });
      }
      for (const mode of (process.env.CT_CTR_BROWSER_FAILURE_MODES || '422,409,401,403,419,429,500,network,html,malformed').split(',')) {
        reviewMode = mode;
        await open('review');
        const url = page.url(), rows = await snapshot(page), first = rows[0];
        const destination = rows.find(row => row.group !== first.group).group;
        await entry(first.id).locator('[data-placement]').selectOption(destination);
        await page.waitForFunction(() => document.querySelector('#ctr-board-status')?.dataset.saveState === 'error');
        assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, `${mode}: failed or uncertain saves cannot enable creation`);
        assert.equal(page.url(), url, `${mode}: save failure does not navigate`);
        assert.deepEqual(await counts(page), [3, 5, 3], `${mode}: local cards are preserved`);
        if (mode === '422') {
          reviewMode = 'success';
          assert.equal(await page.locator('#ctr-review-changes').isEnabled(), true, 'Validation rejection permits explicit retry');
          await page.locator('#ctr-review-changes').click(); await saved();
        } else {
          assert.equal(await page.locator('#ctr-reload-plan').isVisible(), true, `${mode}: recovery explains reloading the saved plan`);
          const attempts = reviews.length;
          reviewMode = 'success';
          await entry(first.id).locator('[data-placement]').selectOption(rows.at(-1).group);
          await page.waitForTimeout(650);
          assert.equal(reviews.length, attempts, `${mode}: uncertain server revisions are not blindly retried`);
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
        }
        report.push({ interaction: 'autosave-failure-recovery', mode, passed: true });
      }
      reviewMode = 'hold'; heldReviews.length = 0;
      await page.clock.install();
      await open('review');
      const timeoutRows = await snapshot(page), timeoutFirst = timeoutRows[0];
      const timeoutRevision = await page.locator('#ctr-confirm-form [name="revision"]').inputValue();
      await entry(timeoutFirst.id).locator('[data-placement]').selectOption(timeoutRows.find(row => row.group !== timeoutFirst.group).group);
      await page.clock.fastForward(450);
      await until(() => heldReviews.length === 1, 'Timeout test has one potentially committed request in flight');
      await page.clock.fastForward(15001);
      await page.waitForFunction(() => document.querySelector('#ctr-board-status')?.dataset.saveState === 'error');
      assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
      assert.equal(await page.locator('#ctr-reload-plan').isVisible(), true);
      heldReviews[0].release();
      await until(() => heldReviews[0].record.delivered, 'Late valid save response is delivered after timeout');
      await new Promise(resolve => setTimeout(resolve, 100));
      assert.equal(await page.locator('#ctr-board-status').getAttribute('data-save-state'), 'error', 'Late acknowledgment cannot clear the uncertain-save error');
      assert.equal(await page.locator('#ctr-confirm-form [name="revision"]').inputValue(), timeoutRevision, 'Late response cannot advance the create revision');
      const timeoutAttempts = reviews.length;
      await entry(timeoutFirst.id).locator('[data-placement]').selectOption(timeoutRows.at(-1).group);
      await page.clock.fastForward(1000);
      await new Promise(resolve => setTimeout(resolve, 100));
      assert.equal(reviews.length, timeoutAttempts, 'Timed-out saves are not blindly retried');
      assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
      report.push({ interaction: 'timeout-late-acknowledgment', passed: true });
      reviewMode = 'success';
    }
    assert.deepEqual([...missing], [], 'No missing local execution/style assets'); assert.deepEqual(errors, [], 'No browser runtime errors');
    fs.writeFileSync(path.join(output, exclusionOnly ? 'exclusion-browser-report.json' : ajaxExtrasOnly ? 'ajax-extra-browser-report.json' : ajaxOnly ? 'ajax-browser-report.json' : 'browser-report.json'), JSON.stringify({ fictional: true, report, submissionsCaptured: submitted.length, jsonReviews: reviews.length, maximumUnanswered, errors, missingAssets: [...missing] }, null, 2));
    console.log(`PASS: ${report.length} fictional checks; no production endpoints contacted.`);
  } finally { heldReviews.forEach(held => held.release()); await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
