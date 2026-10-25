(() => {
  const tabs = [...document.querySelectorAll('[data-workspace-tab]')];
  const panels = new Map(tabs.map((tab) => [tab.dataset.workspaceTab, document.getElementById(`project-panel-${tab.dataset.workspaceTab}`)]));
  const activate = (name) => {
    if (!panels.has(name)) name = 'overview';
    tabs.forEach((tab) => { const selected = tab.dataset.workspaceTab === name; tab.setAttribute('aria-selected', String(selected)); tab.tabIndex = selected ? 0 : -1; });
    panels.forEach((panel, key) => { panel.hidden = key !== name; });
  };
  tabs.forEach((tab) => tab.addEventListener('click', () => { history.replaceState(null, '', `#${tab.dataset.workspaceTab}`); activate(tab.dataset.workspaceTab); }));
  document.querySelector('.pm-workspace-tabs')?.addEventListener('keydown', (event) => {
    if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
    event.preventDefault();
    const current = tabs.indexOf(document.activeElement);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
    tabs[next].focus(); tabs[next].click();
  });
  window.addEventListener('hashchange', () => activate(location.hash.slice(1)));
  activate(location.hash.slice(1));
  const editor = document.querySelector('.pm-project-editor');
  const reader = document.querySelector('.pm-project-reader');
  const area = document.querySelector('[data-description-editor]');
  if (!editor || !reader || !area) return;
  const open = () => { activate('overview'); editor.hidden = false; reader.hidden = true; area.focus(); };
  const close = () => { editor.hidden = true; reader.hidden = false; };
  document.querySelectorAll('[data-project-edit]').forEach((button) => button.addEventListener('click', open));
  document.querySelector('[data-project-cancel]')?.addEventListener('click', close);
  const wrap = (before, after = before, fallback = 'text') => {
    const start = area.selectionStart, end = area.selectionEnd;
    const selected = area.value.slice(start, end) || fallback;
    area.setRangeText(before + selected + after, start, end, 'select'); area.focus();
  };
  document.querySelectorAll('[data-editor-tool]').forEach((button) => button.addEventListener('click', () => {
    switch (button.dataset.editorTool) {
      case 'heading': wrap('## ', '', 'Heading'); break;
      case 'bold': wrap('**'); break;
      case 'italic': wrap('*'); break;
      case 'bullet': wrap('- ', '', 'List item'); break;
      case 'number': wrap('1. ', '', 'List item'); break;
      case 'quote': wrap('> ', '', 'Quote'); break;
      case 'link': wrap('[', '](https://example.com)', 'link text'); break;
      case 'undo': document.execCommand('undo'); break;
      case 'redo': document.execCommand('redo'); break;
    }
  }));
  const preview = document.querySelector('[data-description-output]');
  document.querySelector('[data-description-preview]')?.addEventListener('click', async () => {
    if (!preview.hidden) { preview.hidden = true; area.hidden = false; return; }
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    try {
      const response = await fetch(document.querySelector('[data-project-form]').action.replace(/\/$/, '') + '/description/preview', {
        method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ description: area.value })
      });
      if (!response.ok) throw new Error('Preview unavailable');
      preview.innerHTML = (await response.json()).html;
      preview.hidden = false; area.hidden = true;
    } catch (_) { preview.textContent = 'Preview unavailable. Your text is still in the editor.'; preview.hidden = false; }
  });
  area.addEventListener('paste', (event) => {
    const html = event.clipboardData?.getData('text/html');
    if (!html) return;
    event.preventDefault();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    doc.querySelectorAll('script,style,iframe,object,embed,svg,form,input').forEach((node) => node.remove());
    const convert = (node, depth = 0) => {
      if (node.nodeType === Node.TEXT_NODE) return node.textContent.replace(/\s+/g, ' ');
      if (node.nodeType !== Node.ELEMENT_NODE) return '';
      const tag = node.tagName.toLowerCase();
      if (tag === 'ul' || tag === 'ol') return [...node.children].map((child) => convert(child, depth)).join('') + '\n';
      if (tag === 'li') {
        const main = [...node.childNodes].filter((child) => !['UL','OL'].includes(child.tagName)).map((child) => convert(child, depth)).join('').trim();
        const nested = [...node.children].filter((child) => ['UL','OL'].includes(child.tagName)).map((child) => convert(child, depth + 1)).join('');
        const number = [...node.parentElement.children].indexOf(node) + 1;
        return `${'  '.repeat(depth)}${node.parentElement.tagName === 'OL' ? `${number}.` : '-'} ${main}\n${nested}`;
      }
      if (tag === 'table') {
        const rows = [...node.querySelectorAll('tr')].map((row) => [...row.querySelectorAll('th,td')].map((cell) => cell.textContent.trim().replace(/\|/g, '\\|')));
        if (!rows.length) return '';
        const width = Math.max(...rows.map((row) => row.length));
        const line = (cells) => `| ${[...cells, ...Array(width - cells.length).fill('')].join(' | ')} |`;
        return `\n\n${line(rows[0])}\n${line(Array(width).fill('---'))}\n${rows.slice(1).map(line).join('\n')}\n\n`;
      }
      const content = [...node.childNodes].map((child) => convert(child, depth)).join('');
      switch (node.tagName.toLowerCase()) {
        case 'h1': case 'h2': return `\n\n## ${content.trim()}\n\n`;
        case 'h3': return `\n\n### ${content.trim()}\n\n`;
        case 'h4': return `\n\n#### ${content.trim()}\n\n`;
        case 'p': case 'div': return `\n\n${content.trim()}\n\n`;
        case 'br': return '  \n';
        case 'strong': case 'b': return `**${content.trim()}**`;
        case 'em': case 'i': return `*${content.trim()}*`;
        case 'a': { const href = node.getAttribute('href') || ''; return /^(https?:\/\/|mailto:|\/|#)/i.test(href) ? `[${content.trim()}](${href.replace(/[()\s]/g, encodeURIComponent)})` : content; }
        default: return content;
      }
    };
    area.setRangeText([...doc.body.childNodes].map(convert).join('').replace(/\n{3,}/g, '\n\n').trim(), area.selectionStart, area.selectionEnd, 'end');
  });
  document.querySelector('[data-copy-project]')?.addEventListener('click', async () => { try { await navigator.clipboard.writeText(location.href); } catch (_) { window.prompt('Copy project link', location.href); } });
  if (document.querySelector('.pm-alert.error')) open();
})();
