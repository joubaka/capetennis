// Local DOM fixture only: every HTTP request is intercepted, with no app/database/mail access.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const {chromium} = require('playwright');

for (const asset of ['public/assets/js/wizard-ex-checkout.js', 'resources/assets/js/wizard-ex-checkout.js']) {
  for (const width of [390, 1440]) {
    test(`${asset}: generated Save & Confirm terminal states at ${width}px`, async () => {
      const browser = await chromium.launch({headless: true, executablePath: process.env.CT_BROWSER_EXECUTABLE});
      try {
        for (const mode of ['success', 'validation', 'session', 'mismatch', 'timeout']) {
          const page = await browser.newPage({viewport: {width, height: 900}});
          const errors = [];
          page.on('pageerror', error => errors.push(error.message));
          const player = {id: 7, name: 'Fictional', surname: 'Player', dateOfBirth: '2010-01-01',
            gender: 'Female', cellNr: '0000000000', email: 'test@example.test'};
          let saves = 0;
          await page.route('**/*', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({contentType: 'text/html', body: `
              <meta charset="utf-8"><meta name="csrf-token" content="fixture"><meta name="viewport" content="width=device-width,initial-scale=1">
              <main id="wizard-checkout" class="container p-3" style="max-width:900px">
                <h1 class="h4">Fictional trials — confirm player details</h1>
                <div class="step active"></div><div class="playerRow">
                  <select class="select2player"><option value="7" selected>Fictional Player</option></select>
                </div><button class="btn-next btn btn-primary">Confirm player details</button>
                <div id="confirmPlayerCards" class="mt-3"></div>
                <button id="goToCart" class="btn btn-primary" disabled>Continue to Cart</button>
                <button id="createPlayerButton" hidden>Create player</button>
              </main>`});
            if (pathname === '/register/player-details') return route.fulfill({json: player});
            if (pathname === '/register/update-player-details') {
              saves++;
              if (mode === 'timeout') return; // jQuery's shortened fixture timeout aborts this intercepted request.
              if (mode === 'validation') return route.fulfill({status: 422, json: {errors: {cellNr: ['Invalid phone']}}});
              if (mode === 'session') return route.fulfill({status: 419, json: {message: 'Expired session'}});
              return route.fulfill({json: {success: true, player: {...player, id: mode === 'mismatch' ? 99 : 7}}});
            }
            return route.fulfill({status: 404, body: ''});
          });
          await page.goto('https://checkout.test/');
          await page.addStyleTag({path: 'node_modules/bootstrap/dist/css/bootstrap.min.css'});
          await page.addScriptTag({path: 'node_modules/jquery/dist/jquery.js'});
          await page.evaluate(mode => {
            window.APP_URL = location.origin;
            window.Stepper = class {next() {}};
            window.toasts = [];
            window.toastr = {success: text => window.toasts.push(text), error: text => window.toasts.push(text)};
            $.fn.select2 = $.fn.flatpickr = function () {return this;};
            $.ajaxPrefilter(options => {
              if (mode === 'timeout' && options.url.endsWith('/register/update-player-details')) options.timeout = 100;
            });
          }, mode);
          await page.addScriptTag({path: asset});
          await page.getByRole('button', {name: 'Confirm player details', exact: true}).click();
          const save = page.getByRole('button', {name: 'Save & Confirm', exact: true});
          await save.waitFor();
          await save.click();
          await page.waitForFunction(() => window.toasts.length > 0);
          const button = page.locator('.btn-save-player');
          assert.equal(await button.getAttribute('aria-busy'), null);
          assert.equal(await button.locator('.spinner-border').count(), 0);
          assert.equal(saves, 1);
          if (mode === 'success') {
            assert.equal(await button.innerText(), 'Saved & Confirmed');
            assert.equal(await button.isDisabled(), true);
            assert.equal(await button.isVisible(), true);
            assert.equal(await page.locator('#confirm-card-7').getAttribute('data-confirmed'), '1');
            assert.equal(await page.locator('#goToCart').isEnabled(), true);
            assert.equal(await page.locator('.pd-name').isDisabled(), true);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            const directory = 'storage/app/testing/checkout-save-qa'; fs.mkdirSync(directory, {recursive: true});
            await page.screenshot({path: `${directory}/${asset.startsWith('public') ? 'served' : 'source'}-${width}.png`});
          } else {
            assert.equal(await button.innerText(), 'Save & Confirm');
            assert.equal(await button.isEnabled(), true);
            assert.equal(await page.locator('#confirm-card-7').getAttribute('data-confirmed'), '0');
            assert.equal(await page.locator('#goToCart').isDisabled(), true);
            assert.equal(await page.locator('.pd-name').isEnabled(), true);
          }
          assert.deepEqual(errors, []);
          await page.close();
        }
      } finally {await browser.close();}
    });
  }
}
