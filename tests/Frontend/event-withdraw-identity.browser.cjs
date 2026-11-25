// Isolated rendered UI test: no application database or withdrawal endpoint.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { test } = require('node:test');
const { chromium } = require('playwright');

test('event withdrawal identifies the exact entry safely on phone and desktop', async () => {
  const template = fs.readFileSync('resources/views/frontend/event/show.blade.php', 'utf8');
  const css = template.match(/<style>([\s\S]*?)<\/style>/)[1];
  const button = template.match(/<button type="button"\s+class="btn btn-danger[\s\S]*?<\/button>/)[0];
  const mark = fs.readFileSync('resources/views/components/ctr-mark.blade.php', 'utf8')
    .match(/<svg[\s\S]*<\/svg>/)[0]
    .replace(/\{\{[\s\S]*?\}\}/, 'style="display:inline-block;width:1.8rem;height:1.8rem;flex-shrink:0"');
  const badge = template.match(/<span class="event-ctr-certified"[\s\S]*?<\/span>\s*<i[\s\S]*?<\/span>/)[0]
    .replace('<x-ctr-mark />', mark);
  const escape = value => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
  const player = 'Alex <img src=x onerror=alert(1)> & Sam';
  const entries = ['Boys U12 A', 'Boys U14 B'];
  const browser = await chromium.launch({ headless: true, ...(process.env.CT_BROWSER_EXECUTABLE ? { executablePath: process.env.CT_BROWSER_EXECUTABLE } : {}) });
  try {
    for (const width of [320, 390, 768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      await page.setContent(`<style>${fs.readFileSync('public/assets/vendor/css/core.css', 'utf8')} ${css}</style><meta name="csrf-token" content="test"><div style="padding:16px">${badge}<br>${entries.map((division, index) => button
        .replaceAll("{{ route('registrations.withdraw', $registration) }}", `/withdraw/${index}`)
        .replaceAll('{{ $registration->display_name }}', escape(player))
        .replaceAll("{{ $registration->categoryEvent?->category?->name ?? 'Division not specified' }}", escape(division))).join('')}</div>`);
      await page.addScriptTag({ path: 'node_modules/jquery/dist/jquery.js' });
      await page.evaluate(() => {
        window.dialogs = [];
        window.Swal = { fire: options => { window.dialogs.push(options); return Promise.resolve({ value: window.dialogs.length % 2 === 1 }); } };
      });
      await page.addScriptTag({ path: 'public/js/event-show.js' });
      assert.equal(await page.locator('img').count(), 0);
      assert.equal(await page.locator('.event-ctr-certified [data-ctr-mark]').count(), 1);
      assert.match(await page.locator('.event-ctr-certified').textContent(), /CTR\s+Certified/);
      assert.equal(await page.locator('.event-ctr-certified [data-ctr-badge]').count(), 0, 'Certification must not imply a player rating');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false);
      for (let index = 0; index < entries.length; index++) {
        const action = page.locator('.withDrawPlayer').nth(index);
        assert.match(await action.innerText(), new RegExp(entries[index]));
        assert.ok((await action.boundingBox()).height >= 44);
        await action.click();
        await page.waitForFunction(count => window.dialogs.length === count, (index + 1) * 2);
        const dialogs = await page.evaluate(() => window.dialogs.slice(-2));
        assert.ok(dialogs[0].html.includes(escape(player).replaceAll('&quot;', '"')));
        assert.ok(dialogs[0].html.includes(entries[index]));
        assert.ok(dialogs[1].text.includes(`${player} — ${entries[index]}`));
        assert.equal(await page.locator('form').count(), 0, 'Cancelling confirmation must not submit');
      }
      await page.close();
    }
  } finally { await browser.close(); }
});
