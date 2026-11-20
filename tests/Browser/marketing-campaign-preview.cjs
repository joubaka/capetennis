/* Fictional Laravel-rendered campaign editor. Serve only local assets, never send. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixture = fs.readFileSync(path.join(root, 'storage/framework/testing/marketing-campaign-form.html'), 'utf8')
  .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
  + '<script src="/assets/js/marketing-campaign-preview.js"></script>';

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      assert.equal(route.request().method(), 'GET', 'Preview must never post or send');
      if (url.hostname !== 'localhost') return route.abort();
      if (url.pathname === '/marketing-preview-qa') return route.fulfill({ contentType: 'text/html', body: fixture });
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname).replace(/^\/ct\/public/, ''));
      if (!target.startsWith(publicRoot + path.sep) || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      return route.fulfill({ path: target });
    });
    for (const width of [320, 360, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await page.goto('http://localhost/marketing-preview-qa', { waitUntil: 'networkidle' });
      await page.waitForFunction(() => document.querySelector('[data-preview-email] table'));
      assert.equal(await page.locator('[data-preview-email] table').count(), 2);
      assert.match(await page.locator('[data-preview-email]').innerText(), /R8,690/);
      assert.equal(await page.locator('[data-preview-email-heading]').evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
      const logo = page.locator('.marketing-brand-header img');
      assert.ok(await logo.evaluate(el => el.complete && el.naturalWidth > 0), 'Configured logo loads');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `email contained at ${width}`);
      await page.locator('.marketing-preview-workspace').screenshot({ path: path.join(root, `storage/framework/testing/marketing-email-preview-${width}.png`) });
      await page.getByRole('tab', { name: 'WhatsApp', exact: true }).click();
      assert.equal(await page.locator('#marketing-email-panel').isVisible(), false);
      assert.match(await page.locator('[data-preview-whatsapp]').innerText(), /R8,690/);
      assert.equal(await page.locator('[data-preview-whatsapp-heading]').evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
      assert.doesNotMatch(await page.locator('[data-preview-whatsapp-heading]').textContent(), /\*/);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `WhatsApp contained at ${width}`);
      await page.locator('.marketing-preview-workspace').screenshot({ path: path.join(root, `storage/framework/testing/marketing-whatsapp-preview-${width}.png`) });
      console.log(`PASS ${width}px: email/WhatsApp previews, financial tables, logo, containment`);
    }
    const payload = '<img src=x onerror="window.marketingXss=1">';
    await page.locator('#title').fill(payload);
    await page.locator('#subject').fill(payload);
    const email = `## ${payload}\n\nCategory | Income\n--- | ---\nRed | *${payload}*\nTotal | R123.45`;
    await page.locator('#email_body').fill(email);
    await page.locator('#whatsapp_body').fill(`*${payload}*\n\n*Academy left: R42*`);
    await page.waitForFunction(() => document.querySelector('[data-preview-email]').textContent.includes('R123.45'));
    assert.equal(await page.locator('[data-preview-email] table').count(), 1, 'Unbounded pipes form a table');
    assert.match(await page.locator('[data-preview-whatsapp]').innerText(), /R42/);
    assert.equal(await page.locator('[data-preview-email] img, [data-preview-whatsapp] img').count(), 0);
    assert.equal(await page.locator('[data-preview-campaign]').textContent(), payload);
    assert.equal(await page.locator('[data-preview-email-heading]').textContent(), payload);
    assert.equal(await page.locator('[data-preview-whatsapp-heading]').textContent(), payload);
    assert.equal(await page.evaluate(() => window.marketingXss), undefined);
    assert.equal(await page.locator('#email_body').inputValue(), email, 'Preview does not rewrite original copy');
    await page.getByRole('tab', { name: 'WhatsApp', exact: true }).focus();
    await page.keyboard.press('ArrowLeft');
    assert.equal(await page.getByRole('tab', { name: 'Email', exact: true }).getAttribute('aria-selected'), 'true');
    assert.deepEqual(errors, []);
    console.log('PASS unsaved edits, pipe tables, HTML inertness, original copy, keyboard tabs, no outbound requests');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
