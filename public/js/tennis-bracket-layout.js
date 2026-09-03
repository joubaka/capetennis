/* Shared line-bracket geometry. Presentation only: never resolves entrants or byes. */
(function (root) {
  'use strict';
  const dimensions = { width: 220, column: 260, slotHeight: 32, lineGap: 64 };

  function layout(entries, startY = 64) {
    const matches = new Map(entries.map(entry => [entry.key, entry]));
    const positions = new Map();
    const visiting = new Set();
    let nextLine = startY;
    const reserveLine = () => {
      const y = nextLine;
      nextLine += dimensions.lineGap;
      return y;
    };
    function position(key) {
      if (positions.has(key)) return positions.get(key);
      if (visiting.has(key)) throw new Error('Cyclic bracket layout');
      visiting.add(key);
      const match = matches.get(key);
      const lines = match.sources.map(source => {
        // Sources outside this section remain labelled, without misleading cross-section lines.
        const feeder = matches.get(source.match);
        return feeder && feeder.column < match.column ? position(feeder.key).middle : reserveLine();
      });
      let top = Math.min(...lines), bottom = Math.max(...lines);
      bottom = Math.max(bottom, top + dimensions.lineGap);
      // Non-standard paths may share a feeder. Keep separate matches in a column readable.
      for (const previous of positions.values()) {
        if (previous.column === match.column && top <= previous.bottom + dimensions.lineGap / 2 && bottom >= previous.top) {
          const shift = previous.bottom + dimensions.lineGap - top;
          top += shift;
          bottom += shift;
        }
      }
      const result = { column: match.column, x: match.column * dimensions.column, top, bottom, middle: (top + bottom) / 2 };
      positions.set(key, result);
      nextLine = Math.max(nextLine, bottom + dimensions.lineGap);
      visiting.delete(key);
      return result;
    }
    // Lay out each final from its actual feeders, rather than lining every round up at the top.
    [...entries].sort((a, b) => b.column - a.column).forEach(entry => position(entry.key));
    return {
      positions,
      width: (Math.max(0, ...entries.map(entry => entry.column)) + 1) * dimensions.column,
      height: Math.max(startY, ...[...positions.values()].map(p => p.bottom)) + 48
    };
  }
  function linePath(positions, connections, viewport = {}) {
    // Draw every edge in the same SVG coordinate system. Mixing CSS borders with
    // centred SVG strokes leaves a half-pixel step (and a grey seam) at the joins.
    // Snap in screen coordinates, not CSS coordinates. At fractional zoom the
    // SVG's origin and round spacing can otherwise split a vertical across pixels.
    const scale = 1 / strokeWidth(viewport.ratio);
    const pixelX = value => (Math.round((value + (viewport.x || 0)) * scale) + 0.5) / scale - (viewport.x || 0);
    const pixelY = value => (Math.round((value + (viewport.y || 0)) * scale) + 0.5) / scale - (viewport.y || 0);
    const paths = [...positions].map(position => {
      const x = pixelX(position.x), right = pixelX(position.x + dimensions.width);
      return `M ${x} ${pixelY(position.top)} H ${right} V ${pixelY(position.bottom)} H ${x}`;
    });
    connections.forEach(([x1, y1, x2, y2]) => {
      const start = `M ${pixelX(x1)} ${pixelY(y1)}`;
      paths.push(pixelY(y1) === pixelY(y2)
        ? `${start} H ${pixelX(x2)}`
        : `${start} H ${pixelX((x1 + x2) / 2)} V ${pixelY(y2)} H ${pixelX(x2)}`);
    });
    return paths.join(' ');
  }
  // Keep crispEdges at exactly one device pixel, including fractional browser zoom.
  // A 1 CSS-pixel stroke at e.g. 125% can otherwise rasterise into two columns.
  const strokeWidth = ratio => 1 / (Number.isFinite(ratio) && ratio > 0 ? ratio : 1);
  const api = { dimensions, layout, linePath, strokeWidth };
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TennisBracketLayout = api;
})(typeof window === 'undefined' ? globalThis : window);
