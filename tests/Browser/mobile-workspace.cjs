/* Presentation-only regression fixtures use real view fragments and local CSS.
   No application requests, database operations, or external requests are made. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const core = read('public/assets/vendor/css/core.css') + read('public/assets/vendor/css/theme-default.css');
const mobile = read('public/css/mobile-workspace.css');
const view = read('resources/views/backend/draw/manage-players.blade.php');
const toolbar = view.match(/<div class="draw-player-toolbar[\s\S]*?<table/)[0].replace(/<table$/, '')
  .replace(/@foreach[\s\S]*?@endforeach/, '<option>Very long player name for a narrow mobile display</option>');
const playerTable = '<table class="table"><tr><th>Name</th><th>Team</th><th>Category</th><th>Action</th></tr><tr><td>Alexandra Example</td><td>Western Cape Junior Tennis</td><td>Under 18</td><td><button class="btn btn-danger">Remove player</button></td></tr></table></div>';
const photo = read('resources/views/backend/photo/upload.blade.php').match(/<form[\s\S]*?<\/form>/)[0]
  .replace(/\{\{[\s\S]*?\}\}/g, '').replace(/@error\([\s\S]*?@enderror/g, '')
  .replace(/@csrf/g, '').replace(/@error\([^)]*\)/g, '').replace(/@enderror/g, '');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.abort());
    for (const shell of ['frontend', 'backend']) {
      const css = core + read('public/assets/vendor/css/pages/page-auth.css') + read(`public/css/${shell}-workspace.css`) + mobile;
      for (const width of [320, 375, 430, 768, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><body class="ct-${shell}"><main class="container-xxl"><div class="card"><div class="card-header d-flex justify-content-between"><h3>Long tournament title for a mobile screen</h3><button class="btn btn-primary">Open tournament workspace</button></div><div class="card-body">${toolbar}${playerTable}</div></div><div class="authentication-wrapper"><div class="w-px-400 mx-auto"><label for="email">Email</label><input id="email" class="form-control" value="player@example.test"><button class="btn btn-primary">Sign in</button></div></div><div class="card"><div class="card-body">${photo}</div></div><div class="modal-footer"><button class="btn btn-primary">Confirm the selected registration details and continue</button></div></main></body>`);
        const errors = await page.evaluate(() => {
          const errors = [];
          const check = (pass, message) => { if (!pass) errors.push(message); };
          check(document.documentElement.scrollWidth <= innerWidth, 'page overflow');
          for (const element of document.querySelectorAll('button, #player-select, #email')) {
            const rect = element.getBoundingClientRect();
            // Wide table cells must remain reachable inside the scroll region.
            if (element.closest('.table-responsive')) continue;
            check(rect.left >= -1 && rect.right <= innerWidth + 1, `clipped ${element.textContent || element.id}`);
          }
          const region = document.querySelector('.table-responsive');
          check(region.clientWidth <= innerWidth, 'table region exceeds viewport');
          if (innerWidth < 768) {
            check(getComputedStyle(document.querySelector('#email')).fontSize === '16px', 'auth input zoom threshold');
            check(document.querySelector('#add-player').getBoundingClientRect().height >= 44, 'add target too small');
            region.scrollLeft = region.scrollWidth;
            check(region.scrollWidth <= region.clientWidth || region.scrollLeft > 0, 'table actions cannot be scrolled into view');
          }
          return errors;
        });
        assert.deepEqual(errors, [], `${shell} ${width}px`);
        await page.locator('#player-select').selectOption({ index: 1 });
        await page.locator('#add-player').click();
        await page.locator('.table-responsive button').click();
        await page.locator('#email').fill('mobile@example.test');
        assert.equal(await page.locator('#email').inputValue(), 'mobile@example.test');
        if (shell === 'backend' && width === 320) {
          await page.screenshot({ path: path.join(root, 'storage/app/testing/mobile-workspace-320.png'), fullPage: true });
        }
        console.log(`PASS ${shell} ${width}px: controls visible, table contained and reachable`);
      }
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
