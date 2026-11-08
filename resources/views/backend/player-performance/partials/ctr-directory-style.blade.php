<style>
    .ctr-directory { border:1px solid #dbe4eb; border-radius:.8rem; overflow:hidden; background:#fff; }
    .ctr-directory .ctr-hero { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.2rem 1.5rem;background:linear-gradient(135deg,#172e45,#0e5360);color:#fff; }
    .ctr-directory .ctr-hero h1 { color:#fff!important;margin:0 0 .35rem;font-size:clamp(1.4rem,2.5vw,1.85rem); }
    .ctr-directory .ctr-hero p { color:#e2f2f1;margin:0; }
    .ctr-directory .ctr-hero-mark { border:1px solid #82bfc3;border-radius:.65rem;padding:.65rem .85rem;color:#fff;font-weight:800;letter-spacing:.08em;font-size:1.2rem; }
    .ctr-directory .ctr-summary { display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem 1rem;padding:.8rem 1.5rem;border-bottom:1px solid #dbe4eb;background:#f4f7fa;font-size:.85rem; }
    .ctr-directory .ctr-content { padding:1.25rem 1.5rem; }
    .ctr-directory .ctr-section-title { margin:0 0 .35rem;font-size:1.125rem;font-weight:700;color:#172e45; }
    .ctr-directory .ctr-intro { color:#435970; }
    .ctr-directory .ctr-update-status { padding:.65rem .85rem;border:1px solid #bedce2;border-radius:.5rem;background:#eef8fa;color:#244f59; }
    .ctr-directory .ctr-filters { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem 1rem;padding:1rem;border:1px solid #dbe4eb;border-radius:.6rem;background:#fafcfd; }
    .ctr-directory .ctr-filters-all-ages { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .ctr-directory .ctr-field { min-width:0; }
    .ctr-directory .ctr-filters .form-label { margin-bottom:.3rem;color:#29425a;font-weight:600; }
    .ctr-directory .ctr-field-hint { margin:.3rem 0 0;color:#4d6277;font-size:.8rem; }
    .ctr-directory .ctr-search { grid-column:span 2; }
    .ctr-directory .ctr-evidence-filter { grid-column:1/-1; }
    .ctr-directory .ctr-evidence-filter label { min-height:44px; cursor:pointer; }
    .ctr-directory .ctr-filter-actions { grid-column:1/-1;display:flex;justify-content:space-between;align-items:start;gap:.5rem 1rem;border-top:1px solid #dbe4eb;padding-top:.65rem; }
    .ctr-directory .ctr-apply { margin-inline-start:auto;min-width:10rem; }
    .ctr-directory .ctr-sort-options { flex:1;min-width:0;max-width:24rem; }
    .ctr-directory .ctr-sort-options summary,.ctr-directory .ctr-rating-details summary,.ctr-directory .ctr-other-views summary { color:#175665;font-weight:600; }
    .ctr-directory .ctr-filters .form-control,.ctr-directory .ctr-filters .form-select,.ctr-directory .ctr-filters .btn { min-height:44px!important; }
    .ctr-directory .ctr-results-intro { border-bottom:1px solid #dbe4eb;padding:.35rem 0 .15rem; }
    .ctr-directory .ctr-result-summary { margin:0;color:#435970; }
    .ctr-directory .ctr-rating-details-body { max-width:76ch;color:#435970; }
    .ctr-directory .ctr-column-head,.ctr-directory .ctr-ranking-row { display:grid;grid-template-columns:3.25rem minmax(0,1fr) minmax(8rem,12rem) minmax(8rem,13rem);gap:.75rem;align-items:center; }
    .ctr-directory .ctr-column-head { padding:.65rem 1rem;border-radius:.5rem .5rem 0 0;background:#e9eef3;color:#465b70;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em; }
    .ctr-directory .ctr-ranking-row { padding:.65rem 1rem; }
    .ctr-directory .ctr-ranking-row:hover { background:#f6fafb; }
    .ctr-directory .ctr-place { color:#60778e;font-size:1.1rem;font-weight:700;font-variant-numeric:tabular-nums; }
    .ctr-directory .ctr-player { min-width:0;overflow-wrap:anywhere; }
    .ctr-directory .ctr-player>a { display:inline-flex;align-items:center;min-height:44px;font-weight:700;color:#172e45!important; }
    .ctr-directory .ctr-player p { margin:0;color:#4d6277;font-size:.8rem; }
    .ctr-directory .ctr-score { justify-self:start; }
    .ctr-directory .ctr-evidence { min-width:0;font-size:.8rem;color:#4d6277;overflow-wrap:anywhere; }
    .ctr-directory .ctr-evidence p { margin:0 0 .2rem; }
    .ctr-directory .ctr-row-detail { grid-column:2/-1;font-size:.75rem; }
    .ctr-directory .ctr-row-detail p { margin:0 0 .25rem; }
    .ctr-directory section>h2 { margin:1.25rem 0 .75rem; }
    .ctr-directory .ctr-results-section>h2 { scroll-margin-top:5rem;border-bottom:1px solid #dbe4eb;padding-bottom:.55rem;color:#172e45;font-weight:700; }
    .ctr-directory summary { cursor:pointer;min-height:44px;align-content:center; }
    .ctr-directory .ctr-gender-columns { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem; }
    .ctr-directory .ctr-gender-column { min-width:0; }
    .ctr-directory .ctr-gender-column[data-ctr-gender-column="unknown"] { grid-column:1/-1; }
    .ctr-directory .ctr-gender-columns.ctr-single-column { grid-template-columns:minmax(0,1fr); }
    .ctr-directory .ctr-gender-column .ctr-ranking-row { grid-template-columns:2rem minmax(0,1fr) auto;gap:.25rem .5rem;padding:.65rem .75rem; }
    .ctr-directory .ctr-gender-column .ctr-place { grid-column:1;grid-row:1; }
    .ctr-directory .ctr-gender-column .ctr-player { grid-column:2;grid-row:1; }
    .ctr-directory .ctr-gender-column .ctr-score { grid-column:3;grid-row:1; }
    .ctr-directory .ctr-gender-column .ctr-evidence { grid-column:2/-1; }
    .ctr-directory a:focus-visible,.ctr-directory summary:focus-visible { outline:2px solid #14796e;outline-offset:3px; }
    @media(max-width:991.98px) {
        .ctr-directory .ctr-filters { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media(max-width:767.98px) {
        .ctr-directory .ctr-gender-columns { grid-template-columns:minmax(0,1fr); }
        .ctr-directory .ctr-hero,.ctr-directory .ctr-content { padding:1rem; }
        .ctr-directory .ctr-summary { padding:.75rem 1rem; }
        .ctr-directory .ctr-column-head { display:none; }
        .ctr-directory .ctr-ranking-row { grid-template-columns:1.7rem minmax(0,1fr) auto;gap:.25rem .5rem;padding:.65rem .75rem; }
        .ctr-directory .ctr-score { grid-column:3;grid-row:1;max-width:7rem; }
        .ctr-directory .ctr-player { grid-column:2;grid-row:1; }
        .ctr-directory .ctr-evidence { grid-column:2/-1; }
        .ctr-directory .ctr-place { grid-column:1;grid-row:1; }
        .ctr-directory .ctr-hero-mark { font-size:.9rem;padding:.5rem; }
    }
    @media(max-width:575.98px) {
        .ctr-directory .ctr-field-mobile-wide { grid-column:1/-1; }
        .ctr-directory .ctr-filter-actions { flex-direction:column;align-items:stretch; }
        .ctr-directory .ctr-sort-options { max-width:none; }
        .ctr-directory .ctr-apply { margin:0;width:100%; }
        .ctr-directory .ctr-ranking-row,
        .ctr-directory .ctr-gender-column .ctr-ranking-row { grid-template-columns:1.7rem minmax(0,1fr);gap:.35rem .5rem; }
        .ctr-directory .ctr-player,
        .ctr-directory .ctr-gender-column .ctr-player { grid-column:2/-1;grid-row:1; }
        .ctr-directory .ctr-score,
        .ctr-directory .ctr-gender-column .ctr-score { grid-column:2;grid-row:2;max-width:none; }
        .ctr-directory .ctr-evidence,
        .ctr-directory .ctr-gender-column .ctr-evidence { grid-column:2/-1;grid-row:3; }
        .ctr-directory .ctr-row-detail { grid-column:2/-1;grid-row:4; }
    }
</style>
