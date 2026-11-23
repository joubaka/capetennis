/* Generate the synthetic HTTP fixture with CT_RANKING_PRINT_QA_HTML=1 and
 * PublicRankingVisibilityTest. All requests are served from local public assets.
 */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixture = fs.readFileSync(path.join(root, 'storage/framework/testing/public-ranking-print.html'), 'utf8');
const rankingScript = [...fixture.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match => match[1]).find(script => script.includes("getElementById('ranking-print')"));
assert.ok(rankingScript, 'Ranking controls must be present in the rendered fixture');
const html = fixture.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(new URL(route.request().url()).pathname));
      if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      return route.fulfill({ path: target });
    });
    for (const width of [390, 1440]) {
      await page.emulateMedia({ media: 'screen' });
      await page.setViewportSize({ width, height: 960 });
      await page.setContent(html, { waitUntil: 'networkidle' });
      await page.addScriptTag({ content: rankingScript });
      await page.evaluate(() => {
        window.printCalls = 0;
        window.print = () => { window.printCalls++; window.dispatchEvent(new Event('beforeprint')); };
        document.dispatchEvent(new Event('DOMContentLoaded'));
      });
      assert.equal(await page.locator('.public-ranking-row:visible').count(), 4);
      const select = page.locator('#ranking-division-select');
      const boys = await select.locator('option').evaluateAll(options => options.find(option => option.textContent === 'Print Boys').value);
      await select.selectOption(boys);
      await page.locator('#ranking-player-search').fill('Alpha');
      assert.equal(await page.locator('.public-ranking-row:visible').count(), 1);
      await page.getByRole('button', { name: 'Print rankings' }).click();
      assert.equal(await page.evaluate(() => window.printCalls), 1);
      await page.emulateMedia({ media: 'print' });
      assert.equal(await page.locator('.public-ranking-row:visible').count(), 1, 'Only the current filtered row prints');
      assert.match(await page.locator('#ranking-print-context').innerText(), /Print Boys · Search: Alpha · 1 player shown/);
      assert.equal(await page.locator('.public-ranking-tools:visible, #layout-navbar:visible, #layout-menu:visible, .content-footer:visible, .modal:visible, #ct-install-app:visible, .public-ranking-player__action:visible').count(), 0);
      assert.equal(await page.locator('.public-ranking-table thead:visible').evaluate(el => getComputedStyle(el).display), 'table-header-group');
      const labels = page.locator('.public-ranking-row:visible .public-ranking-print-label');
      assert.deepEqual(await labels.allTextContents(), ['Counted', 'Automatic award', 'Not counted']);
      assert.equal(await page.locator('.public-ranking-row:visible').evaluate(el => getComputedStyle(el).display), 'table-row');
      assert.equal(await page.locator('.public-ranking-row:visible').evaluate(el => getComputedStyle(el).breakInside), 'avoid');
      assert.equal(await page.locator('.public-ranking-shell').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
      await page.pdf({ path: path.join(root, `storage/framework/testing/public-ranking-print-${width}.pdf`), format: 'A4' });
      await page.emulateMedia({ media: 'screen' });
      await select.selectOption('all');
      await page.locator('#ranking-player-search').fill('');
      await page.emulateMedia({ media: 'print' });
      assert.equal(await page.locator('.public-ranking-row:visible').count(), 4, 'All divisions print when selected');
      assert.equal(await page.getByText('Private Draft', { exact: false }).count(), 0);
      console.log(`PASS ${width}px: filtered print, all divisions, monochrome labels, table headers and hidden controls`);
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
