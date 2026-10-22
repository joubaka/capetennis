<style>
.cost-editor-row { border:1px solid var(--bs-border-color, #ddd); border-radius:.5rem; padding:.75rem; margin-bottom:.65rem; background:var(--bs-body-bg, #fff); }
.cost-editor-grid { display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:.5rem .75rem; align-items:end; }
.cost-editor-grid > * { min-width:0; }
.cost-span-2 { grid-column:span 2; }.cost-span-3 { grid-column:span 3; }.cost-span-4 { grid-column:span 4; }.cost-span-6 { grid-column:span 6; }
.cost-editor-row .form-label { font-size:.8rem; margin-bottom:.2rem; }.cost-editor-row .form-control,.cost-editor-row .form-select { min-height:40px; }
.cost-editor-actions { display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-top:.5rem; flex-wrap:wrap; }
.cost-editor-row summary { min-height:40px; align-content:center; cursor:pointer; }
.cost-editor-save { position:sticky; bottom:0; background:var(--bs-body-bg,#fff); border-top:1px solid var(--bs-border-color,#ddd); padding:1rem 0; z-index:5; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:.75rem; }
@media(max-width:767.98px) { .cost-editor-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.cost-span-2,.cost-span-3 { grid-column:span 1; }.cost-span-4,.cost-span-6 { grid-column:span 2; } }
</style>
