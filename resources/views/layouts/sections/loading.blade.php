<style>
  #ct-loading-dialog{box-sizing:border-box;width:min(26rem,calc(100vw - 2rem));max-height:calc(100dvh - 2rem);overflow:auto;border:0;border-radius:1rem;padding:clamp(1.5rem,5vw,2.5rem);background:#fff;color:#172e45;text-align:center;box-shadow:0 1.5rem 5rem #172e4540}
  #ct-loading-dialog:not([open]){display:none}
  #ct-loading-dialog::backdrop{background:#172e4570;backdrop-filter:blur(2px)}
  #ct-loading-dialog .ct-loading-spinner{display:block;width:3rem;height:3rem;margin:0 auto 1.25rem;border:.3rem solid #dce5ef;border-top-color:#004177;border-radius:50%;animation:ct-loading-spin .8s linear infinite}
  #ct-loading-dialog h2{font-size:1.35rem;color:#172e45;margin:0 0 .75rem}
  #ct-loading-dialog p{color:#536873;line-height:1.6;margin:0}
  #ct-loading-dialog button{margin-top:1.25rem;min-height:44px;color:#004177;border:1px solid #9ab4cf;border-radius:.5rem;background:#fff;padding:.6rem 1rem}
  #ct-loading-dialog :focus-visible{outline:3px solid #b56514;outline-offset:3px}
  @keyframes ct-loading-spin{to{transform:rotate(360deg)}}
  @media(prefers-reduced-motion:reduce){#ct-loading-dialog .ct-loading-spinner{animation:none}}
  @media print{#ct-loading-dialog{display:none!important}}
</style>
<script src="{{ asset('assets/js/site-loading.js') }}?v={{ filemtime(public_path('assets/js/site-loading.js')) }}"></script>
