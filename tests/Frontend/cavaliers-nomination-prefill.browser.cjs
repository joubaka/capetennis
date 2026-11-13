const {test} = require('node:test');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');

test('nominee checkout preselection initializes the exact category fee and renders labels as text', async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CT_BROWSER_EXECUTABLE});
  try {
    for (const script of ['public/assets/js/wizard-ex-checkout.js', 'resources/assets/js/wizard-ex-checkout.js']) {
      for (const [prefilled, categoryFee, expected] of [[true, '0', 'R0.00'], [true, '125.50', 'R125.50'], [false, '0', 'Unchanged']]) {
      const page = await browser.newPage();
      const errors = []; page.on('pageerror', error => errors.push(error.stack));
      await page.setContent(`<meta name="csrf-token" content="fixture"><div id="wizard-checkout" data-nomination-prefilled="${prefilled}"><div class="playerRow"><select class="select2player"><option value="0">Select</option><option value="11" selected>&lt;img src=x onerror=alert(1)&gt; Example</option></select><select class="select2category"><option value="7" data-name="U11 Girls" data-price="${categoryFee}" selected>U11 Girls</option></select></div><div class="playersCart"></div><ul id="myUl"></ul><span class="orderTotal">Unchanged</span><input id="eventPrice" value="900"><input id="amount"><input id="myevent" value="Fictional Trials"><input id="item_name"></div>`);
      await page.addScriptTag({path: 'public/assets/vendor/libs/jquery/jquery.js'});
      await page.addScriptTag({path: 'public/assets/vendor/libs/select2/select2.js'});
      // The maintained source wizard expects the normal add-player modal form.
      await page.evaluate(() => { const form = document.createElement('form'); form.className = 'formPlayer'; const button = document.createElement('button'); button.id = 'createPlayerButton'; form.append(button); document.body.append(form); });
      await page.addScriptTag({content: 'window.Stepper = class {next(){} previous(){}}; window.APP_URL="https://checkout.invalid";'});
      await page.addScriptTag({path: script});
      assert.equal(await page.locator('.select2player').inputValue(), '11');
      assert.equal(await page.locator('.select2category').inputValue(), '7');
      assert.deepEqual(errors, [], script);
      if (prefilled) await page.waitForFunction(expected => document.querySelector('.orderTotal').textContent === expected, expected);
      assert.equal(await page.locator('.orderTotal').innerText(), expected, script);
      if (prefilled) {
        assert.match(await page.locator('.playersCart').innerText(), /<img src=x onerror=alert\(1\)> Example/);
        assert.equal(await page.locator('#wizard-checkout img').count(), 0);
        assert.equal(await page.locator('#amount').inputValue(), categoryFee === '0' ? '0.00' : '125.50');
      }
      assert.deepEqual(errors, []);
      await page.close();
      }
    }
  } finally { await browser.close(); }
});
