/* Synthetic HTTP-rendered Plus pages and real local styles; no live payments/mail/data. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtureRoot = path.join(root, 'storage/framework/testing');

(async () => {
  const names = ['index', 'player', 'orders', 'order', 'terms', 'admin', 'orders-empty', 'player-active', 'paused', 'settings'];
  const fixtures = names.map(name => {
    const filename = path.join(fixtureRoot, `player-plus-${name}.html`);
    assert.ok(fs.existsSync(filename), `Generate current Plus HTTP fixture: ${filename}`);
    return { name, html: fs.readFileSync(filename, 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') };
  });
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const styles = new Set();
    const priceRequests = [];
    let rejectPrice = false;
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.hostname === 'localhost' && url.pathname.endsWith('/settings/player-plus-price')) {
        priceRequests.push(new URLSearchParams(route.request().postData()));
        return route.fulfill({ status: rejectPrice ? 422 : 200,
          headers: { 'access-control-allow-origin': '*' },
          json: rejectPrice ? { errors: { player_plus_annual_price: ['Check the annual price.'] } }
            : { message: 'Annual price saved for new checkouts.' } });
      }
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!['localhost', '127.0.0.1'].includes(url.hostname) || !target.startsWith(publicRoot + path.sep)
        || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      if (target.endsWith('.css')) styles.add(url.pathname);
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      for (const { name, html } of fixtures) {
        await page.setContent(html, { waitUntil: 'networkidle' });
        if (name === 'settings') {
          const fragment = await page.locator('#sa-plus-price').evaluate(el => el.closest('.col-md-6').outerHTML);
          const head = await page.locator('head').innerHTML();
          await page.setContent(`<html><head>${head}</head><body><main class="content-wrapper container-p-y container-xxl"><h1>Player Plus annual price settings</h1>${fragment}</main></body></html>`, { waitUntil: 'networkidle' });
        }
        const content = page.locator('.content-wrapper').first();
        assert.equal(await content.isVisible(), true, `${name}: rendered layout`);
        assert.ok(await content.locator('h1').count(), `${name}: page heading`);
        assert.deepEqual(await content.evaluate(el => {
          const issues = [];
          const box = el.getBoundingClientRect();
          if (box.left < -1 || box.right > innerWidth + 1) issues.push('content clipped');
          for (const node of el.querySelectorAll('form, input:not([type=hidden]), textarea, button, summary, h1, h2, p, .card')) {
            const rect = node.getBoundingClientRect();
            if (!rect.width || !rect.height || node.closest('.table-responsive')) continue;
            if (rect.left < -1 || rect.right > innerWidth + 1) issues.push(`${node.tagName}: clipped`);
          }
          return issues;
        }), [], `${name} ${width}px: horizontal containment`);
        for (const summary of await content.locator('details > summary').all()) {
          const wasOpen = await summary.evaluate(el => el.parentElement.open);
          await summary.click();
          assert.equal(await summary.evaluate(el => el.parentElement.open), !wasOpen, `${name}: disclosure responds`);
        }
        if (name === 'player') {
          const confirm = content.locator('input[name=confirm]');
          if (await confirm.count()) {
            assert.notEqual(await confirm.getAttribute('required'), null);
            await confirm.check();
            assert.equal(await confirm.isChecked(), true);
          }
          assert.match(await content.innerText(), /pathway/i);
        }
        if (name === 'player-active') assert.equal(await content.getByRole('link', { name: 'View tennis pathway', exact: true }).isVisible(), true);
        if (name === 'orders-empty') assert.match(await content.innerText(), /no Player Plus orders/i);
        if (name === 'paused') assert.match(await content.innerText(), /purchases are currently paused/i);
        if (name === 'settings') {
          const fullHtml = fs.readFileSync(path.join(fixtureRoot, 'player-plus-settings.html'), 'utf8');
          const handler = fullHtml.match(/\$\('#sa-plus-price-save'\)\.on\('click',[\s\S]*?\n  \}\);/);
          assert.ok(handler, 'Actual rendered annual-price click handler is present');
          await page.addScriptTag({ path: path.join(publicRoot, 'assets/vendor/libs/jquery/jquery.js') });
          await page.addScriptTag({ content: `var csrfToken = 'synthetic-browser-token'; var saToggleToken = csrfToken;\n${handler[0]}` });
          const price = content.locator('#sa-plus-price');
          const save = content.getByRole('button', { name: 'Save annual price', exact: true });
          assert.equal(Number(await price.getAttribute('min')), 5, 'PayFast live-payment minimum');
          assert.ok(await save.evaluate(el => el.getBoundingClientRect().height >= 44));
          const before = priceRequests.length;
          await price.fill('0');
          await save.click();
          assert.equal(priceRequests.length, before, 'Invalid annual price never submits');
          await price.fill('4.99');
          await save.click();
          assert.equal(priceRequests.length, before, 'Below-provider-minimum price never submits');
          rejectPrice = false;
          await price.fill('625.75');
          await save.click();
          await content.locator('#sa-plus-price-status').getByText('Annual price saved for new checkouts.', { exact: true }).waitFor();
          assert.equal(priceRequests.at(-1).get('player_plus_annual_price'), '625.75');
          assert.equal(await save.isEnabled(), true);
          rejectPrice = true;
          await price.fill('650.50');
          await save.click();
          await content.locator('#sa-plus-price-status').getByText('Check the annual price.', { exact: true }).waitFor();
          assert.equal(await save.isEnabled(), true, 'Server rejection leaves price editable');
        }
        if (width === 320 || width === 1440) {
          await content.screenshot({ path: path.join(fixtureRoot, `player-plus-${name}-${width}.png`) });
        }
      }
      console.log(`PASS ${width}px: Plus purchase, order recovery, support terms and reconciliation presentation`);
    }
    assert.ok(styles.size, 'Actual linked CSS must load');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
