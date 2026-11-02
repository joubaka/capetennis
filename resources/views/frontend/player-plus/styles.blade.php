<style>
  .pp-journey { --plus-navy:#172e45;--plus-blue:#004177;--plus-orange:#ff9f43;max-width:1120px;color:#243e50;overflow-wrap:anywhere; }
  .pp-journey .pp-visual-hero { position:relative;overflow:hidden;background:linear-gradient(115deg,#172e45,#004177);border-radius:18px;padding:clamp(1.25rem,4vw,2.5rem);color:#fff;margin-bottom:1.5rem; }
  .pp-journey .pp-visual-hero::after { content:'';position:absolute;width:220px;height:220px;border:1px solid #ffffff20;border-radius:50%;right:-80px;top:-110px;pointer-events:none; }
  .pp-journey .pp-visual-hero h1,.pp-journey .pp-visual-hero h2 { color:#fff;font-size:clamp(1.6rem,4vw,2.5rem);letter-spacing:-.035em;line-height:1.2; }
  .pp-journey .pp-visual-hero p { color:#dee9f5;max-width:65ch;line-height:1.7; }
  .pp-journey .pp-visual-eyebrow { display:block;color:#ff9f43;font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.8rem; }
  .pp-journey .pp-hero-icon { display:grid;place-items:center;width:3rem;height:3rem;background:#ffffff15;border:1px solid #ffffff30;border-radius:12px;color:#ff9f43;font-size:1.6rem;margin-bottom:1rem; }
  .pp-journey .btn { min-height:44px;white-space:normal; }
  .pp-journey .btn-primary { background:#004177;border-color:#004177;color:#fff; }
  .pp-journey .btn-primary:hover { background:#00345f;border-color:#00345f; }
  .pp-journey .btn-outline-primary { color:#004177;border-color:#9ab4cf; }
  .pp-journey .btn-outline-primary:hover { background:#eef4fa;color:#00345f;border-color:#004177; }
  .pp-journey :is(a,button,summary,input,select):focus-visible { outline:3px solid #b56514;outline-offset:3px; }
  #player-plus-page.pp-journey .plus-page-intro { background:linear-gradient(115deg,#172e45,#004177); }
  #player-plus-page.pp-journey .plus-page-intro h2 { color:#fff; }
  #player-plus-page.pp-journey .plus-page-intro p { color:#dee9f5; }
  #player-plus-page.pp-journey .plus-page-intro .btn { background:#ff9f43;border-color:#ff9f43;color:#172e45;font-weight:600; }
  #player-plus-page.pp-journey .plus-page-intro .btn:hover { background:#f38d2d;border-color:#f38d2d; }
  .pp-journey.pp-player .pp-hero,.pp-journey.pp-checkout .pp-checkout-hero { background:linear-gradient(115deg,#172e45,#004177); }
  .pp-journey.pp-player .pp-eyebrow,.pp-journey.pp-checkout .pp-checkout-eyebrow { color:#ff9f43; }
  .pp-journey.pp-player .pp-price small { color:#ff9f43; }
  .pp-journey.pp-player .pp-purchase .btn-primary,.pp-journey.pp-checkout .pp-payment-card .btn-primary { background:var(--plus-orange);border-color:var(--plus-orange);color:var(--plus-navy);font-weight:600; }
  .pp-journey.pp-player .pp-purchase .btn-primary:hover,.pp-journey.pp-checkout .pp-payment-card .btn-primary:hover { background:#f38d2d;border-color:#f38d2d;color:var(--plus-navy); }
  .pp-journey.pp-player .pp-benefits>ul>li:before,.pp-journey.pp-checkout .pp-checkout-benefits>ul>li:before { display:none; }
  .pp-journey.pp-player .pp-benefits>ul>li,.pp-journey.pp-checkout .pp-checkout-benefits>ul>li { padding:1.1rem; }
  .pp-journey .pp-order-list { gap:.8rem; }
  .pp-journey .pp-order-row { display:flex;align-items:center;gap:1rem;border:1px solid #dce5ef!important;border-radius:14px!important;padding:1.25rem!important;background:#fff; }
  .pp-journey .pp-order-row:hover { background:#eef4fa;border-color:#9ab4cf!important; }
  .pp-journey .pp-order-icon { display:grid;place-items:center;flex:0 0 44px;height:44px;background:#eef4fa;color:#004177;border-radius:12px;font-size:1.4rem; }
  .pp-journey .pp-order-copy { flex:1;min-width:0;line-height:1.7; }
  .pp-journey .pp-order-copy strong { color:#172e45; }
  .pp-journey .pp-order-copy span { font-size:.85rem;color:#536873; }
  .pp-journey .pp-terms-body { background:#fff;border:1px solid #dce5ef;border-radius:16px;padding:clamp(1.25rem,3vw,2rem);line-height:1.8; }
  .pp-journey .pp-terms-body h2 { color:#172e45;font-size:1.15rem;border-top:1px solid #dce5ef;padding-top:1.25rem;margin-top:1.5rem; }
  .pp-journey .pp-terms-body h2 .ti { color:#004177;margin-right:.5rem; }
  @media(max-width:575.98px) { .pp-journey .pp-order-row { align-items:flex-start;gap:.75rem;padding:1rem!important; }.pp-journey .pp-order-icon{flex-basis:36px;height:36px;font-size:1.2rem;} }
</style>
