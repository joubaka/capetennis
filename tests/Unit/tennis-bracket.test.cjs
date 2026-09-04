const { test } = require('node:test');
const assert = require('node:assert/strict');
const { pathSegments, mergeSegments, screenSegments, transform } = require('../../public/js/tennis-bracket.js');
const { linePath } = require('../../public/js/tennis-bracket-layout.js');

test('Custom Monrad exports unsnapped vectors for the shared adapter and print', () => {
  const path = linePath([{ x: 0, top: 64, bottom: 128 }], [[220, 96, 260, 96]], { raw: true });
  assert.equal(path, 'M 0 64 H 220 V 128 H 0 M 220 96 H 260');
  assert.deepEqual(pathSegments(path), [[0, 64, 220, 64], [220, 64, 220, 128], [220, 128, 0, 128], [220, 96, 260, 96]]);
});

test('legacy relative paths and repeated coordinates are preserved', () => {
  assert.deepEqual(pathSegments('m10,20l30,0 v50 h-30 M100 100 120 100'),
    [[10, 20, 40, 20], [40, 20, 40, 70], [40, 70, 10, 70], [100, 100, 120, 100]]);
  assert.deepEqual(pathSegments('M1e2 -2e1 H120'), [[100, -20, 120, -20]]);
  assert.equal(pathSegments('M0 0 C10 0 20 10 30 0'), null);
  assert.equal(pathSegments('M0 H2'), null);
});

test('duplicate, reversed and overlapping edges become one stroke, gaps remain gaps', () => {
  assert.deepEqual(mergeSegments([[10, 0, 10, 50], [10, 50, 10, 0], [10, 25, 10, 75], [10, 80, 10, 90], [0, 5, 10, 5], [10, 5, 20, 5]]),
    [[10, 0, 10, 75], [10, 80, 10, 90], [0, 5, 20, 5]]);
});

test('device alignment includes SVG viewBox scale, internal zoom, group translation and scroll', () => {
  for (const ratio of [0.8, 1, 1.125, 1.25, 1.5, 2]) {
    for (const zoom of [0.3, 0.65, 1, 1.2, 3]) {
      const matrix = { a: zoom, b: 0, c: 0, d: zoom, e: 313.888885, f: -338.777771 };
      const segments = screenSegments([[0, 64, 220, 64], [220, 64, 220, 128]], matrix, ratio);
      for (const segment of segments) for (const value of segment) {
        const physical = value * ratio;
        assert.ok(Math.abs(physical - (Math.round(physical - 0.5) + 0.5)) < 1e-6);
      }
      assert.equal(segments[0][2], segments[1][0]);
      assert.equal(segments[0][3], segments[1][1]);
      const point = transform(220, 64, matrix);
      assert.deepEqual(point, [220 * zoom + matrix.e, 64 * zoom + matrix.f]);
    }
  }
});

test('independent diagrams do not share geometry and diagonal legacy connectors remain intact', () => {
  assert.deepEqual(mergeSegments([[0, 0, 20, 5]]), [[0, 0, 20, 5]]);
  const first = screenSegments([[0, 0, 20, 0]], { a: 1, b: 0, c: 0, d: 1, e: 0, f: 0 });
  const second = screenSegments([[0, 0, 20, 0]], { a: 2, b: 0, c: 0, d: 2, e: 100, f: 50 });
  assert.deepEqual(first, [[0.5, 0.5, 20.5, 0.5]]);
  assert.deepEqual(second, [[100.5, 50.5, 140.5, 50.5]]);
});
