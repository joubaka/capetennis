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
        const handlers = {}, printed = [], errors = [], popups = [];
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
            open: () => { const popup = { prints:0, print() { this.prints++; }, document: { write: html => printed.push(html), close() {}, querySelectorAll: () => [] } }; popups.push(popup); return popup; }
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
          const popup = popups[0];
          popup.onload();
          assert.equal(popup.prints, 1);
          for (const rendered of [undefined, 'loading', 'error']) {
            popup.document.querySelectorAll = selector => selector === 'ct-interpro-bracket' ? [{dataset:{rendered}}] : [];
            popup.onload();
            assert.equal(popup.prints, 1, 'Missing or failed adapter rendering must never print a blank bracket');
          }
        }
        else { assert.equal(printed.length, 0); assert.match(errors.join(' '), /retry before printing/); }
      }
    }
    const { chromium } = require('playwright');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const report = [];
    let interproMetrics;
    const typography = () => {
      const board=document.querySelector('ct-interpro-bracket .fm-board')||document.querySelector('#fm-board');
      return Object.fromEntries(['.fm-slot','.fm-player-identity','.fm-match-head','.fm-round-title'].map(selector=>{
        const style=getComputedStyle(board.querySelector(selector === '.fm-slot' ? '.fm-slot:not(.winner):not(.loser)' : selector));
        return [selector,Object.fromEntries(['fontFamily','fontSize','fontWeight','lineHeight','padding','borderRadius'].map(key=>[key,style[key]]))];
      }));
    };
    try {
      const page = await browser.newPage();
      const missingAssets = new Set();
      await page.context().route('**/*', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (request.method() !== 'GET' || !['localhost', '127.0.0.1'].includes(url.hostname)) return route.abort();
        const file = localFile(url.href);
        if (!file && ['stylesheet', 'script', 'font'].includes(request.resourceType())) missingAssets.add(url.pathname);
        return file ? route.fulfill({ path: file, contentType: mime(file) }) : route.abort();
      });
      for (const width of [360, 390, 1200, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const stage of ['setup', 'round-robin', 'carry', 'final', 'blank']) {
          await page.goto(`http://localhost/${stage}.html`, { waitUntil: 'networkidle' });
          assert.equal(await page.locator('body').evaluate(el => el.scrollWidth <= window.innerWidth + 1), true, `${stage} ${width}: page containment`);
          if (stage === 'round-robin') {
            assert.equal(await page.locator('#rr-standings-wrapper table').count(), 4);
            for (const group of ['A', 'B', 'C', 'D']) assert.match(await page.locator('#rr-standings-wrapper').innerText(), new RegExp('Group ' + group + ' Player 4'));
          }
          if (['carry', 'final', 'blank'].includes(stage)) {
            await page.waitForSelector('ct-interpro-bracket[data-rendered="true"]');
            assert.equal(await page.locator('[data-bracket-loading]').evaluateAll(nodes=>nodes.every(node=>getComputedStyle(node).display==='none')),true,'Completed mounts never leave a visible loading placeholder');
            const section = page.locator('.interpro-feed-in');
            assert.equal(await section.count(), 1);
            assert.equal(await section.locator('tbody tr').count(), 18);
            assert.equal(await section.locator('.fm-match[data-match]').count(), 18);
            assert.equal(await section.locator('.fm-board').count(), 2);
            assert.equal(await section.locator('[data-board="main"] [data-match]').count(), 12);
            assert.equal(await section.locator('[data-board="placements"] [data-match]').count(), 6);
            for (const [round, count] of [[1, 4], [2, 2], [3, 2], [4, 1]]) {
              assert.equal(await section.locator(`[data-board="main"] [data-stage="PLATE"][data-round="${round}"]`).count(), count, `Lower round ${round} has the expected conventional bracket size`);
            }
            assert.equal(await section.locator('[marker-end],marker,[stroke-dasharray]').count(), 0, 'Only the normal Monrad bracket lines are drawn');
            const projection = JSON.parse(await section.locator('[data-interpro-payload]').textContent());
            assert.equal(Object.values(projection.matches).flatMap(match => match.sources).filter(source => ['winner', 'loser'].includes(source.type)).length, 24, 'All canonical dependencies remain in data');
            assert.equal(await section.locator('.interpro-route-table').isVisible(), false, 'Source table is optional detail');
            assert.equal(await page.locator('#bracket-zoom-bar').isVisible(), true, 'Zoom controls are available at every width');
            assert.equal(await section.locator('.fm-slot').first().evaluate(el => getComputedStyle(el).fontSize), '13px', 'Actual Monrad player sizing');
            assert.equal(await section.locator('.fm-match').first().evaluate(el => el.style.width), '220px');
            assert.equal(await section.locator('.fm-player-identity').evaluateAll(names => names.every(name => name.title && getComputedStyle(name).overflow === 'hidden')), true, 'Names retain full titles with the actual Monrad overflow behavior');
            assert.equal(await section.locator('.interpro-match-source').count(), 0, 'Resolved names have no invented provenance subtitles');
            assert.match(await section.locator('[data-match="3007"] .fm-slot').first().getAttribute('title'), /Loser of Match 2002/);
            assert.match(await section.locator('[data-match="3008"] .fm-slot').first().getAttribute('title'), /Loser of Match 2001/);
            const geometry = await section.locator('[data-match]').evaluateAll(nodes => Object.fromEntries(nodes.map(node => [node.dataset.match, {top:Number(node.dataset.slotTop),bottom:Number(node.dataset.slotBottom)}])));
            const middle = nr => (geometry[nr].top + geometry[nr].bottom) / 2;
            assert.equal(geometry[2003].top, middle(2001));
            assert.equal(geometry[2003].bottom, middle(2002));
            assert.equal(geometry[3009].top, middle(3007));
            assert.equal(geometry[3009].bottom, middle(3008));
            assert.equal(await section.locator('.fm-winner-endpoint').count(), 6);
            const bands=await section.locator('.fm-section-label,.fm-round-title').allTextContents();
            for(const band of ['Positions 1–2','Positions 3–4','Positions 5–6','Positions 7–8','Positions 9–10','Positions 11–12']) assert.ok(bands.includes(band), `Visible ${band} label`);
            for(const [number,position] of [[2003,1],[3009,3],[3011,5],[3010,7],[4003,9],[4004,11]]) assert.equal(await section.locator(`[data-match="${number}"]`).getAttribute('data-position'),String(position));
            if(stage!=='blank') assert.deepEqual(await page.locator('.ct-final-position strong').allTextContents(), Array.from({length:12},(_,index)=>String(index+1)));
            if (stage === 'carry') assert.match(await section.innerText(), /First result stands \(match/);
            if (stage === 'blank') assert.doesNotMatch(await section.innerText(), /6-2|Group [A-D] Player/);
          }
          if (stage === 'final') assert.equal(await page.locator('.ct-final-position').count(), 12);
          if (stage === 'carry' && width === 1440) {
            interproMetrics = await page.evaluate(typography);
            await page.setViewportSize({width:1800,height:1200});
            await page.locator('main.preview-content').evaluate(el => {el.style.maxWidth='none';});
            await page.locator('.fm-board').first().screenshot({ path: path.join(output, 'bracket-overview.png') });
            await page.locator('.fm-board').nth(1).screenshot({ path: path.join(output, 'placement-overview.png') });
            await page.locator('main.preview-content').evaluate(el => {el.style.maxWidth='';});
            await page.setViewportSize({width,height:1000});
          }
          await page.screenshot({ path: path.join(output, `${stage}-${width}.png`), fullPage: true });
          if (stage === 'carry') {
            const beforeZoom = await page.locator('.fm-board').first().evaluate(el => el.getBoundingClientRect().width);
            await page.locator('#bracket-zoom-in').click();
            assert.equal(await page.locator('#bracket-zoom-label').innerText(), '120%');
            assert.ok(await page.locator('.fm-board').first().evaluate(el => el.getBoundingClientRect().width) >= beforeZoom * 1.15, 'Admin zoom magnifies the board');
            await page.locator('#bracket-zoom-reset').click();
            assert.equal(await page.locator('#bracket-zoom-label').innerText(), '100%');
            const scroller = page.locator('.interpro-bracket-scroll').first();
            assert.equal(await scroller.evaluate(el => {
              const cell = el.querySelector('[data-match="3009"]').getBoundingClientRect();
              const bounds = el.getBoundingClientRect();
              el.scrollLeft += cell.left - bounds.left - 8;
              const moved = el.querySelector('[data-match="3009"]').getBoundingClientRect();
              return moved.right <= bounds.right + 1 && moved.left >= bounds.left - 1;
            }), true, 'Later placement rounds remain reachable without shrinking text');
            await page.screenshot({ path: path.join(output, `carry-${width}-scrolled.png`), fullPage: true });
          }
          report.push({ stage, width, contained: true });
        }
        // The same Blade response is also hosted by the published draw page.
        // Use its actual inline wrapper rules and public stylesheet, retaining
        // the real rendered fictional bracket rather than a handwritten graph.
        await page.goto('http://localhost/carry.html', { waitUntil: 'networkidle' });
        const rawFragment = await page.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('#bracket-zoom-inner').innerHTML, fs.readFileSync(path.join(output, 'carry.html'), 'utf8'));
        const publicTemplate = fs.readFileSync(path.join(root, 'resources/views/frontend/roundrobin/show.blade.php'), 'utf8');
        const mainHeading = publicTemplate.indexOf('>Main Bracket</h5>');
        const publicHeader = publicTemplate.slice(publicTemplate.lastIndexOf('<div class="card-header', mainHeading), publicTemplate.indexOf('<div class="card-body p-0">', mainHeading));
        await page.evaluate(publicHeader => {
          document.querySelector('link[href$="draw-workspace.css"]').remove();
          const host = document.querySelector('#main-bracket-wrapper');
          const adminInner = host.querySelector('#bracket-zoom-inner');
          adminInner.replaceWith(...adminInner.childNodes);
          host.className = 'bracket-zoom-inner';
          document.querySelector('#bracket-zoom-bar').remove();
          const card = document.createElement('div');
          card.className = 'card';
          card.innerHTML = publicHeader + '<div class="card-body p-0"><div class="bracket-zoom-outer"><div class="bracket-zoom-scroll"></div></div></div>';
          host.replaceWith(card);
          card.querySelector('.bracket-zoom-scroll').append(host);
        }, publicHeader);
        await page.addStyleTag({ content: publicTemplate.match(/<style>([\s\S]*?)<\/style>/)[1] });
        await page.addStyleTag({ content: fs.readFileSync(path.join(publicRoot, 'css/public-draw.css'), 'utf8') });
        await page.locator('#main-bracket-wrapper').evaluate((host, html) => { host.innerHTML = html; }, rawFragment);
        await page.waitForSelector('ct-interpro-bracket[data-rendered="true"]');
        assert.equal(await page.locator('body').evaluate(el => el.scrollWidth <= window.innerWidth + 1), true, `public ${width}: page containment`);
        assert.equal(await page.locator('.interpro-feed-in').evaluate(el => el.getBoundingClientRect().width <= window.innerWidth), true, `public ${width}: prose stays within the viewport`);
        assert.equal(await page.locator('.interpro-feed-in > p').evaluateAll(elements => elements.every(el => el.getBoundingClientRect().right <= window.innerWidth)), true, `public ${width}: paragraph containment`);
        assert.equal(await page.locator('.ct-final-position').evaluateAll(elements => elements.every(el => el.getBoundingClientRect().right <= window.innerWidth)), true, `public ${width}: final position containment`);
        assert.equal(await page.locator('.fm-slot').first().evaluate(el => getComputedStyle(el).fontSize), '13px');
        await page.addScriptTag({ content: publicTemplate.match(/<script>\s*(\(function\(\)\{[\s\S]*?)<\/script>/)[1] });
        const publicBase = await page.locator('.fm-board').first().evaluate(el => el.getBoundingClientRect().width);
        await page.locator('.btn-bracket-zoom[data-dir="in"]').click();
        assert.ok(await page.locator('.fm-board').first().evaluate(el => el.getBoundingClientRect().width) >= publicBase * 1.1, 'Public header zoom controls magnify the correct bracket');
        assert.equal(await page.locator('.bracket-zoom-label').innerText(), '115%');
        await page.locator('.btn-bracket-zoom[data-dir="fit"]').click();
        if (width >= 1440) assert.equal(await page.locator('.fm-board').first().evaluate(el => el.getBoundingClientRect().width <= el.closest('.bracket-zoom-scroll').clientWidth + 1), true, 'Desktop Fit restores the complete board');
        // Return to the normal initial state for the review screenshot.
        await page.evaluate(() => { const inner = document.querySelector('.bracket-zoom-inner'); inner.style.zoom = 1; inner.style.width = '100%'; });
        await page.screenshot({ path: path.join(output, `public-${width}.png`), fullPage: true });
        report.push({ stage: 'public', width, contained: true });
      }
      // Exercise the actual editable Monrad page with the same shared factories.
      await page.setViewportSize({width:1440,height:1000});
      await page.route('**/flexible-monrad/demo-preview', route => route.fulfill({path:path.join(output,'monrad-graph.json'),contentType:'application/json'}));
      await page.goto('http://localhost/monrad.html', {waitUntil:'networkidle'});
      await page.locator('#fm-size').selectOption('4');
      for (const [index, slot] of ['aa','ab','ba','bb'].entries()) {
        await page.locator('#fm-players .fm-player').filter({hasText:'Demo Player 0'+(index+1)}).click();
        await page.locator('#fm-board [data-slot="'+slot+'"]').click();
      }
      await page.locator('#fm-generate').click();
      await page.getByText('Demo fixtures generated.', {exact:false}).waitFor();
      assert.equal(await page.locator('#fm-board .fm-match').count(), 4);
      assert.equal(await page.locator('#fm-board .fm-winner-endpoint').count(), 2);
      assert.equal(await page.locator('#fm-board .fm-slot').first().evaluate(el => getComputedStyle(el).fontSize), '13px');
      assert.deepEqual(await page.evaluate(typography), interproMetrics, 'Actual Monrad and Interpro share exact computed typography, chip padding and radius');
      await page.locator('#fm-board').screenshot({path:path.join(output,'actual-monrad-smoke.png')});
      await page.unroute('**/flexible-monrad/demo-preview');

      const popupPromise = page.waitForEvent('popup');
      await page.evaluate(html => {const popup=window.open('','_blank');popup.document.write(html);popup.document.close();}, fs.readFileSync(path.join(output,'print.html'),'utf8'));
      const popup = await popupPromise;
      await popup.waitForLoadState('networkidle');
      await popup.waitForSelector('ct-interpro-bracket[data-rendered="true"]');
      assert.equal(await popup.locator('[data-bracket-loading]').evaluateAll(nodes=>nodes.every(node=>getComputedStyle(node).display==='none')),true,'Parser-added popup loader remains hidden after rendering');
      assert.equal(await popup.locator('.fm-match').count(),18,'document.write popup upgrades the read-only shared renderer');
      await popup.close();
      await page.setViewportSize({ width: 1123, height: 794 });
      await page.emulateMedia({ media: 'print' });
      await page.goto('http://localhost/print.html', { waitUntil: 'networkidle' });
      await page.waitForSelector('ct-interpro-bracket[data-rendered="true"]');
      assert.equal(await page.locator('[data-bracket-loading]').evaluateAll(nodes=>nodes.every(node=>getComputedStyle(node).display==='none')),true,'Print layout has no visible loader');
      assert.equal(await page.locator('.interpro-feed-in tbody tr').count(), 18);
      assert.equal(await page.locator('ct-interpro-bracket').isVisible(), true);
      assert.equal(await page.locator('.interpro-route-table').isVisible(), false);
      assert.equal(await page.locator('.fm-match').count(), 18);
      assert.equal(await page.locator('[marker-end],marker,[stroke-dasharray]').count(), 0);
      assert.equal(await page.locator('.ct-final-position').count(), 12);
      await page.screenshot({ path: path.join(output, 'print.png'), fullPage: true });
      await page.pdf({ path: path.join(output, 'interpro-trials-preview.pdf'), format: 'A4', preferCSSPageSize: true, printBackground: true, margin: { top: '8mm', bottom: '8mm', left: '8mm', right: '8mm' } });
      await page.goto('http://localhost/blank.html', { waitUntil: 'networkidle' });
      const blankBracket = await page.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('#bracket-zoom-inner').innerHTML, fs.readFileSync(path.join(output, 'blank.html'), 'utf8'));
      await page.goto('http://localhost/print.html', { waitUntil: 'networkidle' });
      await page.locator('.bracket-print-wrap').evaluate((el, html) => { el.innerHTML = html; }, blankBracket);
      await page.waitForSelector('ct-interpro-bracket[data-rendered="true"]');
      assert.equal(await page.locator('.fm-match').count(), 18);
      assert.equal(await page.locator('.fm-slot strong[data-slot]').count(), 0);
      assert.equal(await page.locator('.ct-final-position').count(), 0);
      assert.equal(await page.locator('.fm-match-schedule').count(), 0);
      await page.screenshot({ path: path.join(output, 'print-blank.png'), fullPage: true });
      await page.pdf({ path: path.join(output, 'interpro-trials-blank.pdf'), format: 'A4', preferCSSPageSize: true, printBackground: true, margin: { top: '8mm', bottom: '8mm', left: '8mm', right: '8mm' } });
      assert.deepEqual([...missingAssets], [], 'All local styles/scripts/fonts loaded');
      fs.writeFileSync(path.join(output, 'browser-checks.json'), JSON.stringify({ checkedAt: new Date().toISOString(), report, print: true, monrad: true, popup: true }, null, 2));
      console.log(JSON.stringify({ checks: report.length, print: true, monrad: true, popup: true, output }));
    } finally { await browser.close(); }
  })().catch(error => { console.error(error); process.exitCode = 1; });
}
