const { test } = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

test('Select2 recipients keep numeric IDs, escape labels and invalidate reviewed recipients', async () => {
 const browser = await chromium.launch({headless:true, executablePath: process.env.CT_BROWSER_EXECUTABLE});
 try {
  const page = await browser.newPage({viewport:{width:390,height:844}});
  let queued = 1;
  let failSend = false;
  await page.route('https://report.test/**', async route => {
   const req=route.request();
   if(req.url().endsWith('/send')) return route.fulfill(failSend
    ? {status:503,json:{message:'Please try again.'}}
    : {json:{queued}});
   if(req.url().includes('/preview')) {
    const payload=req.postDataJSON();
    assert.deepEqual(payload.recipient_ids,[7]);
    return route.fulfill({json:{...payload,recipients:[{id:7,name:'Reviewer',email:'review@example.test'}],pdf_url:'about:blank',send_url:'/send'}});
   }
   if(req.url().includes('?')) return route.fulfill({json:{users:[{id:7,name:'<img src=x onerror=alert(1)> Reviewer',email:'review@example.test'},{id:8,name:'Second Reviewer',email:'second@example.test'}]}});
   return route.fulfill({contentType:'text/html',body:'<meta name="csrf-token" content="fixture">'});
  });
  await page.goto('https://report.test/');
  await page.setContent(`<meta name="csrf-token" content="fixture"><div data-roster-report data-url="https://report.test/users" data-preview-url="https://report.test/preview"><label for="roster-recipients">Recipients</label><select id="roster-recipients" data-recipients multiple></select><p data-recipient-status></p><input data-subject value="Review"><textarea data-message>Review report</textarea><input data-from-name value="Cape Tennis"><select data-from-address><option>sender@example.test</option></select><input data-reply-to value="reply@example.test"><button data-preview>Preview</button><p data-status></p><section data-review hidden><p data-reviewed-recipients></p><p data-reviewed-subject></p><p data-reviewed-sender></p><div data-email></div><iframe data-pdf></iframe><a data-pdf-link></a><button data-send>Send</button></section></div>`);
  await page.addStyleTag({path:'public/assets/vendor/libs/select2/select2.css'});
  await page.addScriptTag({path:'public/assets/vendor/libs/jquery/jquery.js'});
  await page.addScriptTag({path:'public/assets/vendor/libs/select2/select2.js'});
  await page.addStyleTag({path:'public/assets/vendor/libs/toastr/toastr.css'});
  await page.addScriptTag({path:'public/assets/vendor/libs/toastr/toastr.js'});
  await page.addScriptTag({path:'public/assets/js/cavaliers-report-share.js'});
  await page.locator('.select2-selection').click();
  await page.locator('.select2-search__field').fill('review');
  const result=page.locator('.select2-results__option').filter({hasText:'<img src=x onerror=alert(1)> Reviewer'});
  await result.waitFor();
  assert.equal(await result.locator('img').count(),0);
  await result.click();
  await page.keyboard.press('Escape');
  await page.locator('[data-preview]').click();
  await page.locator('[data-review]:not([hidden])').waitFor();
  await page.locator('.select2-selection').click();
  await page.locator('.select2-search__field').fill('second');
  await page.locator('.select2-results__option').filter({hasText:'Second Reviewer'}).click();
  assert.equal(await page.locator('[data-review]').isVisible(),false);
  assert.deepEqual(await page.locator('[data-recipients]').evaluate(el=>Array.from(el.selectedOptions).map(o=>o.value)),['7','8']);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  await page.evaluate(()=>window.jQuery('[data-recipients]').val(['7']).trigger('change'));
  for (const count of [1,0]) {
   queued=count;
   await page.locator('[data-preview]').click();
   await page.locator('[data-review]:not([hidden])').waitFor();
   await page.locator('[data-send]').click();
   await page.waitForFunction(()=>document.querySelector('[data-status]').className.includes('alert-'));
   assert.equal(await page.locator('[data-review]').isVisible(),false);
   const text=await page.locator('[data-status]').innerText();
   assert.match(text,count ? /1 recipient/ : /No new emails queued/i);
   assert.doesNotMatch(text,/delivered|successfully sent/i);
   await page.locator('#toast-container .toast').last().waitFor();
  }
  failSend=true;
  await page.locator('[data-preview]').click();
  await page.locator('[data-review]:not([hidden])').waitFor();
  await page.locator('[data-send]').click();
  await page.waitForFunction(()=>document.querySelector('[data-status]').className.includes('alert-danger'));
  assert.equal(await page.locator('[data-review]').isVisible(),true);
  assert.equal(await page.locator('[data-send]').isEnabled(),true);
  assert.match(await page.locator('#toast-container').innerText(),/Could not queue report/);
 } finally {await browser.close();}
});

