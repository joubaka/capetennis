<style>
.operational-page :is(button,.btn,.page-link,.nav-link,summary,input:not([type=checkbox]):not([type=radio]),select) { min-height:44px !important; }
.operational-page :is(.btn-icon,.btn-close) { min-width:44px !important; }
.operational-page .operational-stepper button { min-width:44px; min-height:44px; }
.operational-page summary { cursor:pointer; align-content:center; }
.operational-page :focus-visible { outline:3px solid #117a72; outline-offset:2px; }
.operational-page .card-header.d-flex { flex-wrap:wrap; gap:.5rem; }
.operational-page :is(td,dd,.badge) { overflow-wrap:anywhere; }
.operational-page .table-responsive { max-width:100%; min-width:0; overflow-x:auto; }
.operational-page pre { white-space:pre-wrap; overflow-wrap:anywhere; max-width:100%; min-width:0; }
.operational-page [data-page-row][hidden] { display:none !important; }
@media(max-width:575px) {
  .operational-page .d-flex.justify-content-between { flex-wrap:wrap; gap:.75rem; }
  .operational-page .row > .col-6 { flex:0 0 100%; max-width:100%; }
  .operational-page .input-group { flex-wrap:wrap; }
  .operational-page .input-group > .form-control { min-width:0; }
  .operational-page .select2-container { max-width:100%; }
}
</style>
@isset($pageSearchLabel)
<div class="mb-3" data-page-search>
  <label class="form-label" for="operational-page-search">{{ $pageSearchLabel }}</label>
  <input type="search" id="operational-page-search" class="form-control" placeholder="Search the rows on this page">
  <p class="small text-muted mb-0 mt-1" role="status" aria-live="polite" data-page-search-status></p>
  <script>
  (() => {
    const controls = document.currentScript.parentElement;
    const scope = controls.closest('.operational-page');
    const search = controls.querySelector('input');
    const refresh = () => {
      const term = search.value.trim().toLocaleLowerCase();
      const rows = [...scope.querySelectorAll('[data-page-row]')];
      let visible = 0;
      rows.forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase().includes(term); if (!row.hidden) visible++; });
      controls.querySelector('[data-page-search-status]').textContent = `${visible} of ${rows.length} rows shown on this page. Downloads keep their existing scope.`;
    };
    search.addEventListener('input', refresh);
    document.addEventListener('DOMContentLoaded', refresh, { once:true });
  })();
  </script>
</div>
@endisset
