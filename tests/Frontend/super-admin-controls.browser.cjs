const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const os = require('node:os');
const {execFileSync, execFile} = require('node:child_process');
const {promisify} = require('node:util');

test('Super Admin cue follows real route methods, explicit scopes and dynamic controls', async () => {
  const browser = process.env.CT_BROWSER_EXECUTABLE || path.join(process.env.LOCALAPPDATA || '', 'ms-playwright/chromium_headless_shell-1228/chrome-headless-shell-win64/chrome-headless-shell.exe');
  assert.ok(fs.existsSync(browser), 'Set CT_BROWSER_EXECUTABLE to a local Chromium headless browser.');
  const manifest = JSON.parse(execFileSync('php', ['-d', 'xdebug.mode=off', '-r',
    "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode((new App\\Support\\SuperAdminControls)->manifest());"], {encoding: 'utf8', windowsHide: true}));
  for (const [pattern] of manifest) assert.doesNotThrow(() => new RegExp(pattern), pattern);
  const html = `<!doctype html><meta charset="utf-8"><base href="/ct/"><link rel="stylesheet" href="/cue.css">
    <a id="exclusive" href="/ct/backend/player-performance">Private ratings</a>
    <a id="shared" href="/ct/backend/email/issues">Shared email issues</a>
    <a id="external" href="https://example.invalid/backend/player-performance">External</a>
    <a id="outside" href="/backend/player-performance">Outside subdirectory</a>
    <form action="/ct/backend/admin/engine/draw/1" method="post"><input type="hidden" name="_method" value="PATCH"><input id="field"><button id="save">Save</button><a id="cancel" href="/ct/login">Cancel</a></form>
    <div data-super-admin-page><button id="page-filter">Filter private page</button><a id="shared-nav" href="/ct/login">Login</a><div data-super-admin-shared><button id="more">More</button><button id="assistant" data-super-admin-only>Assistant</button></div></div>
    <div data-super-admin-only><summary id="summary">Private tools</summary></div>
    <script type="application/json" id="ct-super-admin-routes" data-base-path="/ct">${JSON.stringify(manifest).replace(/</g, '\\u003c')}</script>
    <script src="/cue.js"></script><pre id="result"></pre><script>
      addEventListener('load', () => {
        const link = document.createElement('a'); link.id = 'dynamic'; link.href = '/ct/backend/player-performance'; document.body.append(link);
        document.getElementById('external').href = '/ct/backend/email/issues';
        setTimeout(() => {
          const marked = id => document.getElementById(id).classList.contains('ct-super-admin-control');
          document.getElementById('result').textContent = JSON.stringify({
            marks: Object.fromEntries(['exclusive','shared','external','outside','field','save','cancel','page-filter','shared-nav','more','assistant','summary','dynamic'].map(id => [id, marked(id)])),
            outline: getComputedStyle(document.getElementById('exclusive')).outlineColor
          });
        }, 500);
      });
    </script>`;
  const server = http.createServer((req, res) => {
    if (req.url === '/cue.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(fs.readFileSync('public/js/super-admin-controls.js')); }
    else if (req.url === '/cue.css') { res.setHeader('Content-Type', 'text/css'); res.end(fs.readFileSync('public/css/super-admin-controls.css')); }
    else { res.setHeader('Content-Type', 'text/html'); res.end(html); }
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'ct-admin-cue-'));
  try {
    const {stdout, stderr} = await promisify(execFile)(browser, ['--headless', '--disable-gpu', '--no-sandbox', '--no-proxy-server', '--dump-dom', '--virtual-time-budget=3000', `--log-file=${path.join(profile, 'browser.log')}`, `--user-data-dir=${profile}`, `http://127.0.0.1:${server.address().port}/ct/fixture`], {encoding: 'utf8', windowsHide: true, timeout: 30000, maxBuffer: 1024 * 1024});
    const result = JSON.parse(stdout.match(/<pre id="result">(.*?)<\/pre>/s)?.[1] || '{}');
    assert.ok(result.marks, `Browser did not finish the local fixture: ${stdout.slice(-2000)}\n${stderr}`);
    assert.deepEqual(result.marks, {exclusive: true, shared: false, external: false, outside: false, field: true, save: true, cancel: false, 'page-filter': true, 'shared-nav': false, more: false, assistant: true, summary: true, dynamic: true});
    assert.equal(result.outline, 'rgb(242, 140, 24)');
  } finally {
    await new Promise(resolve => server.close(resolve));
    fs.rmSync(profile, {recursive: true, force: true});
  }
});
