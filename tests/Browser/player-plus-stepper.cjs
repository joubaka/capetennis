/* Current synthetic Laravel HTTP fixtures; no provider requests or real payments. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const script = fs.readFileSync(path.join(publicRoot, 'assets/js/player-plus-stepper.js'), 'utf8');
const fixture = name => fs.readFileSync(path.join(root, `storage/framework/testing/player-plus-${name}.html`), 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    let posts = 0;
    await page.route('**/*', route => {
      if (route.request().method() === 'POST') posts++;
      const url = new URL(route.request().url());
      const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!['localhost', '127.0.0.1'].includes(url.hostname) || !file.startsWith(publicRoot + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) return route.abort();
      return route.fulfill({ path: file });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 960 });
      for (const name of ['player', 'player-monthly']) {
        await page.setContent(fixture(name), { waitUntil: 'networkidle' });
        assert.equal(await page.locator('[data-plus-plan="annual_recurring"]').isVisible(), true, 'no-JS review fallback');
        await page.addScriptTag({ content: script });
        const choices = page.locator('[data-plus-plan-choices]');
        const annual = page.locator('[data-plus-plan="annual_recurring"]');
        assert.equal(await choices.isVisible(), true);
        assert.equal(await annual.isVisible(), false);
        await page.locator('[data-plus-choose="annual_recurring"]').click();
        assert.equal(await annual.isVisible(), true);
        assert.equal(await page.locator('[aria-current="step"]').getAttribute('data-plus-step'), '4');
        assert.equal(await annual.locator('h2').evaluate(el => el === document.activeElement), true);
        assert.equal(await annual.evaluate(form => form.checkValidity()), false, 'consent remains required');
        await annual.locator('[name="confirm"]').check();
        await annual.locator('[name="recurring_consent"]').check();
        assert.equal(await annual.evaluate(form => form.checkValidity()), true);
        await page.locator('[data-plus-step-back]').click();
        assert.equal(await choices.isVisible(), true);
        assert.equal(await annual.locator('[name="confirm"]').isChecked(), false);
        assert.equal(await page.locator('[aria-current="step"]').getAttribute('data-plus-step'), '3');
        if (name === 'player-monthly') {
          await page.locator('[data-plus-choose="monthly"]').click();
          assert.equal(await page.locator('[data-plus-plan="monthly"]').isVisible(), true);
          assert.equal(await annual.isVisible(), false);
          assert.equal(await page.locator('[data-plus-plan="monthly"] [name="confirm"]').isChecked(), false);
        }
        assert.equal(await page.locator('[data-plus-player-page]').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true, `${width}px containment`);
      }
      for (const name of ['player-active', 'player-paused']) {
        await page.setContent(fixture(name), { waitUntil: 'networkidle' });
        await page.addScriptTag({ content: script });
        assert.equal(await page.locator('[data-plus-plan-choices]').count(), 0);
        assert.equal(await page.locator('[data-plus-plan]').count(), 0);
        if (name === 'player-active') {
          const returnLink = page.getByRole('link', { name: /Back to My Tennis/ });
          assert.equal(await returnLink.isVisible(), true);
          assert.equal(new URL(await returnLink.getAttribute('href')).searchParams.get('tab'), 'rating');
          assert.equal(await page.locator('[data-plus-stepper]').count(), 0, 'members bypass purchase steps');
        }
      }
      await page.setContent(fixture('player-monthly'), { waitUntil: 'networkidle' });
      await page.locator('[data-plus-player-page]').evaluate(el => { el.dataset.plusReviewPlan = 'monthly'; });
      await page.addScriptTag({ content: script });
      assert.equal(await page.locator('[data-plus-plan="monthly"]').isVisible(), true, 'validation return restores chosen plan');
      assert.equal(await page.locator('[data-plus-plan="monthly"] [name="recurring_consent"]').isChecked(), false);
      for (const name of ['wallet', 'wallet-insufficient']) {
        await page.setContent(fixture(name), { waitUntil: 'networkidle' });
        const wallet = page.locator('[data-plus-plan="annual"]');
        assert.equal(await wallet.isVisible(), true, 'no-JS wallet review fallback');
        assert.equal(await wallet.locator('[name="recurring_consent"]').count(), 0);
        await page.addScriptTag({ content: script });
        await page.locator('[data-plus-plan-choices] [data-plus-choose="annual"]').click();
        assert.equal(await wallet.isVisible(), true);
        assert.equal(await wallet.evaluate(form => form.checkValidity()), false);
        await wallet.locator('[name="confirm"]').check();
        await wallet.locator('[name="wallet_consent"]').check();
        assert.equal(await wallet.evaluate(form => form.checkValidity()), true);
        assert.equal(await wallet.locator('button[type="submit"],button:not([type])').isEnabled(), name === 'wallet');
        await wallet.locator('[data-plus-change-plan]').click();
        assert.equal(await wallet.locator('[name="wallet_consent"]').isChecked(), false);
        await page.locator('[data-plus-choose="annual_recurring"]').click();
        await page.locator('[data-plus-wallet-alternative]').click();
        assert.equal(await wallet.isVisible(), true);
        assert.equal(await wallet.locator('[name="confirm"]').isChecked(), false);
        assert.equal(await page.locator('[data-plus-player-page]').evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
      }
    }
    assert.equal(posts, 0, 'choosing and reviewing plans creates no checkout or payment');
    console.log('Player Plus stepper passed at 320, 390, 768 and 1440px. No POSTs made.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
