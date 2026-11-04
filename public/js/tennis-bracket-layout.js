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
  // Restore the fixed 16/32 feed-in template's collapsed bye paths for display.
  // Match signatures must agree with the saved graph; older/different topologies
  // are left intact rather than drawing a pathway that was never generated.
  function withFeedInByes(matches, draft) {
    const size = Number(draft?.size);
    if (![16, 32].includes(size)) return matches;
    const entries = Object.entries(matches);
    const section = entries.find(([, match]) => / · Feed-in$/.test(match.section))?.[1].section;
    if (!section) return matches;
    const main = new Map(entries.filter(([, match]) => match.section === 'Main draw' && !match.displayOnly)
      .map(([key, match]) => [match.path, key]));
    const signature = sources => JSON.stringify(sources.map(source => [source.type, source.match || null, source.id || null]));
    const real = new Map(entries.filter(([, match]) => match.section === section)
      .map(([key, match]) => [signature(match.sources), [key, match]]));
    const projected = {}, used = new Set();
    let round = 0, failed = false;
    const band = depth => Array.from({length: 2 ** depth}, (_, index) => {
      const path = index.toString(2).padStart(depth, '0').replaceAll('0', 'a').replaceAll('1', 'b');
      return {source: main.has(path) ? {type: 'loser', match: main.get(path)} : {type: 'bye'}};
    });
    const player = entry => {
      if (entry.source.type === 'player') return entry.source.id;
      const match = matches[entry.source.match];
      if (!match?.winner) return null;
      return entry.source.type === 'winner' ? match.winner
        : match.players.find(id => id && Number(id) !== Number(match.winner)) || null;
    };
    const step = (survivors, incoming = null) => {
      round++;
      const pairs = incoming ? incoming.map((entry, index) => [entry, survivors[index]])
        : Array.from({length: survivors.length / 2}, (_, index) => survivors.slice(index * 2, index * 2 + 2));
      return pairs.map((pair, index) => {
        const sources = pair.map(entry => entry.key
          ? {...entry.source, original_match: entry.source.match ?? null, match: entry.key} : {...entry.source});
        const actual = pair.map(entry => entry.source);
        if (actual.every(source => source.type !== 'bye')) {
          const existing = real.get(signature(actual));
          if (!existing) { failed = true; return {source: {type: 'bye'}}; }
          const [key, match] = existing;
          used.add(key);
          projected[key] = {...match, sources, round};
          return {key, source: {type: 'winner', match: key}};
        }
        const key = `display_feed_bye_${round}_${index}`;
        const survivor = pair.find(entry => entry.source.type !== 'bye') || {source: {type: 'bye'}};
        projected[key] = {section, round, path: null, label: `Feed-in round ${round}`, sources,
          players: pair.map(player), byes: actual.map(source => source.type === 'bye'),
          winner: player(survivor), sets: [], displayOnly: true};
        return {key, source: survivor.source};
      });
    };
    const openingDepth = Math.log2(size) - 1;
    let survivors = step(band(openingDepth));
    survivors = step(survivors, band(openingDepth - 1).reverse());
    if (size === 32) {
      survivors = step(survivors);
      survivors = step(survivors, band(2));
    }
    while (survivors.length > 1) survivors = step(survivors);
    if (failed || used.size !== real.size) return matches;
    const result = {};
    let inserted = false;
    entries.forEach(([key, match]) => {
      if (match.section !== section) result[key] = match;
      else if (!inserted) { Object.assign(result, projected); inserted = true; }
    });
    return result;
  }
  // Keep crispEdges at exactly one device pixel, including fractional browser zoom.
  // A 1 CSS-pixel stroke at e.g. 125% can otherwise rasterise into two columns.
  const strokeWidth = ratio => 1 / (Number.isFinite(ratio) && ratio > 0 ? ratio : 1);
  const api = { dimensions, layout, linePath, terminalWinnerKeys, strokeWidth, withMainDrawByes, withFeedInByes };
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TennisBracketLayout = api;
})(typeof window === 'undefined' ? globalThis : window);
