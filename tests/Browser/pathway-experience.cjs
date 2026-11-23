/* Fictional Laravel HTTP fixtures; all requests intercepted with local assets. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const fixtureRoot = path.join(root, 'storage/framework/testing');

(async () => {
  const browser = await chromium.launch({channel: 'msedge', headless: true});
  try {
    const page = await browser.newPage();
    let fixture;
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/pathway-review') return route.fulfill({contentType: 'text/html',
        body: fs.readFileSync(fixture, 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')});
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (target.startsWith(publicRoot + path.sep) && fs.existsSync(target) && fs.statSync(target).isFile()) return route.fulfill({path: target});
      return route.abort();
    });
    for (const [name, filename, selector] of [
      ['personal', 'pathway-events-qa.html', '#player-development-pathway'],
      ['public', 'ctr-marketing-guest-intro.html', '[data-public-pathway]'],
    ]) {
      fixture = path.join(fixtureRoot, filename);
      assert.ok(fs.existsSync(fixture), 'Generate current fictional Laravel fixture: ' + fixture);
      for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({width, height: 1000});
        await page.goto('http://localhost/pathway-review', {waitUntil: 'networkidle'});
        await page.addScriptTag({path: path.join(publicRoot, 'assets/js/pathway-explore.js')});
        const panel = page.locator(selector);
        const marker = panel.locator('[aria-current="step"]');
        const originalMarker = await marker.evaluateAll(nodes => nodes.map(node => ({stage: node.dataset.pathwayCompetition || node.querySelector('summary strong').textContent, label: node.querySelector('.pathway-here').textContent})));
        const summaries = panel.locator('.pathway-explore summary');
        assert.equal(await summaries.count(), 5);
        // Native keyboard activation must reveal a wide sheet, not a tall narrow card.
        await summaries.last().focus();
        await page.keyboard.press('Enter');
        await panel.locator('[data-pathway-exploration-panel]').waitFor({state: 'visible'});
        assert.equal(await panel.locator('.pathway-map .pathway-explore-body').count(), 4);
        if (width === 1440) assert.ok(await panel.locator('.pathway-map').evaluate(el => el.getBoundingClientRect().height < 300));
        await summaries.first().click();
        await page.waitForFunction(selector => document.querySelector(selector).querySelector('[data-pathway-exploring-label]').textContent.includes('Red'), selector);
        // Cached workspaces clone DOM without listeners; remount must restore the sheet.
        await panel.evaluate(el => {
          const journey = el.querySelector('[data-pathway-journey]');
          journey.replaceWith(journey.cloneNode(true));
          document.dispatchEvent(new CustomEvent('my-tennis:mounted'));
        });
        await panel.locator('.pathway-explore summary').nth(3).click();
        await page.waitForFunction(selector => document.querySelector(selector).querySelector('[data-pathway-exploring-label]').textContent.includes('Wilson A'), selector);
        assert.deepEqual(await marker.evaluateAll(nodes => nodes.map(node => ({stage: node.dataset.pathwayCompetition || node.querySelector('summary strong').textContent, label: node.querySelector('.pathway-here').textContent}))), originalMarker);
        const overflow = await panel.evaluate(el => [...el.querySelectorAll('article,button,summary,.pathway-here,.pathway-band')]
          .filter(node => {const box = node.getBoundingClientRect(); return box.width && (box.left < -1 || box.right > innerWidth + 1);})
          .map(node => node.tagName));
        assert.deepEqual(overflow, [], `${name} ${width}px containment`);
        const heading = name === 'personal' ? panel.locator('#ctr-pathway-heading') : panel.locator('.pathway-demo h3');
        assert.equal(await heading.evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
        if (name === 'personal') assert.match(await panel.locator('.pathway-hero').textContent(), /Latest saved stage.*update pending/);
        if (name === 'public') {
          await page.addScriptTag({path: path.join(publicRoot, 'assets/js/pathway-share.js')});
          await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', {configurable: true, value: {writeText: async () => {throw new Error('Unavailable');}}}));
          await page.locator('[data-pathway-copy]').click();
          assert.match(await page.locator('[data-pathway-share-status]').textContent(), /Select and copy/);
          assert.equal(await page.evaluate(() => document.activeElement.id), 'pathway-share-message');
          await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', {configurable: true, value: {writeText: async text => {window.copiedPathwayMessage = text;}}}));
          await page.locator('[data-pathway-copy]').click();
          assert.match(await page.locator('[data-pathway-share-status]').textContent(), /Message copied/);
          assert.match(await page.evaluate(() => window.copiedPathwayMessage), /https:\/\/capetennis.co.za\/ctr#pathway/);
          const share = new URL(await page.getByRole('link', {name: /Share on WhatsApp/}).getAttribute('href'));
          assert.equal(share.hostname, 'wa.me');
          assert.match(share.searchParams.get('text'), /https:\/\/capetennis.co.za\/ctr#pathway/);
          await page.emulateMedia({media: 'print'});
          const poster = page.locator('#pathway-coach-kit > .pathway-print-poster');
          assert.equal(await poster.isVisible(), true);
          assert.equal(await page.locator('[data-pathway-copy]').isVisible(), false);
          assert.ok(await poster.evaluate(el => el.complete && el.naturalWidth > 0));
          if (width === 1440) await page.screenshot({path: path.join(fixtureRoot, 'pathway-review-print.png'), fullPage: true});
          await page.emulateMedia({media: 'screen'});
        }
        await panel.scrollIntoViewIfNeeded();
        await page.screenshot({path: path.join(fixtureRoot, `pathway-review-${name}-${width}.png`), fullPage: true});
        if (width === 1440) {
          const clip = await panel.evaluate(el => {
            const box = el.getBoundingClientRect();
            return {x: box.left + scrollX, y: box.top + scrollY,
              width: box.width, height: Math.min(box.height, 1150), scale: 1};
          });
          const capture = await page.context().newCDPSession(page);
          const {data} = await capture.send('Page.captureScreenshot', {format: 'png', clip,
            captureBeyondViewport: true});
          fs.writeFileSync(path.join(fixtureRoot, `pathway-review-${name}-preview.png`), Buffer.from(data, 'base64'));
          await capture.detach();
        }
        console.log(`PASS ${name} ${width}px: shared sheet, keyboard, clone remount, marker, contrast, containment${name === 'public' ? ', sharing fallback' : ''}`);
      }
    }
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
