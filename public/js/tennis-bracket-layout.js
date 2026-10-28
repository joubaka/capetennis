/* Shared line-bracket presentation. Never creates fixtures or changes results. */
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
    const pixelX = value => viewport.raw ? value : (Math.round((value + (viewport.x || 0)) * scale) + 0.5) / scale - (viewport.x || 0);
    const pixelY = value => viewport.raw ? value : (Math.round((value + (viewport.y || 0)) * scale) + 0.5) / scale - (viewport.y || 0);
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
  function terminalWinnerKeys(matches, { withinSections = false } = {}) {
    const entries = Object.entries(matches);
    const followedWinners = new Set();
    entries.forEach(([, match]) => {
      match.sources.forEach(source => {
        if (source.type === 'winner' && source.match
          && (!withinSections || matches[source.match]?.section === match.section)) followedWinners.add(source.match);
      });
    });
    return entries.map(([key]) => key).filter(key => !followedWinners.has(key));
  }
  // The compiler omits unplayed bye matches. Restore their lines for a full
  // opening bracket without adding them to the canonical match/fixture state.
  function withMainDrawByes(matches, draft) {
    const depth = Math.log2(Number(draft?.size));
    if (!Number.isInteger(depth) || depth < 2 || depth > 6) return matches;
    const slots = draft.slots || {};
    const main = Object.entries(matches).filter(([, match]) => match.section === 'Main draw');
    if (!main.length) return matches;
    const byPath = new Map(main.map(([key, match]) => [match.path, [key, match]]));
    const expanded = {};
    const visit = path => {
      if (path.length === depth) return { source: slots[path] || {type: 'bye'} };
      const children = [visit(path + 'a'), visit(path + 'b')];
      const existing = byPath.get(path);
      const key = existing?.[0] || `display_bye_${path || 'final'}`;
      const sources = children.map(child => child.key
        ? {...child.source, original_match: child.source.match, match: child.key}
        : {...child.source});
      let source;
      if (existing) {
        const match = existing[1];
        expanded[key] = {...match, sources};
        source = {type: 'winner', match: key};
      } else {
        source = children.find(child => child.source.type !== 'bye')?.source || {type: 'bye'};
        const players = children.map(child => child.source.type === 'player' ? child.source.id
          : child.source.type === 'winner' ? matches[child.source.match]?.winner || null : null);
        expanded[key] = {
          section: 'Main draw', path, round: depth - path.length,
          label: ['Final', 'Semifinals', 'Quarterfinals'][path.length] || `Round of ${2 ** (path.length + 1)}`,
          sources, players, byes: children.map(child => child.source.type === 'bye'),
          winner: source.type === 'player' ? source.id : matches[source.match]?.winner || null,
          sets: [], displayOnly: true,
        };
      }
      return {key, source};
    };
    visit('');
    return {...expanded, ...Object.fromEntries(Object.entries(matches).filter(([, match]) => match.section !== 'Main draw'))};
  }
  // Keep crispEdges at exactly one device pixel, including fractional browser zoom.
  // A 1 CSS-pixel stroke at e.g. 125% can otherwise rasterise into two columns.
  const strokeWidth = ratio => 1 / (Number.isFinite(ratio) && ratio > 0 ? ratio : 1);
  const api = { dimensions, layout, linePath, terminalWinnerKeys, strokeWidth, withMainDrawByes };
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TennisBracketLayout = api;
})(typeof window === 'undefined' ? globalThis : window);
