/* Deterministic fictional workspaces: no live data, rating builders or POSTs. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const links = tab => `<nav id="my-tennis-tabs" aria-label="My Tennis views">${['profile','rating'].map(key => `<a data-my-tennis-link href="/my-tennis?player=7&tab=${key}" ${tab === key ? 'aria-current="page"' : ''}>${key}</a>`).join(' ')}</nav>`;
const workspace = (tab, pending = false) => `<div data-my-tennis-workspace>${links(tab)}<h1>${tab}</h1>${pending ? '<p data-profile-match-pending>Awaiting a saved update</p>' : `<p data-ready-${tab}>Saved ${tab} view</p>`}</div>`;
(async () => {
  const browser = await chromium.launch({channel:'msedge', headless:true});
  try {
    for (const width of [320,1440]) {
      const page = await browser.newPage({viewport:{width,height:900}});
      const requests = [], errors = [];
      let failProfile = true;
      page.on('pageerror', error => errors.push(error.message));
      await page.addInitScript(() => {
        window.navigationEvents = [];
        for (const name of ['my-tennis:navigating','my-tennis:navigation-stopped']) document.addEventListener(name, () => window.navigationEvents.push(name));
      });
      await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        assert.equal(request.method(),'GET','No graph materialization or other mutations');
        if (url.hostname !== 'localhost') return route.abort();
        if (request.isNavigationRequest()) return route.fulfill({contentType:'text/html',body:'<!doctype html><title>My Tennis QA</title>'+workspace('profile',true)+'<script defer src="/assets/js/my-tennis-navigation.js"></script>'});
        if (request.headers()['x-my-tennis-navigation'] === '1') {
          const tab = url.searchParams.get('tab'); requests.push(tab);
          if (tab === 'profile' && failProfile) {failProfile=false;return route.fulfill({status:503});}
          return route.fulfill({contentType:'text/html',body:'<!doctype html><title>My Tennis QA</title>'+workspace(tab)});
        }
        if (url.pathname === '/assets/js/my-tennis-navigation.js') return route.fulfill({path:path.join(root,'public/assets/js/my-tennis-navigation.js')});
        return route.abort();
      });
      await page.goto('http://localhost/my-tennis?player=7&tab=profile',{waitUntil:'networkidle'});
      assert.equal(await page.locator('[data-profile-match-pending]').count(),1);
      await page.getByRole('link',{name:'rating',exact:true}).click();
      await page.locator('[data-ready-rating]').waitFor();
      await page.getByRole('link',{name:'profile',exact:true}).click();
      await page.getByRole('button',{name:'Try again'}).waitFor();
      assert.equal(await page.locator('[data-ready-rating]').count(),1,'Failed selected tab retains old panel');
      assert.deepEqual(requests,['rating','profile'],'Pending Profile is fetched again instead of replayed from cache');
      assert.ok((await page.evaluate(() => window.navigationEvents)).includes('my-tennis:navigation-stopped'),'Failed navigation resumes background read lifecycle');
      await page.getByRole('button',{name:'Try again'}).click();
      await page.locator('[data-ready-profile]').waitFor();
      await page.getByRole('link',{name:'rating',exact:true}).click();
      await page.locator('[data-ready-rating]').waitFor();
      await page.getByRole('link',{name:'profile',exact:true}).click();
      await page.locator('[data-ready-profile]').waitFor();
      assert.deepEqual(requests,['rating','profile','profile'],'Successful visited tabs still reuse their ready cache');
      assert.equal(await page.locator('[data-profile-match-pending]').count(),0);
      assert.deepEqual(errors,[]);
      console.log(`PASS ${width}px: transient Profile cache bypass, ready-cache reuse, failure/retry read lifecycle, no POSTs`);
      await page.close();
    }
  } finally {await browser.close();}
})().catch(error => {console.error(error);process.exitCode=1;});
