// Offline modal fixture: shipped markup and handlers, mocked save responses.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('walkover result selector works on phone and desktop without submitting scores', async () => {
  const browser = await chromium.launch({headless: true, channel: 'msedge'});
  try {
    const source = read('resources/views/frontend/scoring/workspace.blade.php');
    let grid = read('resources/views/shared/scoring/set-grid.blade.php');
    const row = grid.match(/  @for[^\n]*\n([\s\S]*?)  @endfor/)[1];
    grid = grid.replace(/  @for[^\n]*\n[\s\S]*?  @endfor/, Array.from({length: 5}, (_, i) => row.replaceAll('{{ $setNumber }}', String(i + 1))).join(''))
      .replace(/@php[^\n]*\n/, '').replace(/<link[^>]*>/, '').replace(/<script[\s\S]*?<\/script>/, '').replaceAll('{{ $scoreGridPrefix }}', 'score');
    const markup = source.slice(source.indexOf('<div class="modal fade" id="score-entry-modal"'), source.lastIndexOf('<script>'))
      .replace(/@include\('shared.scoring.set-grid'[^\n]*/, grid);
    const script = source.slice(source.lastIndexOf('<script>') + 8, source.lastIndexOf('</script>'))
      .replace('  syncRememberedVenue(true);', '').replace('  applyScoreFilter(currentFilter(), false);', '');
    const css = ['public/assets/vendor/css/core.css', 'public/assets/vendor/css/theme-default.css', 'public/css/set-score-grid.css'].map(read).join('\n');
    for (const width of [320, 1280]) {
      const page = await browser.newPage({viewport: {width, height: 900}});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/*', route => route.abort());
      await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fictional"><style>${css}</style>
        <button class="js-open-score" data-engine="standard" data-home="Alex Example" data-away="Sam Example" data-home-registration="11" data-away-registration="22" data-num-sets="3" data-store="/fixture" data-scores="[]">Enter score</button>${markup}`);
      await page.addScriptTag({path: path.join(root, 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js')});
      await page.addScriptTag({path: path.join(root, 'public/js/set-score-grid.js')});
      await page.evaluate(() => {
        window.savedBodies = [];
        window.fetch = async (url, options) => {
          if (options.method === 'POST') window.savedBodies.push(JSON.parse(options.body));
          return {ok: false, status: 422, json: async () => ({message: 'Fictional save captured'})};
        };
      });
      await page.addScriptTag({content: script});
      await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
      await page.locator('.js-open-score').click();
      await page.locator('#score-result-type').selectOption('walkover');
      assert.equal(await page.locator('[data-set-score-grid]').isVisible(), false);
      assert.equal(await page.locator('#score-walkover-winner').isVisible(), true);
      await page.locator('#score-save').click();
      assert.equal(await page.evaluate(() => savedBodies.length), 0);
      await page.locator('#score-walkover-winner').selectOption('22');
      await page.screenshot({path: path.join(root, `storage/framework/testing/walkover-selection-${width}.png`)});
      await page.locator('#score-save').click();
      await page.waitForFunction(() => savedBodies.length === 1);
      assert.deepEqual(await page.evaluate(() => savedBodies[0]), {walkover_winner_id: 22});
      await page.locator('#score-result-type').selectOption('played');
      assert.equal(await page.locator('[data-set-score-grid]').isVisible(), true);
      assert.equal(await page.locator('#score-walkover-winner').isVisible(), false);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      assert.deepEqual(errors, []);
      await page.screenshot({path: path.join(root, `storage/framework/testing/walkover-result-${width}.png`)});
      await page.close();
    }
  } finally { await browser.close(); }
});
