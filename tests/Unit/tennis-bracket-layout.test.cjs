const { test } = require('node:test');
const assert = require('node:assert/strict');
const { layout, dimensions, linePath, strokeWidth } = require('../../public/js/tennis-bracket-layout.js');
const direct = () => ({ type: 'player' });
const feeder = match => ({ type: 'winner', match });

test('all horizontal and vertical edges align to physical pixel centres after zoom and scrolling', () => {
  for (const ratio of [0.8, 1, 1.125, 1.25, 1.5, 2]) {
    for (const origin of [{ x: 313.888885, y: -338.777771 }, { x: -127.3, y: 45.6 }]) {
      const path = linePath([{ x: 260, top: 96, bottom: 224 }], [[220, 96, 260, 96]], { ratio, ...origin });
      let axis = 'x';
      for (const token of path.split(' ')) {
        if (token === 'M' || token === 'H') { axis = 'x'; continue; }
        if (token === 'V') { axis = 'y'; continue; }
        const physical = (Number(token) + origin[axis]) * ratio;
        assert.ok(Math.abs(physical - (Math.round(physical - 0.5) + 0.5)) < 0.000001);
        axis = 'y';
      }
    }
  }
});

test('fractional zoom never expands a bracket stroke into two device pixels', () => {
  for (const ratio of [0.8, 1, 1.125, 1.25, 1.5, 1.75, 2, 2.5]) {
    assert.equal(strokeWidth(ratio) * ratio, 1);
  }
  assert.equal(strokeWidth(undefined), 1);
  assert.equal(strokeWidth(0), 1);
});

test('bracket edges and connectors share one pixel-aligned stroke at each join', () => {
  const positions = [
    { x: 0, top: 64, bottom: 128 },
    { x: 260, top: 96, bottom: 224 }
  ];
  const path = linePath(positions, [[220, 96, 260, 96]]);
  assert.equal(path, 'M 0.5 64.5 H 220.5 V 128.5 H 0.5 M 260.5 96.5 H 480.5 V 224.5 H 260.5 M 220.5 96.5 H 260.5');
});

test('custom-path elbows use the same pixel alignment as bracket edges', () => {
  assert.equal(linePath([], [[220, 96.25, 520, 128.25]]), 'M 220.5 96.5 H 370.5 V 128.5 H 520.5');
});

test('standard bracket centres later rounds on their actual feeders', () => {
  const entries = [
    { key: 'a', column: 0, sources: [direct(), direct()] },
    { key: 'b', column: 0, sources: [direct(), direct()] },
    { key: 'final', column: 1, sources: [feeder('a'), feeder('b')] }
  ];
  const { positions, height, width } = layout(entries);
  assert.equal(positions.get('final').top, positions.get('a').middle);
  assert.equal(positions.get('final').bottom, positions.get('b').middle);
  assert.equal(width, dimensions.column * 2);
  assert.ok(height > positions.get('b').bottom);
  assert.deepEqual(layout(entries.slice().reverse()).positions.get('final'), positions.get('final'));
});

test('direct later-round entry consumes one line without inventing a qualifying match', () => {
  const { positions } = layout([
    { key: 'qualifier', column: 0, sources: [direct(), direct()] },
    { key: 'final', column: 1, sources: [direct(), feeder('qualifier')] }
  ]);
  assert.equal(positions.size, 2);
  assert.equal(positions.get('final').bottom, positions.get('qualifier').middle);
  assert.ok(positions.get('final').top < positions.get('qualifier').top);
});

test('placement sources outside a section retain independent readable positions', () => {
  const result = layout([
    { key: 'placement', column: 0, sources: [{ type: 'loser', match: 'elsewhere' }, direct()] }
  ], 400);
  assert.equal(result.positions.get('placement').top, 400);
  assert.equal(result.positions.get('placement').bottom, 464);
  assert.equal(result.height, 512);
});

test('64-player draw fits every match in its full-height bounds without same-round overlap', () => {
  const entries = [];
  for (let column = 0; column < 6; column++) {
    for (let i = 0; i < 32 / 2 ** column; i++) {
      entries.push({ key: `${column}-${i}`, column, sources: column ? [feeder(`${column - 1}-${i * 2}`), feeder(`${column - 1}-${i * 2 + 1}`)] : [direct(), direct()] });
    }
  }
  const { positions, height } = layout(entries);
  assert.equal(positions.size, 63);
  for (let column = 0; column < 6; column++) {
    const round = [...positions.values()].filter(p => p.column === column).sort((a, b) => a.top - b.top);
    round.forEach((p, index) => {
      assert.ok(p.bottom < height);
      if (index) assert.ok(p.top - dimensions.slotHeight > round[index - 1].bottom);
    });
  }
});
