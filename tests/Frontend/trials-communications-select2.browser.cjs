const { test } = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

test('Trials email selectors search, submit and reopen inside the shared modal', async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CT_BROWSER_EXECUTABLE });
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.setContent(`<form data-mail-compose action="/preview">${['category_ids', 'tiers', 'nominee_ids', 'slot_ids'].map(name => `<select multiple data-trials-mail-select name="${name}[]"><option value="7" selected>First player</option><option value="8">Second player</option></select>`).join('')}<button>Preview</button></form>`);
      await page.addStyleTag({ path: 'public/assets/vendor/css/core.css' });
      await page.addStyleTag({ path: 'public/assets/vendor/libs/select2/select2.css' });
      await page.addScriptTag({ path: 'public/assets/vendor/libs/jquery/jquery.js' });
      await page.addScriptTag({ path: 'public/assets/vendor/js/bootstrap.js' });
      await page.addScriptTag({ path: 'public/assets/vendor/libs/select2/select2.js' });
      await page.addScriptTag({ path: 'public/js/manual-email.js' });
      await page.addScriptTag({ path: 'public/js/trials-communications.js' });
      await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
      await page.getByRole('button', { name: 'Write email' }).click();
      await page.locator('.select2-container').first().waitFor();
      assert.equal(await page.locator('select.select2-hidden-accessible').count(), 4);
      const search = page.locator('.select2-search__field').first();
      await search.fill('Second');
      await page.locator('.select2-results__option').filter({ hasText: 'Second player' }).click();
      assert.equal(await page.locator('.modal .select2-dropdown').count(), 1);
      assert.deepEqual(await page.locator('form').evaluate(form => new FormData(form).getAll('category_ids[]')), ['7', '8']);
      await page.keyboard.press('Escape');
      await page.locator('.btn-close').click();
      await page.locator('.modal').waitFor({ state: 'hidden' });
      await page.getByRole('button', { name: 'Write email' }).click();
      await page.locator('.modal.show').waitFor();
      assert.equal(await page.locator('select + .select2-container').count(), 4);
      assert.deepEqual(await page.locator('form').evaluate(form => new FormData(form).getAll('category_ids[]')), ['7', '8']);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await page.close();
    }
  } finally {
    await browser.close();
  }
});
