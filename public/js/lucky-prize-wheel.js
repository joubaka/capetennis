(function () {
  'use strict';
  const initialised = new WeakSet();
  let pending = () => false;
  function initialise() {
  const modal = document.querySelector('[data-lucky-prize]');
  if (!modal || initialised.has(modal)) return;
  initialised.add(modal);
  const find = selector => modal.querySelector(selector);
  const draw = find('[data-prize-draw]'), wheel = find('[data-prize-wheel]'), status = find('[data-prize-status]');
  const spin = find('[data-prize-spin]'), refresh = find('[data-prize-refresh]');
  let pool = null, busy = false, token = null;
  pending = () => token !== null;
  const svgNS = 'http://www.w3.org/2000/svg';
  const colours = ['#d8e8ba', '#b8d7df', '#f5ce9a', '#d8c5e7', '#bde1cc', '#f0bebe'];
  const point = angle => [200 + 194 * Math.cos((angle - 90) * Math.PI / 180), 200 + 194 * Math.sin((angle - 90) * Math.PI / 180)];
  function list(selector, players) {
    find(selector).replaceChildren(...players.map(player => { const li = document.createElement('li'); li.textContent = player.name; return li; }));
  }
  function renderWheel(players) {
    wheel.style.transition = 'none'; wheel.style.transform = 'rotate(0deg)'; wheel.replaceChildren();
    players.forEach((player, index) => {
      const start = index * 360 / players.length, end = (index + 1) * 360 / players.length, mid = (start + end) / 2;
      const a = point(start), b = point(end);
      const segment = document.createElementNS(svgNS, players.length === 1 ? 'circle' : 'path');
      if (players.length === 1) { segment.setAttribute('cx', 200); segment.setAttribute('cy', 200); segment.setAttribute('r', 194); }
      else segment.setAttribute('d', `M200 200 L${a.join(' ')} A194 194 0 ${end - start > 180 ? 1 : 0} 1 ${b.join(' ')} Z`);
      segment.setAttribute('fill', colours[index % colours.length]); segment.setAttribute('stroke', '#fff');
      const title = document.createElementNS(svgNS, 'title'); title.textContent = player.name; segment.append(title); wheel.append(segment);
      const text = document.createElementNS(svgNS, 'text');
      text.setAttribute('x', 380); text.setAttribute('y', 204); text.setAttribute('text-anchor', 'end');
      text.setAttribute('transform', `rotate(${mid - 90} 200 200)`); text.setAttribute('fill', '#173446');
      text.setAttribute('font-size', players.length > 20 ? 8 : 12);
      text.textContent = player.name.length > 24 ? player.name.slice(0, 23) + '…' : player.name; wheel.append(text);
    });
  }
  function buttons() { spin.disabled = busy || !pool?.ready; refresh.disabled = busy || token !== null; draw.disabled = busy || token !== null; }
  async function request(url, options) {
    const response = await fetch(url, {credentials: 'same-origin', ...options, headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}});
    const data = await response.json();
    if (!response.ok) { const error = new Error(data.message || 'The prize wheel could not be loaded.'); error.status = response.status; throw error; }
    return data;
  }
  async function load() {
    if (busy || token) return;
    spin.textContent = 'Spin for a lucky winner';
    busy = true; pool = null; buttons(); status.textContent = 'Checking eligible players…';
    try {
      pool = await request(draw.selectedOptions[0].dataset.poolUrl);
      renderWheel(pool.players); list('[data-prize-players]', pool.players); list('[data-prize-champions]', pool.champions); list('[data-prize-previous]', pool.previous_winners);
      find('[data-prize-count]').textContent = pool.players.length; status.textContent = pool.message;
    } catch (error) { status.textContent = error.message; }
    finally { busy = false; buttons(); }
  }
  async function award() {
    if (busy || !pool?.ready) return;
    busy = true; token ||= crypto.randomUUID(); buttons(); status.textContent = 'Selecting and recording the lucky winner…';
    try {
      const result = await request(draw.selectedOptions[0].dataset.spinUrl, {method: 'POST', body: JSON.stringify({token, revision: pool.revision})});
      renderWheel(result.players);
      const index = result.players.findIndex(player => player.id === result.winner.id);
      const angle = 1800 + 360 - (index + 0.5) * 360 / result.players.length;
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (!reduced) { wheel.getBoundingClientRect(); wheel.style.transition = 'transform 4s cubic-bezier(.15,.7,.1,1)'; }
      wheel.style.transform = `rotate(${angle}deg)`;
      if (!reduced) await new Promise(resolve => setTimeout(resolve, 4100));
      status.textContent = `Lucky prize winner: ${result.winner.name}!`;
      pool.ready = false; token = null; spin.textContent = 'Winner recorded';
      list('[data-prize-previous]', [...pool.previous_winners, result.winner]);
    } catch (error) {
      status.textContent = error.message;
      if ([403, 404, 409, 422, 429].includes(error.status)) { token = null; pool.ready = false; }
      else { status.textContent += ' Use Retry result to retrieve this same prize; do not start another spin.'; spin.textContent = 'Retry result'; }
    } finally { busy = false; buttons(); }
  }
  modal.addEventListener('show.bs.modal', load); draw.addEventListener('change', load);
  refresh.addEventListener('click', () => { spin.textContent = 'Spin for a lucky winner'; load(); }); spin.addEventListener('click', award);
  }
  window.CTLuckyPrize = {init: initialise, pending: () => pending()};
  initialise();
})();
