/* Actual fictional Blade exports; every HTTP request is fulfilled locally. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
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
  const states = ['initial', 'review', 'unrated', 'mixed', 'blocked', 'created', 'six', 'ten', 'twenty', 'shortfall', 'excluded', 'categories', 'sparse'];
  for (const state of states) assert.ok(fs.existsSync(fixture(state)), `Missing fictional export ${state}.html`);
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const report = [], submitted = [], missing = new Set(), errors = [];
  try {
    const page = await browser.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
      const request = route.request(), url = new URL(request.url());
      if (!['localhost', '127.0.0.1', 'ct.test'].includes(url.hostname)) return route.abort();
      if (request.method() !== 'GET') {
        if (/\/ctr-planner\//.test(url.pathname)) submitted.push({ path: url.pathname, method: request.method(), body: request.postData() });
        return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Fictional submission captured</title><p>Captured locally; no application endpoint called.</p>' });
      }
      const state = url.pathname.match(/^\/ctr-qa\/([a-z]+)$/)?.[1];
      if (states.includes(state)) return route.fulfill({ path: fixture(state), contentType: 'text/html' });
      const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname).replace(/^\/ct\/public\//, '/'));
      if (asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({ path: asset, contentType: mime(asset) });
      if (['stylesheet', 'script', 'font'].includes(request.resourceType())) missing.add(url.pathname);
      return route.abort();
    });
    const open = state => page.goto(`http://localhost/ctr-qa/${state}`, { waitUntil: 'networkidle' });
    const entry = id => page.locator(`${playerSelector}[data-entry-id="${id}"]`);
    for (const width of (process.env.CT_CTR_BROWSER_WIDTHS || '360,390,768,1440').split(',').map(Number)) {
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
          }
        }
        if (state === 'blocked') { assert.match(await page.locator('#ctr-review').innerText(), /Not enough players/); assert.equal(await page.locator('#ctr-confirm').isDisabled(), true); }
        if (state === 'shortfall') {
          assert.match(await page.locator('#ctr-review').innerText(), /2 matches per player|two matches per player/);
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true);
          await page.locator('[data-shortfall-ack]').check();
          assert.equal(await page.locator('#ctr-review-changes').isEnabled(), true);
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Acknowledgment must be saved by review first');
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
          assert.equal(await page.locator('#ctr-review-changes').isEnabled(), true, 'Exclusion leaves valid competition available');
          assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Changed inclusion needs saved review');
          assert.deepEqual(await snapshot(page), original, 'Exclusion preserves every player and placement');
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
      const original = await snapshot(page), ids = original.map(row => row.id), first = original[0], second = original[1];
      const target = original.find(row => row.group !== first.group);
      await entry(first.id).locator('[data-placement]').selectOption(target.group);
      await entry(first.id).locator('[data-move-player]').focus(); await page.keyboard.press('Enter');
      assert.deepEqual(await counts(page), [3, 5, 3], 'Accessible Move updates cards');
      assert.equal(await page.locator('#ctr-confirm').isDisabled(), true, 'Dirty placements disable confirmation');
      await entry(second.id).locator('[data-placement]').selectOption(target.group);
      assert.deepEqual(await counts(page), [2, 6, 3]);
      assert.equal(await page.locator('#ctr-review-changes').isDisabled(), true, 'Groups outside three to five block review');
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
      await page.locator('#ctr-review-changes').click(); await page.waitForLoadState('networkidle');
      const reviewed = new URLSearchParams(submitted.at(-1).body);
      assert.equal([...reviewed.keys()].filter(key => key.startsWith('placements[')).length, expected.length);
      for (const row of expected) assert.equal(reviewed.get(`placements[${row.id}]`), row.group, 'POST exactly matches visible membership');
      report.push({ interaction: 'move-drag-boundaries-membership', width, passed: true });
      console.log(`PASS ${width}px: visual states, Move, native drag/drop, size bounds and membership.`);
    }
    if (states.includes('categories')) {
      await open('categories');
      const original = await snapshot(page), first = original[0];
      const foreign = page.locator(`${groupSelector}:not([data-category-id="${first.category}"]) [data-player-list]`).first();
      assert.equal(await foreign.count(), 1);
      await nativeDrag(page, entry(first.id), foreign);
      assert.deepEqual(await snapshot(page), original, 'Cross-category drag cannot change memberships');
      report.push({ interaction: 'cross-category-drag', passed: true });
    }
    await open('review'); await page.locator('#ctr-confirm').click(); await page.waitForLoadState('networkidle');
    const confirmed = new URLSearchParams(submitted.at(-1).body);
    assert.ok(confirmed.get('token')); assert.ok(confirmed.get('revision'));
    assert.equal([...confirmed.keys()].some(key => key.startsWith('placements')), false, 'Confirm submits saved proposal identity only');
    assert.deepEqual([...missing], [], 'No missing local execution/style assets'); assert.deepEqual(errors, [], 'No browser runtime errors');
    fs.writeFileSync(path.join(output, 'browser-report.json'), JSON.stringify({ fictional: true, report, submissionsCaptured: submitted.length, errors, missingAssets: [...missing] }, null, 2));
    console.log(`PASS: ${report.length} fictional checks; no production endpoints contacted.`);
  } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
