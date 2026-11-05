/* Synthetic profiles, actual widget/Select2 assets; no live account mutations. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'resources/views/frontend/my-tennis/index.blade.php'), 'utf8');
const markup = source.match(/<label for="my-tennis-player-search"[\s\S]*?<p id="my-tennis-player-search-status"[^>]*><\/p>/)[0]
  + source.match(/<button id="my-tennis-player-link-button"[^>]*data-admin-profile-link[^>]*>[^<]*<\/button>/)[0];
const style = source.match(/<style>[\s\S]*?<\/style>/)[0];
assert.match(source, /route\('player.search', \[\], false\)/);
assert.match(source, /route\('backend.user.players.store', \$accountUser, false\)/);

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    for (const width of [320, 390, 768, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const requests = [];
      await page.route('http://ct-widget.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/fixture') return route.fulfill({ contentType: 'text/html', body: `<html><head><link rel="stylesheet" href="/assets/vendor/css/core.css"><link rel="stylesheet" href="/assets/vendor/libs/select2/select2.css">${style}</head><body><main id="my-tennis-page" style="padding:16px"><div id="link-player-panel">${markup}</div></main></body></html>` });
        if (url.pathname === '/search') {
          requests.push(Object.fromEntries(url.searchParams));
          assert.equal(url.searchParams.get('format'), 'select2');
          const q = url.searchParams.get('q');
          if (q === 'error') return route.fulfill({ status: 500, json: {} });
          const second = url.searchParams.get('page') === '2';
          return route.fulfill({ json: { results: q === 'empty' ? [] : second ? [{ id: 22, text: 'Second page Example · s***@example.test · profile #22' }] : Array.from({ length: 20 }, (_, i) => ({ id: i + 1, text: `Fictional Player ${i + 1} · f***@example.test · profile #${i + 1}` })), pagination: { more: q !== 'empty' && !second } } });
        }
        if (url.pathname === '/link') {
          assert.equal(route.request().method(), 'POST');
          assert.equal(route.request().headers()['x-csrf-token'], 'synthetic');
          assert.deepEqual(route.request().postDataJSON(), { player_id: 2 });
          return route.fulfill({ json: { message: 'Player linked successfully' } });
        }
        const file = path.resolve(root, 'public', '.' + url.pathname);
        if (!file.startsWith(path.join(root, 'public') + path.sep) || !fs.existsSync(file)) return route.abort();
        return route.fulfill({ path: file });
      });
      await page.goto('http://ct-widget.test/fixture');
      for (const file of ['assets/vendor/libs/jquery/jquery.js', 'assets/vendor/libs/select2/select2.js', 'assets/js/my-tennis-player-search.js']) await page.addScriptTag({ url: '/' + file });
      await page.evaluate(() => {
        window.links = [];
        new window.MyTennisPlayerSearch({ select: document.querySelector('#my-tennis-player-search'), button: document.querySelector('#my-tennis-player-link-button'), status: document.querySelector('#my-tennis-player-search-status'), url: '/search', linkedIds: new Set([1]), onLink: player => window.links.push(player.id) });
      });
      const box = page.locator('.select2-selection');
      const input = page.locator('.select2-search__field');
      await box.click();
      await input.fill('F');
      await page.waitForTimeout(400);
      assert.equal(requests.length, 0, 'One character does not search');
      await input.fill('Fi');
      await page.waitForTimeout(100);
      await input.fill('Fictional');
      await page.locator('.select2-results__option[aria-selected="false"]').first().waitFor();
      assert.equal(requests[0].q, 'Fictional', 'Typing is debounced');
      assert.equal(await page.locator('.select2-results__option[aria-disabled="true"]').filter({ hasText: 'Fictional Player' }).count(), 1);
      await page.locator('.select2-results__options').evaluate(el => { el.scrollTop = el.scrollHeight; el.dispatchEvent(new Event('scroll')); });
      await page.getByRole('option', { name: 'Second page Example', exact: false }).waitFor();
      assert.equal(requests[1].page, '2', 'Results paginate');
      await input.press('ArrowDown');
      await input.press('Enter');
      assert.deepEqual(await page.evaluate(() => window.links), [], 'Selecting never links');
      await page.getByRole('button', { name: 'Link and open profile' }).click();
      assert.equal((await page.evaluate(() => window.links)).length, 1);
      await box.click();
      await input.pressSequentially('empty');
      await page.locator('.select2-results__message').filter({ hasText: 'No matching player profiles found.' }).waitFor({ timeout: 5000 });
      await input.fill('');
      await input.pressSequentially('error');
      await page.getByText('Player search is unavailable. Try typing the name again.', { exact: true }).first().waitFor();
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `No page overflow at ${width}`);
      const runLink = () => page.evaluate(() => window.MyTennisPlayerSearch.link({ url: '/link', csrf: 'synthetic', playerId: 2 }).then(() => 'success', error => error.message));
      assert.equal(await runLink(), 'success');
      await page.route('**/link', route => route.fulfill({ status: 422, json: { message: 'Player already linked' } }));
      assert.equal(await runLink(), 'Player already linked');
      await page.route('**/link', route => route.fulfill({ status: 419, body: 'Expired' }));
      assert.match(await runLink(), /session has expired/);
      const redirectMessage = await page.evaluate(async () => {
        const originalFetch = window.fetch;
        window.fetch = async () => ({ redirected: true, status: 200 });
        try { return await window.MyTennisPlayerSearch.link({ url: '/link', csrf: 'synthetic', playerId: 2 }).catch(error => error.message); }
        finally { window.fetch = originalFetch; }
      });
      assert.match(redirectMessage, /session has expired/);
      await page.route('**/link', route => route.fulfill({ status: 200, contentType: 'text/html', body: '<html>Sign in</html>' }));
      assert.match(await runLink(), /unexpected response/);
      await page.route('**/link', route => route.abort());
      assert.match(await runLink(), /Check your connection/);
      await page.close();
    }
    console.log('PASS: Select2 AJAX search, pagination, keyboard selection, explicit linking, failure handling and widths 320/390/768/1440.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
