/* Real Laravel-rendered fixtures and local CSS. No database or live provider requests. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtureRoot = path.join(root, 'storage/framework/testing');

(async () => {
  const fixtures = ['index', 'detail', 'empty', 'validation'].map(name => {
    const file = path.join(fixtureRoot, `player-plus-management-${name}.html`);
    assert.ok(fs.existsSync(file), `Generate current HTTP fixture with CT_PLUS_MANAGEMENT_QA_HTML=1: ${file}`);
    return { name, html: fs.readFileSync(file, 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') };
  });
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const styles = new Set();
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!['localhost', '127.0.0.1'].includes(url.hostname) || !target.startsWith(publicRoot + path.sep)
        || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      if (target.endsWith('.css')) styles.add(target);
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const { name, html } of fixtures) {
        await page.setContent(html, { waitUntil: 'networkidle' });
        const content = page.locator('[data-plus-management]');
        assert.equal(await content.isVisible(), true, `${name}: management page visible`);
        assert.ok(await content.locator('h1').count(), `${name}: heading`);
        for (const summary of await content.locator('details > summary').all()) {
          await summary.click();
          assert.equal(await summary.evaluate(el => el.parentElement.open), true, 'order resolution disclosure opens');
        }
        assert.deepEqual(await content.evaluate(el => {
          const issues = [];
          for (const node of [el, ...el.querySelectorAll('form,input:not([type=hidden]),select,textarea,button,h1,h2,p,.card')]) {
            const box = node.getBoundingClientRect();
            if (box.width && box.height && (box.left < -1 || box.right > innerWidth + 1)) issues.push(`${node.tagName}: clipped`);
          }
          return issues;
        }), [], `${name} ${width}px: horizontal containment`);
        for (const button of await content.locator('button,.btn,input:not([type=hidden]):not([type=checkbox]),select,textarea').all()) {
          const target = await button.evaluate(el => ({ tag: el.tagName, name: el.name || el.textContent.trim(), height: el.getBoundingClientRect().height, minHeight: getComputedStyle(el).minHeight, zoom: getComputedStyle(document.body).zoom }));
          assert.ok(target.height >= 44, `${name} ${width}px: touch target ${JSON.stringify(target)}`);
        }
        if (name === 'index' || name === 'empty') {
          await content.getByLabel('Show', { exact: true }).selectOption('all');
          await content.locator('[name=q]').fill('Synthetic player');
          assert.equal(await content.locator('[name=q]').inputValue(), 'Synthetic player');
          assert.equal(await content.locator('[name=status]').inputValue(), 'all');
        }
        if (name === 'empty') assert.match(await content.innerText(), /No players match/i);
        if (name === 'detail' || name === 'validation') {
          const form = content.locator('form').first();
          await form.locator('[name=action]').selectOption('suspend');
          await form.locator('[name=reason]').fill('Reviewed the player support request.');
          assert.equal(await form.evaluate(el => el.checkValidity()), false, 'confirmation is mandatory');
          await form.locator('[name=confirm]').check();
          assert.equal(await form.evaluate(el => el.checkValidity()), true, 'form accepts reason and confirmation');
          for (const action of ['grant', 'restore', 'inherit']) {
            await form.locator('[name=action]').selectOption(action);
            assert.equal(await form.locator('[name=action]').inputValue(), action);
          }
          assert.match(await content.innerText(), /does not stop automatic billing/i);
          if (name === 'validation') assert.equal(await content.getByRole('alert').count() > 0, true);
        }
        if (width === 320 || width === 1440) await content.screenshot({ path: path.join(fixtureRoot, `player-plus-management-${name}-${width}.png`) });
      }
      console.log(`PASS ${width}px: management list, empty, detail and validation; filters, access options, confirmation and containment`);
    }
    assert.ok(styles.size, 'Actual local stylesheet links loaded');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
