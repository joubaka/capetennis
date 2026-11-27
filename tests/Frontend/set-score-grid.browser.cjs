// Isolated DOM fixture: no application database or network requests.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');

for (const width of [390, 1440]) {
  test(`set score completion and manual correction at ${width}px`, async () => {
    const browser = await chromium.launch({headless: true, executablePath: process.env.CT_BROWSER_EXECUTABLE});
    try {
      const page = await browser.newPage({viewport: {width, height: 900}});
      await page.setContent('<div id="grid"><strong data-score-home-label></strong><strong data-score-away-label></strong>'
        + Array.from({length: 5}, (_, i) => `<div data-score-row><input type="number" min="0" aria-label="Home ${i}"><span>Set ${i + 1}</span><input type="number" min="0" aria-label="Away ${i}"></div>`).join('') + '</div>');
      await page.addScriptTag({path: 'public/js/set-score-grid.js'});
      await page.addStyleTag({path: 'public/css/set-score-grid.css'});
      await page.evaluate(() => CTSetScoreGrid.populate(document.querySelector('#grid'), {names: ['A', 'B'], maxSets: 3, setTypes: ['full', 'full', 'match_tiebreak']}));
      const home = page.getByLabel('Home 0'), away = page.getByLabel('Away 0');
      await home.fill('2'); assert.equal(await away.inputValue(), '6');
      await home.fill('5'); assert.equal(await away.inputValue(), '7');
      await home.fill('6'); assert.equal(await away.inputValue(), '7');
      await away.fill('6'); await home.fill('3'); assert.equal(await away.inputValue(), '6');
      await page.getByLabel('Away 1').fill('5'); assert.equal(await page.getByLabel('Home 1').inputValue(), '7');
      await page.getByLabel('Away 1').fill('6'); assert.equal(await page.getByLabel('Home 1').inputValue(), '7');
      await page.getByLabel('Away 1').fill('5');
      await page.getByLabel('Home 2').pressSequentially('20');
      assert.equal(await page.getByLabel('Away 2').inputValue(), '');
      await page.getByLabel('Away 2').fill('18');
      assert.deepEqual(await page.evaluate(() => CTSetScoreGrid.read(document.querySelector('#grid'))), [[3, 6], [7, 5], [20, 18]]);
      await page.evaluate(() => CTSetScoreGrid.setCompletionEnabled(document.querySelector('#grid'), false));
      await away.fill(''); await home.fill('2'); assert.equal(await away.inputValue(), '');
      await page.evaluate(() => CTSetScoreGrid.populate(document.querySelector('#grid'), {names: ['C', 'D'], maxSets: 1, setTypes: ['target4'], scores: [[4, 2]]}));
      await away.fill('1'); assert.equal(await home.inputValue(), '4');
    } finally { await browser.close(); }
  });
}
