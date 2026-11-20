/* Draft-only local preview. Untrusted copy is inserted as text, never HTML. */
(() => {
  'use strict';
  const root = document.querySelector('[data-marketing-editor]');
  if (!root) return;
  const field = name => root.querySelector(`[name="${name}"]`);
  const node = (tag, text, className) => {
    const element = document.createElement(tag);
    if (text !== undefined) element.textContent = text;
    if (className) element.className = className;
    return element;
  };
  // A tiny text formatter, not a general Markdown or HTML interpreter.
  const inline = (parent, text) => {
    const pieces = text.split(/(\*\*[^*\n]+\*\*|\*[^*\n]+\*)/g);
    for (const piece of pieces) {
      if (/^\*\*[^*]+\*\*$/.test(piece)) parent.append(node('strong', piece.slice(2, -2)));
      else if (/^\*[^*]+\*$/.test(piece)) parent.append(node('strong', piece.slice(1, -1)));
      else parent.append(document.createTextNode(piece));
    }
  };
  const cells = line => line.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map(value => value.trim());
  const isTableLine = line => line !== undefined && line.includes('|') && cells(line).length >= 2;
  const render = (target, text) => {
    target.replaceChildren();
    const lines = text.replace(/\r\n?/g, '\n').split('\n');
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;
      if (isTableLine(line) && isTableLine(lines[i + 1]) && cells(line).length === cells(lines[i + 1]).length) {
        const wrapper = node('div', undefined, 'marketing-preview-table');
        const table = node('table');
        const heading = node('thead');
        const body = node('tbody');
        const headingRow = node('tr');
        for (const value of cells(line)) { const th = node('th'); th.scope = 'col'; inline(th, value); headingRow.append(th); }
        heading.append(headingRow);
        const columnCount = cells(line).length;
        while (isTableLine(lines[i + 1]) && cells(lines[i + 1]).length === columnCount) {
          const values = cells(lines[++i]);
          if (values.every(value => /^:?-{3,}:?$/.test(value))) continue;
          const row = node('tr');
          for (const value of values) { const td = node('td'); inline(td, value); row.append(td); }
          body.append(row);
        }
        table.append(heading, body); wrapper.append(table); target.append(wrapper);
      } else if (/^#{1,3}\s+/.test(line) || (line.length <= 100 && /[A-Z]/.test(line) && line === line.toUpperCase())) {
        const heading = node('h3'); inline(heading, line.replace(/^#{1,3}\s+/, '')); target.append(heading);
      } else {
        const paragraph = node('p'); inline(paragraph, line); target.append(paragraph);
      }
    }
    if (!text.trim()) target.append(node('p', 'Start writing to see your preview.', 'marketing-preview-empty'));
  };
  const update = () => {
    root.querySelector('[data-preview-campaign]').textContent = field('title').value.trim() || 'Untitled campaign';
    const subject = field('subject').value.trim();
    root.querySelector('[data-preview-subject]').textContent = subject || 'No subject entered';
    root.querySelector('[data-preview-email-heading]').textContent = subject || 'Your CTR invitation';
    const whatsappHeading = field('whatsapp_body').value.split(/\r?\n/).find(line => line.trim()) || 'Your CTR invitation';
    root.querySelector('[data-preview-whatsapp-heading]').textContent = whatsappHeading.trim().replace(/\*\*([^*\n]+)\*\*|\*([^*\n]+)\*/g, (_, bold, emphasis) => bold ?? emphasis);
    render(root.querySelector('[data-preview-email]'), field('email_body').value);
    render(root.querySelector('[data-preview-whatsapp]'), field('whatsapp_body').value);
    for (const name of ['email_body', 'whatsapp_body']) {
      root.querySelector(`[data-count="${name}"]`).textContent = `${Array.from(field(name).value).length.toLocaleString()} characters`;
    }
  };
  let pending;
  root.querySelectorAll('input[name="title"], input[name="subject"], textarea').forEach(element => element.addEventListener('input', () => {
    clearTimeout(pending); pending = setTimeout(update, 100);
  }));
  const tabs = Array.from(root.querySelectorAll('[data-preview-tab]'));
  const select = tab => {
    tabs.forEach(button => {
      const selected = button === tab;
      button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1;
      button.classList.toggle('active', selected);
      root.querySelector(`#${button.getAttribute('aria-controls')}`).hidden = !selected;
    });
  };
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => select(tab));
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next === undefined) return;
      event.preventDefault(); select(tabs[next]); tabs[next].focus();
    });
  });
  update();
})();
