(function (window, document) {
  'use strict';
  var host = document.querySelector('[data-draw-guide-modal]');
  if (!host) return;
  var body = host.querySelector('[data-draw-guide-body]');
  var version = 0, request = null, submitting = false, currentUrl = '', returnFocus=null, changed=false;
  function localUrl(value) {
    var url = new URL(value, window.location.href);
    if (url.origin !== window.location.origin) throw new Error('The setup link must belong to this site.');
    return url.href;
  }
  function message(text, retry) {
    body.replaceChildren();
    var status = document.createElement('div'); status.className = 'alert alert-danger'; status.setAttribute('role','alert'); status.textContent = text; body.appendChild(status);
    if (retry) { var button = document.createElement('button'); button.type='button'; button.className='btn btn-primary'; button.dataset.drawGuideRetry=''; button.textContent='Try again'; body.appendChild(button); }
  }
  async function json(response) {
    if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('Your session may have expired. Refresh the page and sign in before continuing.');
    var data = await response.json();
    if (!response.ok) {
      var error = new Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Could not load draw setup.');
      error.fields = data.errors || {}; throw error;
    }
    return data;
  }
  function closeCreateModal() {
    var active = document.querySelector('.modal.show:not([data-draw-guide-modal])');
    if (!active) return Promise.resolve();
    return new Promise(function (resolve) {
      active.addEventListener('hidden.bs.modal', resolve, {once:true});
      window.bootstrap.Modal.getOrCreateInstance(active).hide();
    });
  }
  async function open(value) {
    if (submitting) return;
    var url;
    try { url = localUrl(value); } catch (error) { return; }
    var own = ++version; currentUrl = url;
    if(!host.contains(document.activeElement))returnFocus=document.activeElement;
    if (request) request.abort();
    request = new AbortController();
    await closeCreateModal();
    if (own !== version) return;
    window.bootstrap.Modal.getOrCreateInstance(host).show();
    body.replaceChildren(); var loading = document.createElement('p'); loading.setAttribute('role','status'); loading.textContent='Loading draw setup…'; body.appendChild(loading);
    try {
      var response = await window.fetch(url, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin', signal:request.signal});
      var data = await json(response);
      if (own !== version) return;
      if (typeof data.html !== 'string' || !data.html.includes('data-draw-guide') || /<html[\s>]|<script[\s>]/i.test(data.html)) throw new Error('Could not load the setup panel. Refresh the page and try again.');
      body.innerHTML = data.html;
      var focus=body.querySelector('h1,h2,select,input');if(focus){if(/^H[12]$/.test(focus.tagName))focus.setAttribute('tabindex','-1');focus.focus();}
    } catch (error) { if (own === version && error.name !== 'AbortError') message(error.message || 'Could not load draw setup. Check your connection and try again.',true); }
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest('[data-draw-guide-open]');
    if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) { event.preventDefault(); open(link.href); return; }
    if (event.target.closest('[data-draw-guide-retry]')) open(currentUrl);
  });
  document.addEventListener('submit', async function (event) {
    var form = event.target.closest('[data-draw-guide-form]');
    if (!form || !host.contains(form)) return;
    event.preventDefault();
    if (submitting) return;
    var action;
    try { action=localUrl(form.action); } catch (error) { return; }
    var payload=new FormData(form), controls=Array.from(form.querySelectorAll('button,input,select'));
    var disabled=controls.map(function(control){return control.disabled;});
    var feedback=form.querySelector('[data-draw-guide-error]');
    if (!feedback) { feedback=document.createElement('div');feedback.dataset.drawGuideError='';feedback.className='alert alert-danger';feedback.setAttribute('role','alert');form.prepend(feedback); }
    feedback.hidden=true; submitting=true;form.setAttribute('aria-busy','true');controls.forEach(function(control){control.disabled=true;control.removeAttribute('aria-invalid');});
    var nextUrl='';
    try {
      var response=await window.fetch(action,{method:'POST',body:payload,headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
      var data=await json(response);
      if (typeof data.guide_url !== 'string') throw new Error('The save could not be confirmed. Check the draw before trying again.');
      nextUrl=localUrl(data.guide_url);
      changed=true;
    } catch(error) {
      feedback.textContent=error.message || 'The save could not be confirmed. Check your connection and the draw before trying again.';feedback.hidden=false;
      controls.forEach(function(control){if(error.fields && error.fields[control.name])control.setAttribute('aria-invalid','true');});
    } finally {
      submitting=false;form.setAttribute('aria-busy','false');controls.forEach(function(control,index){control.disabled=disabled[index];});
    }
    if(nextUrl) open(nextUrl);
  });
  host.addEventListener('hide.bs.modal',function(event){if(submitting)event.preventDefault();});
  host.addEventListener('hidden.bs.modal',function(){version++;if(request)request.abort();if(changed && document.querySelector('[data-draw-hub]')){window.location.reload();return;}if(returnFocus && returnFocus.isConnected)returnFocus.focus();});
  function appendChoices(target, guides) {
    guides.forEach(function(guide){
      var url;try{url=localUrl(guide.url);}catch(error){return;}
      var link=document.createElement('a');link.href=url;link.className='btn btn-outline-primary m-1';link.dataset.drawGuideOpen='';link.textContent='Continue '+guide.name;target.appendChild(link);
    });
  }
  function created(guides) {
    changed=true;
    var summary=document.querySelector('[data-draw-guide-created]');
    if(!summary){summary=document.createElement('aside');summary.dataset.drawGuideCreated='';summary.className='alert alert-success m-3';summary.setAttribute('role','status');(document.querySelector('.content-wrapper') || document.body).prepend(summary);}
    var message=document.createElement('p');message.textContent='Draw saved. Continue setup below; closing setup refreshes your draws.';summary.appendChild(message);appendChoices(summary,guides);
  }
  async function choose(guides) {
    if(submitting)return;var own=++version;if(request)request.abort();await closeCreateModal();if(own!==version)return;
    window.bootstrap.Modal.getOrCreateInstance(host).show();body.replaceChildren();var heading=document.createElement('p');heading.textContent='Draws saved. Choose a draw to continue its setup.';body.appendChild(heading);appendChoices(body,guides);
  }
  window.DrawSetupGuide={open:open,created:created,choose:choose};
})(window,document);
