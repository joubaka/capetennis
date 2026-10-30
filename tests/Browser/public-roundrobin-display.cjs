// Isolated public viewer fixture using shipped renderer, tab markup and styles.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('public schedule is default and matrix venue text stays inside cells at phone and desktop widths', async () => {
  const browser = await chromium.launch({headless: true, channel: 'msedge'});
  try {
    const view = read('resources/views/frontend/roundrobin/show.blade.php');
    const tabs = view.match(/<ul class="nav nav-tabs ct-public-draw-nav"[\s\S]*?<\/ul>/)[0]
      .replace(/@unless[^\n]*|@endunless/g, '');
    // Match the base theme followed by this view's stylesheet links and inline style.
    const styles = ['public/assets/vendor/css/core.css', 'public/assets/vendor/css/theme-default.css',
      'public/css/frontend-workspace.css', 'public/css/mobile-workspace.css',
      'public/css/public-draw.css', 'public/assets/css/draw-roundrobin.css'].map(read).join('\n')
      + view.match(/<style>([\s\S]*?)<\/style>/)[1];
    for (const width of [320, 390, 768, 1450]) {
      const page = await browser.newPage({viewport: {width, height: 1000}});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => route.abort());
      await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style>
        <main style="max-width:1450px;margin:auto;padding:16px"><div id="round-robin-app" class="rr-public-view" data-draw-id="1">${tabs}
        <div class="tab-content"><div class="tab-pane fade" id="matrix-pane"><div class="card"><div class="card-body p-0"><div id="rr-matrix-wrapper" class="rr-public-matrices p-2"></div></div></div></div>
        <div class="tab-pane fade show active" id="oop-pane"><table id="rr-order-table"><tbody></tbody></table></div>
        <div class="tab-pane fade" id="standings-pane"></div><div class="tab-pane fade" id="main-bracket-pane"></div></div></div></main>`);
      await page.addScriptTag({path: path.join(root, 'node_modules/jquery/dist/jquery.min.js')});
      await page.addScriptTag({path: path.join(root, 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js')});
      await page.evaluate(() => {
        const names = ['Alexandra Example Longsurname', 'Jasper Example', 'Karli Example', 'Liam Example', 'Lize Example'];
        window.RR_GROUPS = [{id: 1, name: 'A', registrations: names.map((name, i) => ({id: i + 1, display_name: name, pivot: {seed: i + 1}}))}];
        const fixtures = [];
        for (let i = 1; i <= 5; i++) for (let j = i + 1; j <= 5; j++) fixtures.push({id: fixtures.length + 1, r1_id: i, r2_id: j,
          time: '2026-10-31 11:15:00', venue_name: 'Fictional Very Long Tennis Sports Club and Academy', all_sets: []});
        fixtures[0].schedule_hidden = true;
        fixtures[0].venue_name = 'SECRET HIDDEN VENUE';
        window.RR_FIXTURES = {1: fixtures};
        window.RR_OOP = [
          {id: 2, match_nr: 2, round: 1, stage: 'RR', time: '2026-10-31 09:00:00'},
          {id: 7, match_nr: 7, round: 1, stage: 'MAIN', time: '2026-11-01 08:00:00'},
          {id: 3, match_nr: 3, round: 2, stage: 'RR', time: '2026-10-31 08:20:00'},
          {id: 1, match_nr: 1, round: 1, stage: 'RR', time: '2026-10-31 08:00:00'},
          {id: 6, match_nr: 6, round: 2, stage: 'MAIN', time: '2026-10-31 10:00:00'},
          {id: 5, match_nr: 5, round: 1, stage: 'RR', time: null},
          {id: 4, match_nr: 4, round: 1, stage: 'RR', time: '2026-10-30 07:00:00', schedule_hidden: true},
          {id: 8, match_nr: 8, round: 2, stage: 'PLATE', time: '2026-10-31 10:00:00'},
        ].map(row => ({home: 'Synthetic player A', away: 'Synthetic player B', ...row}));
        window.RR_STANDINGS = {};
        window.CTPlayerRatings = {marker: () => '<span style="display:inline-block;white-space:nowrap;font-size:10px">55.9 | MEDIUM</span>'};
      });
      await page.addScriptTag({path: path.join(root, 'public/assets/js/roundrobin-public.js')});
      await page.waitForSelector('.rr-group-section', {state: 'attached'});
      assert.equal(await page.locator('#oop-tab').getAttribute('aria-selected'), 'true');
      assert.equal(await page.locator('#oop-pane').isVisible(), true);
      assert.deepEqual(await page.locator('#rr-order-table tbody td[data-label="Match"]').allTextContents(), ['1', '3', '2', '6', '8', '7', '4', '5']);
      await page.locator('#matrix-tab').click();
      await page.waitForTimeout(200);
      assert.equal(await page.locator('#matrix-pane').isVisible(), true);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      assert.equal(await page.locator('#rr-matrix-wrapper').evaluate(node => node.scrollWidth <= node.clientWidth), true);
      assert.doesNotMatch(await page.locator('#rr-matrix-wrapper').innerText(), /SECRET HIDDEN VENUE/);
      if (width >= 768) {
        for (const node of await page.locator('.rr-public-match-venue, .rr-matrix-table th').all()) {
          assert.equal(await node.evaluate(el => el.scrollWidth <= el.clientWidth + 1), true);
        }
        assert.equal(await page.locator('.rr-public-match-time').first().isVisible(), true);
      } else {
        assert.equal(await page.locator('.rr-matrix-table-shell').isVisible(), false);
        assert.equal(await page.locator('.rr-mobile-match').first().isVisible(), true);
      }
      await page.screenshot({path: path.join(root, `storage/framework/testing/public-roundrobin-${width}.png`), fullPage: true});
      assert.deepEqual(errors, []);
      await page.close();
    }
    // Existing explicit draw links continue to open the matrix instead of the default schedule.
    const source = read('public/assets/js/roundrobin-public.js');
    const startup = source.slice(source.lastIndexOf('  $(document).ready(function () {'), source.lastIndexOf('})(jQuery'));
    const vm = require('node:vm');
    for (const [hash, expected] of [['', 'oop-tab'], ['#schedule', 'oop-tab'], ['#matrix', 'matrix-tab'], ['#draw', 'matrix-tab'], ['#standings', 'standings-tab'], ['#playoffs', 'main-bracket-tab']]) {
      let clicked;
      vm.runInNewContext(startup, {$: () => ({ready(fn) {fn();}}), document: {getElementById: id => ({click() {clicked = id;}})}, window: {location: {hash}}});
      assert.equal(clicked, expected);
    }
  } finally { await browser.close(); }
});
