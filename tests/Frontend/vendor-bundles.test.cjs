'use strict';

// Exercise the emitted production UMD entry points, not raw dependency imports.
// Run after the build with CT_VENDOR_BUILD_ROOT pointing at its public directory.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.resolve(process.env.CT_VENDOR_BUILD_ROOT || path.join(root, 'storage/framework/testing/ci-frontend-build/public'));
const bundles = ['/assets/vendor/libs/shepherd/shepherd.js', '/assets/vendor/libs/quill/katex.js', '/assets/vendor/libs/i18n/i18n.js'];
for (const bundle of bundles) assert.ok(fs.statSync(path.join(publicRoot, bundle)).isFile(), `Missing emitted bundle: ${bundle}`);

(async () => {
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    const external = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.origin !== 'http://vendor-bundles.local') {
        external.push(url.origin);
        return route.abort();
      }
      if (url.pathname === '/') {
        return route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head><meta charset="utf-8"></head><body><button id="target">Tour target</button><div id="math"></div>' + bundles.map(src => `<script src="${src}"></script>`).join('') + '</body></html>' });
      }
      const asset = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!asset.startsWith(publicRoot + path.sep) || !fs.existsSync(asset) || !fs.statSync(asset).isFile()) return route.abort();
      return route.fulfill({ path: asset });
    });
    await page.goto('http://vendor-bundles.local/', { waitUntil: 'networkidle' });

    const merge = await page.evaluate(() => {
      if (!window.Shepherd?.Tour) throw new Error('Emitted Shepherd UMD export is unavailable');
      const recursive = { marker: 'recursive' }; recursive.self = recursive;
      const recursiveDefault = { base: true }; recursiveDefault.self = recursiveDefault;
      const recursiveStep = { step: true }; recursiveStep.self = recursiveStep;
      const callback = () => 'preserved';
      // Shepherd shallowly replaces general step fields, but explicitly deep-merges
      // floatingUIOptions and deep-clones defaultStepOptions. Exercise those paths.
      const tour = new Shepherd.Tour({ defaultStepOptions: { qaMeta: { callback, recursive }, floatingUIOptions: { qaNested: { base: true, callback }, qaRecursive: recursiveDefault } } });
      const step = tour.addStep({ id: 'merge', text: 'Merge check', floatingUIOptions: { qaNested: { step: true }, qaRecursive: recursiveStep } });
      const result = { base: step.options.floatingUIOptions.qaNested.base, step: step.options.floatingUIOptions.qaNested.step,
        callback: step.options.qaMeta.callback(), nestedCallback: step.options.floatingUIOptions.qaNested.callback(), recursive: step.options.qaMeta.recursive.self.marker,
        recursiveDefault: step.options.floatingUIOptions.qaRecursive.self.base, recursiveStep: step.options.floatingUIOptions.qaRecursive.self.step };
      const unsafe = JSON.parse('{"__proto__":{"vendorBundlePolluted":true},"nested":{"__proto__":{"vendorBundlePolluted":true}}}');
      const pollutionTour = new Shepherd.Tour({ defaultStepOptions: { qaMeta: unsafe } });
      const pollutionStep = pollutionTour.addStep({ id: 'pollution', text: 'Pollution check' });
      result.polluted = pollutionStep.options.qaMeta.vendorBundlePolluted === true
        || pollutionStep.options.qaMeta.nested.vendorBundlePolluted === true || ({}).vendorBundlePolluted === true;
      return result;
    });
    assert.deepEqual(merge, { base: true, step: true, callback: 'preserved', nestedCallback: 'preserved', recursive: 'recursive', recursiveDefault: true, recursiveStep: true, polluted: false });

    await page.evaluate(async () => {
      window.vendorTourEvents = { show: 0, next: 0, cancel: 0 };
      const tour = new Shepherd.Tour({ confirmCancel: false, defaultStepOptions: { cancelIcon: { enabled: true }, scrollTo: false } });
      tour.on('cancel', () => window.vendorTourEvents.cancel++);
      tour.addStep({ id: 'first', text: 'First emitted-bundle step', attachTo: { element: '#target', on: 'bottom' },
        when: { show: () => window.vendorTourEvents.show++ }, buttons: [{ text: 'Next test step', action() { window.vendorTourEvents.next++; return tour.next(); } }] });
      tour.addStep({ id: 'second', text: 'Second emitted-bundle step', buttons: [{ text: 'Cancel test tour', action: tour.cancel }] });
      window.vendorTour = tour;
      await tour.start();
    });
    assert.ok(await page.getByText('First emitted-bundle step', { exact: true }).isVisible());
    await page.getByRole('button', { name: 'Next test step', exact: true }).click();
    assert.ok(await page.getByText('Second emitted-bundle step', { exact: true }).isVisible());
    await page.getByRole('button', { name: 'Cancel test tour', exact: true }).click();
    assert.deepEqual(await page.evaluate(() => window.vendorTourEvents), { show: 1, next: 1, cancel: 1 });
    assert.equal(await page.locator('.shepherd-element').count(), 0);

    const math = await page.evaluate(() => {
      if (typeof window.katex?.render !== 'function') throw new Error('Emitted KaTeX UMD export is unavailable');
      const target = document.querySelector('#math');
      katex.render('c^2 = a^2 + b^2', target, { throwOnError: true });
      const basic = target.querySelectorAll('.katex').length;
      const inherited = Object.create({ trust: true }); inherited.strict = 'ignore';
      target.innerHTML = katex.renderToString('\\htmlClass{vendor-trusted}{x}', inherited);
      const inheritedTrust = target.querySelectorAll('.vendor-trusted').length;
      let pollutedTrust;
      try {
        Object.prototype.trust = true;
        target.innerHTML = katex.renderToString('\\htmlClass{vendor-trusted}{x}', { strict: 'ignore' });
        pollutedTrust = target.querySelectorAll('.vendor-trusted').length;
      } finally { delete Object.prototype.trust; }
      target.innerHTML = katex.renderToString('\\htmlClass{vendor-trusted}{x}', { trust: true, strict: 'ignore' });
      return { basic, inheritedTrust, pollutedTrust, explicitTrust: target.querySelectorAll('.vendor-trusted').length };
    });
    assert.deepEqual(math, { basic: 1, inheritedTrust: 0, pollutedTrust: 0, explicitTrust: 1 });

    const translations = await page.evaluate(async () => {
      if (typeof window.i18next?.init !== 'function' || !window.i18NextHttpBackend || !window.languageDetector) throw new Error('Emitted i18n UMD exports are unavailable');
      await i18next.use(languageDetector).use(i18NextHttpBackend).init({ lng: 'en', fallbackLng: false, resources: { en: { translation: { greeting: 'Hello {{name}}' } } } });
      const builtIns = [Object.prototype.hasOwnProperty, Object.prototype.toString, Object.prototype.valueOf];
      const originalCalls = builtIns.map(fn => fn.call);
      const originalDescriptors = builtIns.map(fn => Object.getOwnPropertyDescriptor(fn, 'call'));
      try {
        // GHSA-6jcc-5g8w-32mx: deep resource merges must not recurse into inherited built-ins.
        i18next.addResourceBundle('en', 'translation', {
          hasOwnProperty: { call: 'corrupted' }, toString: { call: 'corrupted' }, valueOf: { call: 'corrupted' }
        }, true, true);
        return {
          translation: i18next.t('greeting', { name: 'Cape Tennis' }), language: i18next.language,
          sharedBuiltInsIntact: builtIns.every((fn, index) => fn.call === originalCalls[index])
        };
      } finally {
        builtIns.forEach((fn, index) => {
          if (originalDescriptors[index]) Object.defineProperty(fn, 'call', originalDescriptors[index]);
          else delete fn.call;
        });
      }
    });
    assert.deepEqual(translations, { translation: 'Hello Cape Tennis', language: 'en', sharedBuiltInsIntact: true });
    assert.deepEqual(errors, []);
    assert.deepEqual(external, [], 'Bundled vendor smoke test must not contact external services');
    console.log('PASS: emitted Shepherd tour callbacks, nested/recursive and pollution-safe merge; KaTeX render/inherited-trust protection; local-resource i18n plugins, interpolation and inherited-built-in merge protection.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
