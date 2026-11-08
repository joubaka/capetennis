<style>
  :root{--ct-loading-blue:#004177;--ct-loading-track:#dce5ef;--ct-loading-ink:#172e45;--ct-loading-muted:#536873;--ct-loading-paper:#fff;--ct-loading-backdrop:#172e4570;--ct-loading-radius:1rem;--ct-loading-shadow:0 1.5rem 5rem #172e4540}
  .ct-loading-spinner,.spinner-border{display:inline-block}
  .ct-loading-spinner,.spinner-border,.swal2-popup .swal2-loader{box-sizing:border-box;flex-shrink:0;width:2rem;height:2rem;border:.22rem solid var(--ct-loading-track);border-top-color:var(--ct-loading-blue);border-radius:50%;animation:ct-loading-spin .8s linear infinite;vertical-align:middle}
  .ct-loading-spinner--small,.spinner-border-sm{width:1rem;height:1rem;border-width:.15rem}
  .btn-primary .ct-loading-spinner,.btn-primary .spinner-border,.btn-secondary .ct-loading-spinner,.btn-secondary .spinner-border,.btn-success .ct-loading-spinner,.btn-success .spinner-border,.btn-danger .ct-loading-spinner,.btn-danger .spinner-border,.btn-dark .ct-loading-spinner,.btn-dark .spinner-border{border-color:#ffffff66;border-top-color:#fff}
  .ct-loading-inline{text-align:center;color:var(--ct-loading-muted);padding:1.5rem 0}.ct-loading-inline>p{margin:.5rem 0 0}
  #ct-loading-dialog,.ct-loading-panel,.swal2-popup[data-loading="true"]{box-sizing:border-box;width:min(26rem,calc(100vw - 2rem));max-height:calc(100dvh - 2rem);overflow:auto;border:0;border-radius:var(--ct-loading-radius);padding:clamp(1.5rem,5vw,2.5rem);background:var(--ct-loading-paper);color:var(--ct-loading-ink);text-align:center;box-shadow:var(--ct-loading-shadow)}
  #ct-loading-dialog:not([open]){display:none}
  #ct-loading-dialog::backdrop{background:var(--ct-loading-backdrop);backdrop-filter:blur(2px)}
  #ct-loading-dialog .ct-loading-spinner,.ct-loading-panel>.ct-loading-spinner{display:block}
  #ct-loading-dialog .ct-loading-spinner,.ct-loading-panel>.ct-loading-spinner,.swal2-popup[data-loading="true"] .swal2-loader{width:3rem;height:3rem;margin:0 auto 1.25rem;border-width:.3rem}
  #ct-loading-dialog h2,.ct-loading-panel h2,.swal2-popup[data-loading="true"] .swal2-title{font-size:1.35rem;color:var(--ct-loading-ink);margin:0 0 .75rem;padding:0}
  #ct-loading-dialog p,.ct-loading-panel p,.swal2-popup[data-loading="true"] .swal2-html-container{color:var(--ct-loading-muted);line-height:1.6;margin:0}
  #ct-loading-dialog button{margin-top:1.25rem;min-height:44px;color:var(--ct-loading-blue);border:1px solid #9ab4cf;border-radius:.5rem;background:var(--ct-loading-paper);padding:.6rem 1rem}
  #ct-loading-dialog button:focus-visible{outline:3px solid var(--ct-loading-blue);outline-offset:3px}
  #ct-loading-title:focus{outline:none}
  @keyframes ct-loading-spin{to{transform:rotate(360deg)}}
  @media(prefers-reduced-motion:reduce){.ct-loading-spinner,.spinner-border,.swal2-popup .swal2-loader{animation:none!important}}
  @media print{#ct-loading-dialog{display:none!important}}
</style>
<script src="<?php echo e(asset('assets/js/site-loading.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/site-loading.js'))); ?>"></script>
<?php /**PATH C:\wamp64\www\ct\resources\views/layouts/sections/loading.blade.php ENDPATH**/ ?>