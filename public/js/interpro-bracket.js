/* Read-only adapter for the actual Flexible Monrad layout, DOM and theme. */
(function (root) {
  'use strict';
  if (root.customElements.get('ct-interpro-bracket')) return;
  const create = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  class InterproBracket extends HTMLElement {
    connectedCallback() {
      queueMicrotask(() => {
        if (this.render()) return;
        this.observer = new MutationObserver(() => { if (this.render()) this.observer.disconnect(); });
        this.observer.observe(this, { childList: true, subtree: true });
      });
    }
    disconnectedCallback() { this.observer?.disconnect(); }
    render() {
      if (this.dataset.rendered === 'true') return true;
      const payload = this.querySelector('[data-interpro-payload]');
      if (!payload?.textContent.trim()) return false;
      try {
        const data = JSON.parse(payload.textContent), layout = root.TennisBracketLayout;
        this.querySelector('[data-bracket-loading]')?.remove();
        this.classList.add('fm-surface');
        data.boards.forEach(boardDefinition => this.append(this.board(boardDefinition, data.matches, layout)));
        this.dataset.rendered = 'true';
        root.TennisBracket?.refresh();
      } catch (error) {
        this.append(create('p', 'alert alert-warning', 'The playoff bracket could not be displayed. Please refresh the page.'));
        this.dataset.rendered = 'error';
      }
      return true;
    }
    board(definition, matches, engine) {
      const scroll = create('div', 'fm-scroll interpro-bracket-scroll');
      scroll.tabIndex = 0;
      scroll.setAttribute('role', 'region');
      scroll.setAttribute('aria-label', definition.title);
      const board = create('div', 'fm-board');
      board.dataset.board = definition.key;
      const positions = new Map(), lines = [];
      let yOffset = 0, width = 500;
      definition.sections.forEach(section => {
        const entries = section.matches.map(number => [String(number), matches[number]]);
        const title = create('div', 'fm-section-label', section.title);
        title.style.top = yOffset + 'px';
        board.append(title);
        const rounds = [...new Set(entries.map(([, match]) => match.round))].sort((a, b) => a - b);
        const column = match => section.independent ? 0 : rounds.indexOf(match.round);
        const result = engine.layout(entries.map(([key, match]) => ({ key, column: column(match), sources: match.sources })), yOffset + 96);
        const headings = section.independent ? [section.roundLabels[0]] : section.roundLabels;
        headings.forEach((text, index) => {
          const heading = create('div', 'fm-round-title', text);
          Object.assign(heading.style, { top: yOffset + 32 + 'px', left: index * engine.dimensions.column + 'px' });
          board.append(heading);
        });
        entries.forEach(([key, match]) => {
          const position = result.positions.get(key);
          positions.set(key, position);
          const card = engine.createMatch(position, 'Match ' + match.number);
          Object.assign(card.dataset, { match: key, stage: match.stage, round: String(match.round),
            position: match.position ? String(match.position) : '',
            slotTop: String(position.top), slotBottom: String(position.bottom) });
          match.players.forEach((player, slot) => {
            const outcome = player && match.winner ? (Number(player.id) === Number(match.winner) ? 'winner' : 'loser') : '';
            const row = engine.createSlot(slot ? position.bottom - position.top : 0, outcome);
            const label = player
              ? create('span', 'fm-slot-name fm-player-identity', player.name)
              : create('span', 'fm-slot-name fm-source-name' + (['winner', 'loser'].includes(match.sources[slot].type) ? ' fm-feeder-name' : ''), match.sources[slot].label);
            row.title = label.textContent + ' · ' + match.sources[slot].label;
            label.title = player?.name || match.sources[slot].label;
            if (player && match.can_lucky_loser && match.fixture_id) {
              label.setAttribute('role', 'button'); label.setAttribute('tabindex', '0');
              label.setAttribute('aria-haspopup', 'menu'); label.setAttribute('aria-expanded', 'false');
              Object.assign(label.dataset, {llFixture: String(match.fixture_id), llSlot: String(slot + 1), llRegistration: String(player.id), llName: player.name});
              label.style.cursor = 'pointer';
            }
            row.append(label);
            if (player?.lucky_loser) {
              label.textContent = player.name.replace(/ \(LL\)$/, '');
              const badge = create('span', 'badge bg-label-warning', 'LL');
              badge.setAttribute('aria-label', 'Lucky loser');
              row.append(badge);
            }
            if (match.sets.length) {
              const score = create('strong', '', match.sets.map(set => set[slot]).join(' '));
              score.dataset.slot = String(slot);
              row.append(score);
            }
            card.append(row);
          });
          if (match.schedule) {
            const date = new Date(match.schedule.time.replace(' ', 'T'));
            const time = new Intl.DateTimeFormat('en-ZA', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }).format(date).replace(',', '');
            const preview = match.schedule.preview === true;
            const label = [preview ? 'Preview only' : 'Published', time, match.schedule.venue, match.schedule.court ? 'Court ' + match.schedule.court : null].filter(Boolean).join(' · ');
            const schedule = create('div', 'fm-match-schedule ' + (preview ? 'is-preview' : 'is-published'), label);
            schedule.title = label;
            schedule.setAttribute('aria-label', label);
            card.append(schedule);
          }
          if (match.note) card.append(create('div', 'fm-match-note', match.note));
          board.append(card);
          // Exactly the normal winner branches from the actual Monrad renderer.
          // Losers entering another branch are labels/tooltips, never extra lines.
          match.sources.forEach((source, slot) => {
            const from = result.positions.get(source.match);
            if (source.type === 'winner' && from) lines.push([from.x + engine.dimensions.width, from.middle, position.x, slot ? position.bottom : position.top]);
          });
        });
        const terminal = engine.terminalWinnerKeys(Object.fromEntries(entries));
        terminal.forEach(key => {
          const match = matches[key], position = result.positions.get(key);
          const lineEnd = position.x + engine.dimensions.width + 120;
          const endpoint = engine.createEndpoint(position, match.position === 1 ? 'Champion' : 'Position ' + match.position);
          endpoint.dataset.terminalFrom = key;
          const name = endpoint.querySelector('.fm-winner-name');
          const champion = match.players.find(player => player && Number(player.id) === Number(match.winner));
          if (champion) {
            const identity = create('span', 'fm-player-identity fm-player-identity-winner', champion.name);
            identity.title = champion.name;
            name.append(identity);
          }
          else name.append(create('span', 'fm-pending-result', 'Awaiting result'));
          board.append(endpoint);
          lines.push([position.x + engine.dimensions.width, position.middle, lineEnd, position.middle]);
          width = Math.max(width, lineEnd + 10 + engine.dimensions.width);
        });
        width = Math.max(width, result.width);
        yOffset = result.height + 35;
      });
      Object.assign(board.style, { width: width + 'px', height: yOffset + 'px' });
      board.style.setProperty('--ct-print-scale', Math.min(1, 980 / width, 650 / yOffset));
      board.prepend(engine.createLinks(positions.values(), lines, width, yOffset));
      scroll.append(board);
      return scroll;
    }
  }
  root.customElements.define('ct-interpro-bracket', InterproBracket);
})(window);
