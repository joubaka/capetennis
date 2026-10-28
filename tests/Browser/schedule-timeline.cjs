// Real grid renderer and Bootstrap modal, using fictional data and no network requests.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('timeline editor fits phone, tablet and desktop and preserves bookings when changing targets', async () => {
  const browser = await chromium.launch({headless:true, channel:'msedge'});
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.abort());
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const source = read('resources/views/backend/schedule/event-venue-schedule.blade.php');
    const renderer = source.slice(source.indexOf('  const timelineWindow ='), source.indexOf('  function venueChangeWarnings'));
    const css = read('public/assets/vendor/css/core.css') + read('public/assets/vendor/css/theme-default.css') + source.match(/<style>([\s\S]*?)<\/style>/)[1];
    for (const width of [320,390,430,768,1280]) {
      await page.setViewportSize({width,height:900});
      await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><main class="schedule-workspace"><div id="venue-slot-grids"></div></main>${read('resources/views/backend/schedule/partials/timeline-editor.blade.php')}`);
      await page.addScriptTag({path:require.resolve('bootstrap/dist/js/bootstrap.bundle.min.js')});
      await page.addScriptTag({path:path.join(root,'public/js/schedule-timeline.js')});
      await page.evaluate(renderer => {
        window.timelineState = CapeScheduleTimeline.createState();
        window.asDate = value => new Date(String(value).replace(' ', 'T'));
        window.dateKey = value => asDate(value).getTime();
        window.escapeHtml = value => String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');
        window.assignmentTime = date => date.toLocaleString('sv-SE');
        window.formatSlotTime = date => date.toLocaleString('sv-SE');
        window.matchEnd = row => asDate(row.ends_at);
        window.matchLabel = () => 'Match 1';
        window.venueActions = window.ageGroupScheduleSummary = () => '';
        window.unapplyUrl = null;
        window.rows = [{scheduled_at:'2026-10-30 09:00:00',ends_at:'2026-10-30 10:00:00',court:'1',draw_name:'Test draw',fixture_id:1,round:1,fixed:true,editable:false}];
        window.venueRows = () => rows;
        window.result = {input:{start:'2026-10-30 08:00:00',end:'2026-10-30 12:00:00',duration:60}};
        window.venue = {id:1,name:'Fictional test courts',court_labels:['1','2'],courts:2};
        window.grid = new Function(renderer+';return slotGrid;')();
        window.redraw = () => document.getElementById('venue-slot-grids').innerHTML = grid(result,venue);
        redraw();
        CapeScheduleTimeline.mount({document,bootstrap,state:timelineState,context:() => ({name:venue.name,start:result.input.start,end:result.input.end}),onChange:redraw});
      }, renderer);
      const row = time => page.locator(`[data-edit-timeline][data-time="2026-10-30 ${time}:00"]`);
      await row('09:00').click();
      const modal = page.locator('#editTimelineModal');
      await modal.waitFor({state:'visible'});
      await page.locator('#edit-timeline-time').fill('2026-10-30T09:15');
      await page.locator('#edit-timeline-form button[type="submit"]').click();
      await modal.waitFor({state:'hidden'});
      assert.equal(await row('09:00').count(),1);
      assert.equal(await row('09:15').count(),1);
      assert.equal(await page.getByText('Test draw',{exact:true}).count(),1);
      assert.equal(await page.locator('[data-manual-slot][data-time="2026-10-30 09:15:00"][data-court="2"]').count(),1);
      await page.waitForFunction(() => document.activeElement?.dataset.time === '2026-10-30 09:15:00', null, {timeout:2000});
      assert.equal(await page.evaluate(() => document.activeElement?.dataset.time),'2026-10-30 09:15:00');
      await row('09:15').click();
      await modal.waitFor({state:'visible'});
      await page.locator('#edit-timeline-time').fill('2026-10-30T10:15');
      await page.locator('#edit-timeline-form').getByText('Cancel',{exact:true}).click();
      await modal.waitFor({state:'hidden'});
      assert.equal(await row('09:15').count(),1);
      assert.equal(await row('10:15').count(),0);
      await page.waitForFunction(() => document.activeElement?.dataset.time === '2026-10-30 09:15:00', null, {timeout:2000});
      assert.equal(await page.evaluate(() => document.activeElement?.dataset.time),'2026-10-30 09:15:00');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    assert.deepEqual(errors,[]);
  } finally { await browser.close(); }
});
