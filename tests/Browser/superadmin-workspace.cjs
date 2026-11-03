/* Current Laravel-rendered fixture, local assets, scripts stripped: layout QA only. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const outputRoot = path.join(root, 'storage/framework/testing');
const fixture = path.join(outputRoot, 'superadmin-workspace.html');

(async () => {
  assert.ok(fs.existsSync(fixture), 'Generate fixture with CT_SUPERADMIN_WORKSPACE_QA_HTML=1');
  assert.ok(fs.statSync(fixture).mtimeMs >= fs.statSync(path.join(root, 'resources/views/backend/superadmin/index.blade.php')).mtimeMs,
    'Fixture must be newer than the dashboard source');
  const html = fs.readFileSync(fixture, 'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
  assert.ok(html.includes('sa-plus-settings-heading') && html.includes('Switches save immediately.'), 'Current implementation markers');
  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  const styles = new Set();
  try {
    const page = await browser.newPage();
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      const target = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
      if (!['localhost', '127.0.0.1'].includes(url.hostname) || !target.startsWith(publicRoot + path.sep)
        || !fs.existsSync(target) || !fs.statSync(target).isFile()) return route.abort();
      if (target.endsWith('.css')) styles.add(target);
      return route.fulfill({ path: target });
    });
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({ width, height: 1100 });
      await page.setContent(html, { waitUntil: 'networkidle' });
      await page.evaluate(() => {
        document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active', 'show'));
        document.querySelectorAll('#sa-main-tabs .nav-link, #sa-settings-tabs .nav-link').forEach(el => el.classList.remove('active'));
        for (const id of ['sa-pane-settings', 'sa-spane-registration']) document.getElementById(id).classList.add('active', 'show');
        for (const id of ['sa-tab-settings', 'sa-stab-registration']) document.getElementById(id).classList.add('active');
      });
      assert.equal(await page.locator('#sa-main-tabs a.nav-link').count(), 10, `${width}px: ten server workspace destinations`);
      assert.equal(await page.locator('#sa-spane-registration').isVisible(), true);
      const measurements = await page.evaluate(() => {
        const shell = document.querySelector('.sa-shell');
        const discipline = document.getElementById('sa-disciplinary-system-enabled').parentElement.parentElement;
        const plus = document.getElementById('sa-plus-settings-heading').parentElement;
        const column = plus.parentElement;
        const row = column.parentElement;
        const kpis = [...document.querySelectorAll('.sa-kpi')].map(el => el.getBoundingClientRect());
        const visibleOverflow = [...shell.querySelectorAll('input:not([type=hidden]),section,.card,.sa-kpi')]
          .filter(el => {const r = el.getBoundingClientRect(); return r.width && r.height && (r.left < -1 || r.right > innerWidth + 1);})
          .map(el => el.id || el.className);
        return {
          shellOverflow: shell.scrollWidth - shell.clientWidth,
          disciplineHeight: discipline.getBoundingClientRect().height,
          plusWidth: plus.getBoundingClientRect().width,
          availableWidth: row.getBoundingClientRect().width - parseFloat(getComputedStyle(column).paddingLeft) - parseFloat(getComputedStyle(column).paddingRight),
          twoKpiColumns: Math.abs(kpis[0].top - kpis[1].top) < 1 && kpis[1].left > kpis[0].left && kpis[2].top > kpis[0].top,
          ancestors: (() => {const a = []; for (let el = discipline; el; el = el.parentElement) {const s = getComputedStyle(el); a.push({tag: el.tagName, class: el.className, width: el.getBoundingClientRect().width, padding: s.padding, margin: s.margin, zoom: s.zoom});} return a;})(),
          visibleOverflow
        };
      });
      assert.ok(measurements.shellOverflow <= 1, `${width}px: main content overflow ${JSON.stringify(measurements)}`);
      assert.deepEqual(measurements.visibleOverflow, [], `${width}px: visible controls/cards contained`);
      assert.ok(measurements.disciplineHeight < 180, `${width}px: disciplinary tile compact ${JSON.stringify(measurements)}`);
      assert.ok(Math.abs(measurements.plusWidth - measurements.availableWidth) < 2, `${width}px: Plus fills row content ${JSON.stringify(measurements)}`);
      if (width === 320) assert.equal(measurements.twoKpiColumns, true, '320px: two KPI columns');
      if (width === 390 || width === 1440) {
        await page.evaluate(() => scrollTo(0, 0));
        await page.screenshot({path: path.join(outputRoot, `superadmin-workspace-${width}.png`), fullPage: true});
      }
      console.log(`PASS ${width}px: Settings/Registration visible, ten tabs, contained layout, compact discipline, full-width Plus`);
    }
    assert.ok(styles.size > 0, 'Actual local CSS loaded');
  } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
