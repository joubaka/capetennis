/* Event HTML editor: retain document structure rather than translating through Quill Delta. */
(function () {
  'use strict';
  const tags = new Set('P DIV BR STRONG B EM I U S SUB SUP UL OL LI BLOCKQUOTE H1 H2 H3 H4 H5 H6 SPAN A TABLE CAPTION THEAD TBODY TFOOT TR TH TD IMG'.split(' '));
  const styles = new Set('color background-color font-family font-size font-weight font-style text-decoration text-align vertical-align line-height margin-left margin-right margin-top margin-bottom padding padding-left padding-right padding-top padding-bottom border border-width border-style border-color border-collapse width height list-style-type'.split(' '));
  const maxBytes = 4 * 1024 * 1024;
  const imageBytes = 1024 * 1024;
  const safeUrl = (value, image) => {
    if (/^[\s\u0000-\u001f]*data:/i.test(value)) return image && /^data:image\/(png|jpeg|gif);base64,[a-z0-9+/=\s]+$/i.test(value) && value.length <= Math.ceil(imageBytes * 4 / 3) + 64;
    if (/[\u0000-\u0020]/.test(value)) return false;
    try { const url = new URL(value, location.href); return ['http:', 'https:'].includes(url.protocol) || (!image && url.protocol === 'mailto:'); } catch (_) { return false; }
  };
  function clean(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    template.content.querySelectorAll('script,style,iframe,object,embed,svg,math,link,meta,base,form,input,button,textarea,select').forEach(el => el.remove());
    for (const el of Array.from(template.content.querySelectorAll('*')).reverse()) {
      if (!tags.has(el.tagName)) { el.replaceWith(...el.childNodes); continue; }
      for (const attr of Array.from(el.attributes)) {
        const name = attr.name;
        if (name === 'style') {
          for (const prop of Array.from(el.style)) {
            if (!styles.has(prop) || /url\s*\(|expression|[\\<>@]/i.test(el.style.getPropertyValue(prop))) el.style.removeProperty(prop);
          }
        } else if (name === 'class') {
          el.className = attr.value.split(/\s+/).filter(value => /^(ql-align-(center|right|justify)|ql-indent-[1-8]|ql-size-(small|large|huge)|ql-font-(serif|monospace))$/.test(value)).join(' ');
        } else if (name === 'src' && el.tagName === 'IMG') {
          if (!safeUrl(attr.value, true)) el.removeAttribute(name);
        } else if (name === 'href' && el.tagName === 'A') {
          if (!safeUrl(attr.value, false)) el.removeAttribute(name);
        } else if (!['alt', 'title', 'width', 'height', 'colspan', 'rowspan', 'border', 'cellpadding', 'cellspacing', 'start', 'scope'].includes(name)) el.removeAttribute(name);
      }
    }
    return template;
  }
  class EventRichTextEditor {
    constructor(selector) {
      this.root = typeof selector === 'string' ? document.querySelector(selector) : selector;
      this.root.innerHTML = clean(this.root.innerHTML).innerHTML;
      this.root.classList.add('event-document-content', 'event-document-editor');
      this.root.contentEditable = 'true';
      this.root.setAttribute('role', 'textbox');
      this.root.setAttribute('aria-multiline', 'true');
      if (!this.root.hasAttribute('aria-label') && !this.root.hasAttribute('aria-labelledby')) this.root.setAttribute('aria-label', 'Formatted event content');
      this.notice = document.createElement('p');
      this.notice.className = 'small text-muted mt-2';
      this.notice.setAttribute('role', 'status');
      this.notice.textContent = 'Paste from Word to retain text formatting, tables and supported images. Page breaks, floating shapes and exact page layout may change. If an image is missing, use Insert image. PNG, JPEG or GIF: up to 1 MB each; complete content: 4 MB. Email appearance may vary.';
      this.root.after(this.notice);
      const bar = document.createElement('div');
      bar.className = 'event-document-toolbar';
      bar.setAttribute('role', 'toolbar'); bar.setAttribute('aria-label', 'Text formatting');
      for (const [label, command] of [['Bold','bold'],['Italic','italic'],['Underline','underline'],['Bullets','insertUnorderedList'],['Numbering','insertOrderedList'],['Undo','undo'],['Redo','redo']]) {
        const button = document.createElement('button');button.type='button';button.textContent=label;button.className='btn btn-sm btn-outline-secondary';
        button.addEventListener('mousedown', e => e.preventDefault());button.addEventListener('click', () => { this.focus();document.execCommand(command);this.changed(); });bar.append(button);
      }
      for (const [label, command, value] of [['Heading', 'formatBlock', 'h2'], ['Paragraph', 'formatBlock', 'p'], ['Clear formatting', 'removeFormat', null]]) {
        const button=document.createElement('button');button.type='button';button.textContent=label;button.className='btn btn-sm btn-outline-secondary';
        button.addEventListener('mousedown',event=>event.preventDefault());button.addEventListener('click',()=>{this.focus();document.execCommand(command,false,value);this.changed();});bar.append(button);
      }
      const linkButton=document.createElement('button');linkButton.type='button';linkButton.textContent='Link';linkButton.className='btn btn-sm btn-outline-secondary';
      linkButton.addEventListener('mousedown',event=>event.preventDefault());linkButton.addEventListener('click',()=>{this.rememberSelection();const url=window.prompt('Link address (https:// or mailto:)');if(url && safeUrl(url,false)){this.focus();document.execCommand('createLink',false,url);this.changed();}});bar.append(linkButton);
      const imageButton = document.createElement('button');imageButton.type='button';imageButton.className='btn btn-sm btn-outline-secondary';imageButton.textContent='Insert image';
      const picker=document.createElement('input');picker.type='file';picker.accept='image/png,image/jpeg,image/gif';picker.hidden=true;
      imageButton.addEventListener('click', () => { this.rememberSelection();picker.click(); });
      picker.addEventListener('change', () => { if(picker.files[0]) this.insertImages([picker.files[0]]);picker.value=''; });bar.append(imageButton,picker);
      this.root.before(bar);
      this.root.addEventListener('input', () => this.changed());
      this.root.addEventListener('drop', event => { event.preventDefault();this.notice.textContent='Please paste document content or use Insert image.'; });
      this.root.addEventListener('paste', async event => {
        event.preventDefault();this.rememberSelection();
        const clipboard=event.clipboardData;
        const html=clipboard.getData('text/html');
        const files=Array.from(clipboard.items).filter(item => item.kind==='file').map(item => item.getAsFile()).filter(Boolean);
        if (new Blob([html || clipboard.getData('text/plain')]).size > maxBytes) { this.notice.textContent='Content exceeds 4 MB. Paste a smaller section.'; return; }
        try {
          this.pending=true;
          const images=await this.readImages(files);
          let template;
          if(html) {
            template=clean(html);
            let index=0, missing=false;
            template.content.querySelectorAll('img').forEach(img => { if(!img.hasAttribute('src')) { if(images[index]) img.src=images[index++];else {img.replaceWith(document.createTextNode('[Image missing — use Insert image]'));missing=true;} } });
            if(!template.content.querySelector('img') && images.length && !missing) images.forEach(src => { const img=document.createElement('img');img.src=src;template.content.append(img); });
            if(missing) this.notice.textContent='Word did not provide usable image data for every picture. Add missing pictures with Insert image.';
          } else {
            template=document.createElement('template');const p=document.createElement('p');clipboard.getData('text/plain').split(/\r?\n/).forEach((line,index)=>{if(index)p.append(document.createElement('br'));p.append(document.createTextNode(line));});template.content.append(p);
            images.forEach(src => {const img=document.createElement('img');img.src=src;template.content.append(img);});
          }
          this.insert(template.innerHTML);
        } catch(error) { this.notice.textContent=error.message; } finally { this.pending=false; }
      });
      const form=this.root.closest('form');
      form?.addEventListener('submit', event => { if(this.pending || new Blob([this.root.innerHTML]).size>maxBytes) {event.preventDefault();event.stopImmediatePropagation();this.notice.textContent=this.pending?'Please wait for the pasted images to finish.':'Content exceeds 4 MB. Remove an image or shorten the content.';} },true);
      this.clipboard={dangerouslyPasteHTML: html => { this.root.innerHTML=clean(html).innerHTML;this.changed(); }};
    }
    rememberSelection() { const selection=window.getSelection();this.range=selection.rangeCount && this.root.contains(selection.anchorNode) ? selection.getRangeAt(0).cloneRange() : null; }
    focus() { this.root.focus(); }
    getText() { return this.root.innerText; }
    hasContent() { return Boolean(this.getText().trim() || this.root.querySelector('img[src]')); }
    on(name, handler) { if(name==='text-change') this.root.addEventListener('event-content-change',handler); }
    changed() { this.root.dispatchEvent(new Event('event-content-change')); }
    async readImages(files) {
      return Promise.all(files.map(file => new Promise((resolve,reject) => {
        if(!['image/png','image/jpeg','image/gif'].includes(file.type) || file.size>imageBytes) {reject(new Error('Use PNG, JPEG or GIF images smaller than 1 MB.'));return;}
        const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(new Error('Could not read this image. Try Insert image.'));reader.readAsDataURL(file);
      })));
    }
    async insertImages(files) {
      try {this.pending=true;const sources=await this.readImages(files);this.insert(sources.map(src=>'<img src="'+src+'" alt="">').join(''));}catch(error){this.notice.textContent=error.message;}finally{this.pending=false;}
    }
    insert(html) {
      if(new Blob([this.root.innerHTML,html]).size>maxBytes) throw new Error('Content exceeds 4 MB. Remove an image or paste a smaller section.');
      this.focus();const selection=window.getSelection();selection.removeAllRanges();
      if(this.range && this.root.contains(this.range.commonAncestorContainer)) selection.addRange(this.range);
      else {const range=document.createRange();range.selectNodeContents(this.root);range.collapse(false);selection.addRange(range);}
      document.execCommand('insertHTML',false,html);this.rememberSelection();this.changed();
    }
  }
  window.EventRichTextEditor=EventRichTextEditor;
})();
