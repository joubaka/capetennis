/* Shared presentation adapter. Never reads or changes draw/fixture state. */
(function (root) {
  'use strict';
  const transform = (x, y, m) => [m.a * x + m.c * y + m.e, m.b * x + m.d * y + m.f];

  // Our renderers emit straight M/L/H/V paths. Unsupported curves stay untouched.
  function pathSegments(path) {
    if (/[^\d\s.,+eE\-MLHVmlhv]/.test(path)) return null;
    const tokens = path.match(/[MLHVmlhv]|[-+]?(?:\d*\.)?\d+(?:e[-+]?\d+)?/gi) || [];
    const segments = [];
    let i = 0, command, x = 0, y = 0;
    while (i < tokens.length) {
      if (/^[MLHV]$/i.test(tokens[i])) command = tokens[i++];
      if (!command) return null;
      const kind = command.toUpperCase(), relative = command !== kind;
      const count = kind === 'M' || kind === 'L' ? 2 : 1;
      const values = tokens.slice(i, i + count).map(Number);
      if (values.length !== count || values.some(v => !Number.isFinite(v))) return null;
      i += count;
      const nx = kind === 'V' ? x : values[0] + (relative ? x : 0);
      const ny = kind === 'H' ? y : values[count - 1] + (relative ? y : 0);
      if (kind !== 'M') segments.push([x, y, nx, ny]);
      x = nx; y = ny;
      if (kind === 'M') command = relative ? 'l' : 'L';
    }
    return segments;
  }

  // Merge coincident edges so adjoining matches never paint the same line twice.
  function mergeSegments(segments) {
    const groups = new Map(), diagonals = [];
    for (const [x1, y1, x2, y2] of segments) {
      if (x1 === x2 && y1 === y2) continue;
      const horizontal = y1 === y2;
      if (!horizontal && x1 !== x2) { diagonals.push([x1, y1, x2, y2]); continue; }
      const fixed = horizontal ? y1 : x1, key = `${horizontal ? 'h' : 'v'}:${fixed}`;
      if (!groups.has(key)) groups.set(key, { horizontal, fixed, intervals: [] });
      groups.get(key).intervals.push(horizontal ? [Math.min(x1, x2), Math.max(x1, x2)] : [Math.min(y1, y2), Math.max(y1, y2)]);
    }
    const result = [...diagonals];
    for (const { horizontal, fixed, intervals } of groups.values()) {
      const merged = [];
      for (const interval of intervals.sort((a, b) => a[0] - b[0])) {
        const last = merged[merged.length - 1];
        if (last && interval[0] <= last[1]) last[1] = Math.max(last[1], interval[1]);
        else merged.push([...interval]);
      }
      merged.forEach(([a, b]) => result.push(horizontal ? [a, fixed, b, fixed] : [fixed, a, fixed, b]));
    }
    return result;
  }

  function screenSegments(segments, matrix, ratio = 1) {
    const dpr = Number.isFinite(ratio) && ratio > 0 ? ratio : 1;
    const snap = v => (Math.round(v * dpr) + 0.5) / dpr;
    return segments.map(([x1, y1, x2, y2]) => [...transform(x1, y1, matrix).map(snap), ...transform(x2, y2, matrix).map(snap)]);
  }
  const api = { pathSegments, mergeSegments, screenSegments, transform };
  if (typeof module === 'object' && module.exports) { module.exports = api; return; }
  if (root.TennisBracket) return;
  root.TennisBracket = api;
  const ns = 'http://www.w3.org/2000/svg';
  let frame = null;
  const observed = new Set();
  const resize = new ResizeObserver(() => queue());

  function draw(svg) {
    if (!observed.has(svg)) { resize.observe(svg); observed.add(svg); }
    const matrix = svg.getScreenCTM();
    if (!matrix || !matrix.a || !matrix.d || !svg.getClientRects().length) return;
    const inverse = matrix.inverse(), segments = [], vectors = [], sources = [];
    svg.querySelectorAll('line, path[data-ct-edge]').forEach(source => {
      // Ignore a nested diagram; it owns its own coordinate system and overlay.
      if (source.closest('svg') !== svg) return;
      const local = source.tagName.toLowerCase() === 'line'
        ? [['x1', 'y1', 'x2', 'y2'].map(name => Number(source.getAttribute(name) || 0))]
        : pathSegments(source.getAttribute('d') || '');
      const ctm = source.getScreenCTM();
      if (!local || !ctm) return;
      segments.push(...screenSegments(local, ctm, root.devicePixelRatio));
      local.forEach(([x1, y1, x2, y2]) => {
        const a = transform(...transform(x1, y1, ctm), inverse);
        const b = transform(...transform(x2, y2, ctm), inverse);
        vectors.push([...a, ...b].map(value => Math.round(value * 1e8) / 1e8));
      });
      sources.push(source);
    });
    let layer = svg.querySelector(':scope > .ct-bracket-edges');
    if (!layer) {
      layer = document.createElementNS(ns, 'path');
      layer.setAttribute('class', 'ct-bracket-edges');
      layer.setAttribute('aria-hidden', 'true');
      svg.append(layer);
    }
    const d = mergeSegments(segments).map(([x1, y1, x2, y2]) => {
      const a = transform(x1, y1, inverse), b = transform(x2, y2, inverse);
      return `M ${a[0]} ${a[1]} L ${b[0]} ${b[1]}`;
    }).join(' ');
    if (layer.getAttribute('d') !== d) layer.setAttribute('d', d);
    let printLayer = svg.querySelector(':scope > .ct-bracket-print-edges');
    if (!printLayer) {
      printLayer = document.createElementNS(ns, 'path');
      printLayer.setAttribute('class', 'ct-bracket-print-edges');
      printLayer.setAttribute('aria-hidden', 'true');
      svg.append(printLayer);
    }
    const printPath = mergeSegments(vectors).map(([x1, y1, x2, y2]) => `M ${x1} ${y1} L ${x2} ${y2}`).join(' ');
    if (printLayer.getAttribute('d') !== printPath) printLayer.setAttribute('d', printPath);
    const stroke = `${1 / (root.devicePixelRatio || 1)}px`;
    if (layer.style.getPropertyValue('--ct-bracket-device-stroke') !== stroke) layer.style.setProperty('--ct-bracket-device-stroke', stroke);
    sources.forEach(source => source.setAttribute('data-ct-source', ''));
  }
  function queue() {
    if (frame !== null) return;
    frame = requestAnimationFrame(() => {
      frame = null;
      observed.forEach(svg => {
        if (!svg.isConnected) { resize.unobserve(svg); observed.delete(svg); }
      });
      document.querySelectorAll('svg.ct-bracket-svg').forEach(draw);
    });
  }
  api.refresh = queue;
  const observer = new MutationObserver(records => {
    if (records.some(record => !record.target.closest?.('.ct-bracket-edges, .ct-bracket-print-edges'))) queue();
  });
  observer.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'viewBox', 'width', 'height', 'd', 'transform', 'x1', 'x2', 'y1', 'y2'] });
  root.addEventListener('resize', queue);
  root.addEventListener('scroll', queue, { capture: true, passive: true });
  // Zoom transitions need a final alignment after the transform settles.
  document.addEventListener('transitionend', queue, true);
  root.addEventListener('afterprint', queue);
  queue();
})(typeof window === 'undefined' ? globalThis : window);
