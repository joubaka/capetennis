/* Actual Laravel-rendered fictional snapshots; no application mutations. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const output = path.join(root, 'storage/app/testing/interpro-preview');
const publicRoot = path.join(root, 'public');
const mime = file => ({ '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.png': 'image/png' }[path.extname(file)] || 'application/octet-stream');
function localFile(url) {
  const pathname = decodeURIComponent(new URL(url, 'http://localhost').pathname);
  const assetPath = pathname.replace(/^\/ct\/public\//, '/');
  const preview = path.resolve(output, '.' + (pathname === '/' ? '/index.html' : pathname));
  if (preview.startsWith(output + path.sep) && fs.existsSync(preview) && fs.statSync(preview).isFile()) return preview;
  const asset = path.resolve(publicRoot, '.' + assetPath);
  return asset.startsWith(publicRoot + path.sep) && fs.existsSync(asset) && fs.statSync(asset).isFile() ? asset : null;
}
if (process.argv.includes('--serve')) {
  require('node:http').createServer((req, res) => {
    if (req.method !== 'GET') { res.writeHead(405); return res.end(); }
    const file = localFile(req.url);
    if (!file) { res.writeHead(404); return res.end('Preview file not found.'); }
    res.writeHead(200, { 'Content-Type': mime(file), 'Cache-Control': 'no-store' });
    fs.createReadStream(file).pipe(res);
  }).listen(8765, '127.0.0.1', () => console.log('Fictional Interpro preview: http://127.0.0.1:8765/'));
} else {
  (async () => {
    const script = fs.readFileSync(path.join(output, 'print-script.js'), 'utf8').replace(/<\/?script\b[^>]*>/gi, '');
    for (const button of ['#btn-print-bracket', '#btn-print-empty-bracket', '#btn-print-draw-pack']) {
      for (const response of ['success', 'empty', 'failure']) {
        const handlers = {}, printed = [], errors = [];
        const chain = { on(event, handler) { handlers[this.selector] = handler; return this; }, prop() { return this; }, html() { return this; }, is() { return this.selector === '#pack-playoff-fixtures'; } };
        const jquery = selector => Object.assign(Object.create(chain), { selector });
        jquery.get = () => ({
          done(callback) { if (response !== 'failure') callback(response === 'success' ? '<section class="interpro-feed-in"><table><tr><td>FIXED ROUTES</td></tr></table></section>' : ''); return this; },
          fail(callback) { if (response === 'failure') callback({}, 'error', 'offline'); return this; },
          always(callback) { callback(); return this; }
        });
        const context = {
          jQuery: jquery, console: { log() {}, error() {} }, APP_URL: 'http://localhost', DRAW_ID: 1,
          toastr: { error: text => errors.push(text), warning: text => errors.push(text) },
          window: {
            RR_OOP: [999, 2001, 2002, 2003, 3001, 3002, 3003, 3004, 3005, 3006, 3007, 3008, 3009, 3010, 3011, 4001, 4002, 4003, 4004].map(nr => ({ id: nr, match_nr: nr, stage: nr === 999 ? 'RR' : 'PLATE', home: 'Fictional one', away: 'Fictional two', round: 1 })),
            open: () => ({ document: { write: html => printed.push(html), close() {}, querySelectorAll: () => [] } })
          }
        };
        require('node:vm').runInNewContext(script, context);
        assert.equal(typeof handlers[button], 'function');
        handlers[button].call({});
        if (response === 'success') {
          assert.equal(printed.length, 1);
          if (button === '#btn-print-draw-pack') {
            for (const number of [3001, 3007, 3011, 4004]) assert.match(printed[0], new RegExp('<td>' + number + '</td>'));
            assert.doesNotMatch(printed[0], /<td>999<\/td>/);
          } else assert.match(printed[0], /FIXED ROUTES/);
        }
        else { assert.equal(printed.length, 0); assert.match(errors.join(' '), /retry before printing/); }
      }
    }
    const { chromium } = require('playwright');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const report = [];
    try {
      const page = await browser.newPage();
      const missingAssets = new Set();
      await page.route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (request.method() !== 'GET' || !['localhost', '127.0.0.1'].includes(url.hostname)) return route.abort();
        const file = localFile(url.href);
        if (!file && ['stylesheet', 'script', 'font'].includes(request.resourceType())) missingAssets.add(url.pathname);
        return file ? route.fulfill({ path: file, contentType: mime(file) }) : route.abort();
      });
      for (const width of [360, 390, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const stage of ['setup', 'round-robin', 'carry', 'final', 'blank']) {
          await page.goto(`http://localhost/${stage}.html`, { waitUntil: 'networkidle' });
          assert.equal(await page.locator('body').evaluate(el => el.scrollWidth <= window.innerWidth + 1), true, `${stage} ${width}: page containment`);
          if (stage === 'round-robin') {
            assert.equal(await page.locator('#rr-standings-wrapper table').count(), 4);
            for (const group of ['A', 'B', 'C', 'D']) assert.match(await page.locator('#rr-standings-wrapper').innerText(), new RegExp('Group ' + group + ' Player 4'));
          }
          if (['carry', 'final', 'blank'].includes(stage)) {
            const section = page.locator('.interpro-feed-in');
            assert.equal(await section.count(), 1);
            assert.equal(await section.locator('tbody tr').count(), 18);
            assert.equal(await section.locator('foreignObject[data-match]').count(), 18);
            assert.equal(await section.locator('.interpro-bracket-svg').count(), 3);
            assert.equal(await section.locator('.interpro-route-table').isVisible(), false, 'Screen shows connected brackets instead of the print table');
            assert.equal(await page.locator('#bracket-zoom-bar').isVisible(), true, 'Zoom controls are available at every width');
            assert.ok(await section.locator('.interpro-match-name').first().evaluate(el => parseFloat(getComputedStyle(el).fontSize)) >= 14, 'Readable player text');
            assert.equal(await section.locator('.interpro-match').evaluateAll(cards => cards.every(card => card.scrollHeight <= card.clientHeight)), true, `${stage} ${width}: match content fits`);
            if (stage === 'carry') assert.match(await section.innerText(), /First result stands \(match/);
            if (stage === 'blank') assert.doesNotMatch(await section.innerText(), /6-2|Group [A-D] Player/);
          }
          if (stage === 'final') assert.equal(await page.locator('.ct-final-position').count(), 12);
          await page.screenshot({ path: path.join(output, `${stage}-${width}.png`), fullPage: true });
          if (stage === 'carry') {
            await page.locator('#bracket-zoom-in').click();
            assert.equal(await page.locator('#bracket-zoom-label').innerText(), '120%');
            await page.locator('#bracket-zoom-reset').click();
            assert.equal(await page.locator('#bracket-zoom-label').innerText(), '100%');
            const scroller = page.locator('.interpro-bracket-scroll').nth(1);
            assert.equal(await scroller.evaluate(el => {
              el.scrollLeft = el.scrollWidth;
              const cell = el.querySelector('[data-match="3011"]').getBoundingClientRect();
              const bounds = el.getBoundingClientRect();
              return cell.right <= bounds.right + 1 && cell.left >= bounds.left - 1;
            }), true, 'Later placement rounds remain reachable without shrinking text');
            await page.screenshot({ path: path.join(output, `carry-${width}-scrolled.png`), fullPage: true });
          }
          report.push({ stage, width, contained: true });
        }
        // The same Blade response is also hosted by the published draw page.
        // Use its actual inline wrapper rules and public stylesheet, retaining
        // the real rendered fictional bracket rather than a handwritten graph.
        await page.goto('http://localhost/carry.html', { waitUntil: 'networkidle' });
        await page.evaluate(() => {
          document.querySelector('link[href$="draw-workspace.css"]').remove();
          const host = document.querySelector('#main-bracket-wrapper');
          const adminInner = host.querySelector('#bracket-zoom-inner');
          adminInner.replaceWith(...adminInner.childNodes);
          host.className = 'bracket-zoom-inner';
          const scroll = document.createElement('div');
          scroll.className = 'bracket-zoom-scroll';
          host.replaceWith(scroll);
          scroll.append(host);
        });
        const publicTemplate = fs.readFileSync(path.join(root, 'resources/views/frontend/roundrobin/show.blade.php'), 'utf8');
        await page.addStyleTag({ content: publicTemplate.match(/<style>([\s\S]*?)<\/style>/)[1] });
        await page.addStyleTag({ content: fs.readFileSync(path.join(publicRoot, 'css/public-draw.css'), 'utf8') });
        assert.equal(await page.locator('body').evaluate(el => el.scrollWidth <= window.innerWidth + 1), true, `public ${width}: page containment`);
        assert.equal(await page.locator('.interpro-feed-in').evaluate(el => el.getBoundingClientRect().width <= window.innerWidth), true, `public ${width}: prose stays within the viewport`);
        assert.equal(await page.locator('.interpro-feed-in > p').evaluateAll(elements => elements.every(el => el.getBoundingClientRect().right <= window.innerWidth)), true, `public ${width}: paragraph containment`);
        assert.equal(await page.locator('.ct-final-position').evaluateAll(elements => elements.every(el => el.getBoundingClientRect().right <= window.innerWidth)), true, `public ${width}: final position containment`);
        assert.ok(await page.locator('.interpro-match-name').first().evaluate(el => parseFloat(getComputedStyle(el).fontSize)) >= 14);
        await page.screenshot({ path: path.join(output, `public-${width}.png`), fullPage: true });
        report.push({ stage: 'public', width, contained: true });
      }
      await page.setViewportSize({ width: 1123, height: 794 });
      await page.emulateMedia({ media: 'print' });
      await page.goto('http://localhost/print.html', { waitUntil: 'networkidle' });
      assert.equal(await page.locator('.interpro-feed-in tbody tr').count(), 18);
      assert.equal(await page.locator('.interpro-diagrams').isVisible(), false);
      assert.equal(await page.locator('.interpro-route-table').isVisible(), true);
      assert.equal(await page.locator('.ct-final-position').count(), 12);
      await page.screenshot({ path: path.join(output, 'print.png'), fullPage: true });
      await page.pdf({ path: path.join(output, 'interpro-trials-preview.pdf'), format: 'A4', landscape: true, printBackground: true, margin: { top: '8mm', bottom: '8mm', left: '8mm', right: '8mm' } });
      assert.deepEqual([...missingAssets], [], 'All local styles/scripts/fonts loaded');
      fs.writeFileSync(path.join(output, 'browser-checks.json'), JSON.stringify({ checkedAt: new Date().toISOString(), report, print: true }, null, 2));
      console.log(JSON.stringify({ checks: report.length, print: true, output }));
    } finally { await browser.close(); }
  })().catch(error => { console.error(error); process.exitCode = 1; });
}
