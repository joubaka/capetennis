'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');
const patch = require('../../tools/webpack/shepherd-safe-merge-loader.cjs');
const { deepmerge } = require('deepmerge-ts');
const packageRoot = path.dirname(require.resolve('shepherd.js'));
const source = fs.readFileSync(path.resolve(packageRoot, '../esm/shepherd.mjs'), 'utf8');

test('pinned Shepherd distribution delegates its sole merge entrypoint to the patched helper', () => {
  const output = patch(source);
  assert.match(output, /import \{ deepmerge as patchedDeepmerge \} from 'deepmerge-ts'/);
  assert.equal(output.includes('return deepmergeCustom({})(...objects);'), false);
  const entry = output.match(/function deepmerge\([\s\S]*?\n}\nfunction deepmergeCustom/)[0]
    .replace(/\nfunction deepmergeCustom$/, '');
  const merge = new Function('patchedDeepmerge', `${entry}; return deepmerge;`)(deepmerge);
  const callback = () => 'next';
  assert.deepEqual(merge({ buttons: [1], nested: { a: true } }, { buttons: [2], nested: { callback } }),
    { buttons: [1, 2], nested: { a: true, callback } });
  const unsafe = JSON.parse('{"__proto__":{"polluted":true},"nested":{"__proto__":{"polluted":true}}}');
  const result = merge({}, unsafe);
  assert.equal(result.polluted, undefined);
  assert.equal(result.nested.polluted, undefined);
  assert.equal({}.polluted, undefined);
  const recursive = {}; recursive.self = recursive;
  const otherRecursive = {}; otherRecursive.self = otherRecursive;
  const legacyMerge = new Function(source.replace('export { Shepherd as default };', 'return deepmerge;'))();
  assert.throws(() => legacyMerge(recursive, otherRecursive), RangeError,
    'The bundled legacy helper must reproduce the advisory; an npm override alone is insufficient');
  assert.doesNotThrow(() => merge(recursive, otherRecursive));
});

test('unknown or multiply matched Shepherd distributions stop the build', () => {
  assert.throws(() => patch('changed distribution'), /Review Shepherd merge remediation/);
  assert.throws(() => patch(source + source), /Review Shepherd merge remediation/);
});
