@extends('layouts.backend')

@section('title', 'Site Settings')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
@endsection

@section('content')
<div class="operational-page site-settings-page">
@include('backend.partials.operational-controls')
<style>
  .site-settings-page { --settings-scroll-offset:160px; }
  .site-settings-page .settings-shell { max-width:1200px; margin-inline:auto; padding-top:.25rem; }
  .site-settings-page .settings-header { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; }
  .site-settings-page .settings-header h1 { font-size:clamp(1.65rem,3vw,2rem); letter-spacing:-.035em; margin:0 0 .5rem; }
  .site-settings-page .settings-header p { max-width:68ch; line-height:1.6; margin:0; color:var(--ct-muted,#66788a); }
  .site-settings-page .settings-header .settings-dashboard-link { display:inline-flex; align-items:center; gap:.4rem; min-height:44px; }
  .site-settings-page .settings-nav { display:flex; flex-wrap:wrap; gap:.5rem; padding:1rem; margin-bottom:1.5rem; border:1px solid var(--ct-border,#e4eaf0); border-radius:12px; background:#fff; }
  .site-settings-page .settings-nav a { display:inline-flex; align-items:center; gap:.45rem; min-height:44px; padding:.6rem .85rem; border-radius:7px; color:var(--ct-ink,#172e45); background:var(--ct-soft,#f2f5f8); font-size:.875rem; }
  .site-settings-page .settings-nav a:hover { background:#eaf4f2; color:#14796e; }
  .site-settings-page .settings-nav a[aria-selected="true"] { background:#172e45; color:#fff; }
  .site-settings-page .settings-nav a:focus-visible { outline:3px solid #14796e; outline-offset:3px; }
  .site-settings-page [data-settings-panel][hidden] { display:none!important; }
  .site-settings-page :is(#settings-plus,#settings-fees,#settings-methods,#settings-agreements,#settings-content) { scroll-margin-top:var(--settings-scroll-offset); }
  .site-settings-page .settings-card { border:1px solid var(--ct-border,#e4eaf0); box-shadow:none; border-radius:14px; overflow:hidden; }
  .site-settings-page .settings-plus-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1.5rem; flex-wrap:wrap; padding:1.5rem; border-bottom:1px solid var(--ct-border,#e4eaf0); }
  .site-settings-page .settings-plus-header p { max-width:62ch; margin-bottom:0; line-height:1.6; }
  .site-settings-page .settings-plus-header .form-switch { padding-left:2.75rem; margin-bottom:.25rem; }
  .site-settings-page .settings-plan-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1.5rem; padding:1.5rem; }
  .site-settings-page .settings-plan-panel { width:100%; min-width:0; border:1px solid var(--ct-border,#e4eaf0); background:#fbfcfd; border-radius:12px; padding:1.25rem; }
  .site-settings-page .settings-plan-panel h3 { font-size:1.05rem; margin-bottom:.75rem; }
  .site-settings-page .settings-plan-panel p { line-height:1.65; }
  .site-settings-page .settings-plan-panel [data-plus-monthly-settings] { margin-top:0!important; padding-top:0!important; border-top:0!important; }
  .site-settings-page .settings-price-actions { display:flex; flex-wrap:wrap; align-items:flex-start; gap:.65rem; }
  .site-settings-page .settings-plan-panel :is(#sa-plus-price,#sa-plus-monthly-price) { max-width:11rem!important; min-width:0; flex:1 1 9rem; }
  .site-settings-page .settings-plus-footer { padding:0 1.5rem 1.25rem; }
  .site-settings-page .settings-plus-footer a { display:inline-flex; align-items:center; gap:.4rem; min-height:44px; }
  .site-settings-page .card-header :is(small,p) { display:block; max-width:85ch; margin-top:.5rem; line-height:1.6; }
  .site-settings-page .card-body .form-label { line-height:1.45; }
  .site-settings-page .settings-methods-table { min-width:540px; }
  .site-settings-page .settings-methods-table .method-pct-input { min-width:5rem; }
  .site-settings-page .settings-formula code { display:block; margin-top:.65rem; white-space:normal; overflow-wrap:anywhere; line-height:1.8; }
  .site-settings-page .settings-save-bar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; padding:1.25rem 1.5rem; border:1px solid var(--ct-border,#e4eaf0); border-radius:12px; background:#fff; margin:0 0 1.5rem; }
  .site-settings-page .settings-save-bar p { margin:0; color:var(--ct-muted,#66788a); font-size:.875rem; line-height:1.6; }
  @media(max-width:1199.98px) { .site-settings-page { --settings-scroll-offset:100px; } }
  @media(max-width:767.98px) {
    .site-settings-page .settings-plan-grid { grid-template-columns:1fr; padding:1rem; gap:1rem; }
    .site-settings-page .settings-plus-header { padding:1.25rem; gap:1rem; }
    .site-settings-page .settings-plus-footer { padding:0 1.25rem 1rem; }
    .site-settings-page .settings-plan-panel { padding:1rem; }
    .site-settings-page .settings-save-bar { padding:1rem; }
  }
  @media(max-width:575.98px) {
    .site-settings-page .settings-nav { padding:.75rem; gap:.4rem; }
    .site-settings-page .settings-nav a { flex:1 1 auto; justify-content:center; }
    .site-settings-page .settings-plan-panel :is(#sa-plus-price,#sa-plus-monthly-price) { max-width:none!important; flex-basis:100%; }
    .site-settings-page .settings-plan-panel :is(#sa-plus-price-save,#sa-plus-monthly-save), .site-settings-page .settings-save-bar .btn { width:100%; }
  }
</style>
<div class="container-xl settings-shell">

  <header class="settings-header">
    <div>
      <p class="ct-eyebrow">Super Admin</p>
      <h1>Site settings</h1>
      <p>Manage Player Plus plans, payment fees and the requirements shown to players.</p>
    </div>
    <div class="d-flex flex-wrap gap-3">
      <a href="{{ route('superadmin.audit.index', ['category' => 'business', 'action' => 'settings.updated']) }}"
         class="settings-dashboard-link"><i class="ti ti-history" aria-hidden="true"></i> Settings change history</a>
      <a href="{{ route('backend.superadmin.index') }}"
         class="settings-dashboard-link"><i class="ti ti-layout-dashboard" aria-hidden="true"></i> Super Admin dashboard</a>
    </div>
  </header>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" aria-label="Close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <nav class="settings-nav" aria-label="Settings sections">
    <a href="#settings-plus"><i class="ti ti-star" aria-hidden="true"></i> Player Plus</a>
    <a href="#settings-fees">Default fees</a>
    <a href="#settings-methods">Payment methods</a>
    <a href="#settings-agreements">Player requirements</a>
    <a href="#settings-content">Published content</a>
  </nav>
  <form action="{{ route('settings.store') }}" method="POST">
    @csrf

    <div data-settings-panel="settings-plus">
    <section class="card settings-card mb-4" id="settings-plus" aria-labelledby="plus-settings-heading">
      <div class="settings-plus-header">
        <div>
          <h2 class="h4 mb-2" id="plus-settings-heading">Player Plus</h2>
          <p class="small text-muted">New plans renew automatically until cancelled. Existing paid terms and agreed subscription prices stay unchanged.</p>
        </div>
        <div>
          <input type="hidden" name="player_rating_membership_enabled" value="0">
          <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="sa-plus-sales" name="player_rating_membership_enabled" value="1" @checked(($generalSettings['player_rating_membership_enabled'] ?? '0') === '1')>
            <span class="form-check-label">Accept new purchases</span>
          </label>
          <p class="small text-muted mb-0">Saves immediately. Pausing sales keeps paid access until expiry.</p>
        </div>
      </div>
      <div class="settings-plan-grid">
        <div class="col-md-6 settings-plan-panel">
          <h3>Annual plan</h3>
          <p class="small text-muted">New annual purchases renew automatically at the agreed price until cancelled. Existing once-off annual orders keep their original conditions.</p>
          <label class="form-label" for="sa-plus-price">Annual price per player (R)</label>
          <div class="settings-price-actions">
            <input class="form-control" style="max-width:12rem;min-height:44px" id="sa-plus-price" name="player_plus_annual_price" type="number" min="{{ \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::MIN_PRICE_CENTS) }}" max="{{ \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::MAX_PRICE_CENTS) }}" step="0.01" value="{{ \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::priceCents()) }}" required>
            <button class="btn btn-outline-primary" type="button" id="sa-plus-price-save" style="min-height:44px">Save annual price</button>
          </div>
          <p class="small text-muted mt-2 mb-0">Applies to new checkouts only. Existing checkout prices and purchased terms remain unchanged.</p>
          <p id="sa-plus-price-status" class="small mt-2" role="status"></p>
        </div>
        <div class="col-md-6 settings-plan-panel">
          @include('backend.settings.partials.player-plus-monthly', ['showAnnualContext' => false])
        </div>
      </div>
      <div class="settings-plus-footer"><a href="{{ route('superadmin.player-plus.index') }}"><i class="ti ti-receipt" aria-hidden="true"></i> Review Plus payment issues</a></div>
    </section>
    </div>

    {{-- ===== DEFAULT / GLOBAL SETTINGS ===== --}}
    <div data-settings-panel="settings-fees">
    <div class="card mb-4">
      <div class="card-header">
        <h5 id="settings-fees" class="mb-0"><i class="ti ti-credit-card me-1"></i> Default PayFast Fee Settings</h5>
        <small class="text-muted">
          These defaults apply when the payment method is unknown. The negotiated discount from PayFast benefits Cape Tennis – the event director is charged at the rates set below.
        </small>
      </div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-4">
            <label class="form-label" for="payfast_fee_percentage">Default Fee Percentage (%)</label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" max="100" class="form-control"
                id="payfast_fee_percentage" name="payfast_fee_percentage"
                value="{{ old('payfast_fee_percentage', $payfastSettings['payfast_fee_percentage']->value ?? '3.2') }}">
              <span class="input-group-text">%</span>
            </div>
            <small class="text-muted">Fallback percentage when payment method is not detected.</small>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="payfast_fee_flat">Flat Fee per Transaction (R)</label>
            <div class="input-group">
              <span class="input-group-text">R</span>
              <input type="number" step="0.01" min="0" class="form-control"
                id="payfast_fee_flat" name="payfast_fee_flat"
                value="{{ old('payfast_fee_flat', $payfastSettings['payfast_fee_flat']->value ?? '2.00') }}">
            </div>
            <small class="text-muted">Applied to all payment methods.</small>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="payfast_vat_rate">VAT Rate (%)</label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" max="100" class="form-control"
                id="payfast_vat_rate" name="payfast_vat_rate"
                value="{{ old('payfast_vat_rate', $payfastSettings['payfast_vat_rate']->value ?? '14') }}">
              <span class="input-group-text">%</span>
            </div>
            <small class="text-muted">VAT applied on top of the fee.</small>
          </div>

        </div>
      </div>
    </div>

    {{-- ===== PER PAYMENT METHOD ===== --}}
    </div>
    <div data-settings-panel="settings-methods">
    <div class="card mb-4">
      <div class="card-header">
        <h5 id="settings-methods" class="mb-0"><i class="ti ti-list me-1"></i> Fee Percentage per Payment Method</h5>
        <small class="text-muted">
          Set the percentage charged to the event director for each payment type. The flat fee and VAT above apply to all methods.
        </small>
      </div>
      <p class="small text-muted px-4 pt-3 mb-2 d-md-none">Scroll across to view all payment-method columns.</p>
      <div class="card-body p-0 table-responsive" tabindex="0" role="region" aria-labelledby="settings-methods">
        <table class="table table-striped mb-0 settings-methods-table">
          <thead class="table-light">
            <tr>
              <th style="width:200px;">Payment Method</th>
              <th style="width:180px;">Fee Percentage</th>
              <th>Example Fee on R200</th>
            </tr>
          </thead>
          <tbody>
            @foreach($paymentMethods as $methodKey => $methodLabel)
              @php
                $settingKey = "payfast_fee_pct_{$methodKey}";
                $currentPct = old($settingKey, $payfastSettings[$settingKey]->value ?? '3.20');
              @endphp
              <tr>
                <td class="align-middle fw-semibold">{{ $methodLabel }}</td>
                <td>
                  <div class="input-group input-group-sm">
                    <input type="number" step="0.01" min="0" max="100"
                      class="form-control method-pct-input" aria-label="Fee percentage for {{ $methodLabel }}"
                      name="{{ $settingKey }}"
                      data-method="{{ $methodKey }}"
                      value="{{ $currentPct }}">
                    <span class="input-group-text">%</span>
                  </div>
                </td>
                <td class="align-middle text-muted">
                  R <span class="method-preview" data-method="{{ $methodKey }}">—</span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- ===== FORMULA PREVIEW ===== --}}
    <div class="card mb-4 settings-formula">
      <div class="card-body">
        <strong>Fee Formula:</strong>
        <code>((amount × percentage%) + R<span id="previewFlat">{{ $payfastSettings['payfast_fee_flat']->value ?? '2.00' }}</span>) × (1 + <span id="previewVat">{{ $payfastSettings['payfast_vat_rate']->value ?? '14' }}</span>%)</code>
      </div>
    </div>

    {{-- ===== CODE OF CONDUCT & TERMS TOGGLES ===== --}}
    </div>
    <div data-settings-panel="settings-agreements">
    <div class="card mb-4">
      <div class="card-header">
        <h5 id="settings-agreements" class="mb-0"><i class="ti ti-file-check me-1"></i> Code of Conduct & Terms</h5>
        <small class="text-muted">
          Enable or disable the Code of Conduct and Terms requirements site-wide. When enabled, players must accept these before registering.
        </small>
      </div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-6">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <label class="form-label mb-0" for="require_code_of_conduct">Require Code of Conduct</label>
                <br><small class="text-muted">Players must accept the Code of Conduct.</small>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="require_code_of_conduct" name="require_code_of_conduct" value="1"
                  {{ old('require_code_of_conduct', $generalSettings['require_code_of_conduct'] ?? '0') == '1' ? 'checked' : '' }}>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <label class="form-label mb-0" for="require_terms">Require Terms & Conditions</label>
                <br><small class="text-muted">Players must accept the Terms & Conditions.</small>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="require_terms" name="require_terms" value="1"
                  {{ old('require_terms', $generalSettings['require_terms'] ?? '0') == '1' ? 'checked' : '' }}>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <label class="form-label mb-0" for="require_profile_update">Require Profile Update on Login</label>
                <br><small class="text-muted">Players must update their profile details when logging in (if profile is incomplete or outdated).</small>
              </div>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="require_profile_update" name="require_profile_update" value="1"
                  {{ old('require_profile_update', $generalSettings['require_profile_update'] ?? '1') == '1' ? 'checked' : '' }}>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    {{-- ===== CODE OF CONDUCT CONTENT ===== --}}
    </div>
    <div data-settings-panel="settings-content">
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 id="settings-content" class="mb-0"><i class="ti ti-file-text me-1"></i> Code of Conduct Content</h5>
          <small class="text-muted">Edit the Code of Conduct text shown to players. HTML formatting is supported.</small>
        </div>
        <button type="button" class="btn btn-sm btn-primary save-content-btn" data-key="code_of_conduct_content" data-editor="coc-editor" data-textarea="code_of_conduct_content_input">
          <i class="ti ti-device-floppy me-1"></i> Save
        </button>
      </div>
      <div class="card-body">
        <div class="quill-wrapper border rounded" style="min-height:220px;">
          <div id="coc-editor" style="min-height:200px;"></div>
        </div>
        <textarea name="code_of_conduct_content" id="code_of_conduct_content_input" class="d-none">{{ old('code_of_conduct_content', $generalSettings['code_of_conduct_content'] ?? '') }}</textarea>
      </div>
    </div>

    {{-- ===== TERMS & CONDITIONS CONTENT ===== --}}
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="mb-0"><i class="ti ti-file-certificate me-1"></i> Terms &amp; Conditions Content</h5>
          <small class="text-muted">Edit the Terms &amp; Conditions text shown to players. HTML formatting is supported.</small>
        </div>
        <button type="button" class="btn btn-sm btn-primary save-content-btn" data-key="terms_conditions_content" data-editor="terms-editor" data-textarea="terms_conditions_content_input">
          <i class="ti ti-device-floppy me-1"></i> Save
        </button>
      </div>
      <div class="card-body">
        <div class="quill-wrapper border rounded" style="min-height:220px;">
          <div id="terms-editor" style="min-height:200px;"></div>
        </div>
        <textarea name="terms_conditions_content" id="terms_conditions_content_input" class="d-none">{{ old('terms_conditions_content', $generalSettings['terms_conditions_content'] ?? '') }}</textarea>
      </div>
    </div>

    </div>

    <div class="settings-save-bar">
      <div><strong>Save page settings</strong><p>Saves annual pricing, fees, player requirements and published content. Use the separate button above for monthly settings.</p></div>
      <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i> Save Settings
      </button>
    </div>

  </form>

</div>

</div>
@endsection

@section('page-script')
<script>
  // Enhance section links into tabs; without JavaScript every section remains available.
  const settingsNav = document.querySelector('.settings-nav');
  const settingsTabs = Array.from(settingsNav.querySelectorAll('a'));
  const settingsPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));
  settingsNav.setAttribute('role', 'tablist');
  settingsTabs.forEach(function(tab, index) {
    const key = tab.hash.slice(1);
    const panel = settingsPanels.find(function(item) { return item.dataset.settingsPanel === key; });
    tab.id = key + '-tab';
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', key + '-panel');
    panel.id = key + '-panel';
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', tab.id);
    panel.tabIndex = 0;
    tab.addEventListener('click', function(event) {
      event.preventDefault();
      activateSettingsTab(key);
      history.replaceState(null, '', tab.hash);
    });
    tab.addEventListener('keydown', function(event) {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % settingsTabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + settingsTabs.length) % settingsTabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = settingsTabs.length - 1;
      if (next === undefined) return;
      event.preventDefault();
      settingsTabs[next].click();
      settingsTabs[next].focus();
    });
  });
  function activateSettingsTab(key) {
    if (!settingsPanels.some(function(panel) { return panel.dataset.settingsPanel === key; })) key = 'settings-plus';
    settingsPanels.forEach(function(panel) { panel.hidden = panel.dataset.settingsPanel !== key; });
    settingsTabs.forEach(function(tab) {
      const active = tab.hash === '#' + key;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
    });
  }
  activateSettingsTab(location.hash.slice(1));
  window.addEventListener('hashchange', function() { activateSettingsTab(location.hash.slice(1)); });
  // Reveal a hidden field before native validation tries to focus it.
  document.querySelector('.settings-shell form').addEventListener('invalid', function(event) {
    const firstInvalid = event.currentTarget.querySelector(':invalid') || event.target;
    const panel = firstInvalid.closest('[data-settings-panel]');
    if (panel) activateSettingsTab(panel.dataset.settingsPanel);
  }, true);

  function calcFee(pct, flat, vat, amount) {
    return ((amount * pct / 100) + flat) * (1 + vat / 100);
  }

  function updateAllPreviews() {
    const flat = parseFloat(document.getElementById('payfast_fee_flat').value) || 0;
    const vat  = parseFloat(document.getElementById('payfast_vat_rate').value) || 0;

    document.getElementById('previewFlat').textContent = flat.toFixed(2);
    document.getElementById('previewVat').textContent  = vat;

    document.querySelectorAll('.method-pct-input').forEach(function(input) {
      const method = input.dataset.method;
      const pct    = parseFloat(input.value) || 0;
      const fee    = calcFee(pct, flat, vat, 200);
      document.querySelector('.method-preview[data-method="' + method + '"]').textContent = fee.toFixed(2);
    });
  }

  document.querySelectorAll('.method-pct-input').forEach(function(el) {
    el.addEventListener('input', updateAllPreviews);
  });
  document.getElementById('payfast_fee_flat').addEventListener('input', updateAllPreviews);
  document.getElementById('payfast_vat_rate').addEventListener('input', updateAllPreviews);

  updateAllPreviews();

  // ── Quill editors for Code of Conduct & Terms content ──────────
  var quillToolbar = [
    [{ 'header': [1, 2, 3, false] }],
    ['bold', 'italic', 'underline'],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    ['link'],
    ['clean']
  ];

  var cocEditor = new Quill('#coc-editor', { theme: 'snow', modules: { toolbar: quillToolbar } });
  var cocTextarea = document.getElementById('code_of_conduct_content_input');
  if (cocTextarea.value) { cocEditor.root.innerHTML = cocTextarea.value; }
  cocEditor.on('text-change', function() { cocTextarea.value = cocEditor.root.innerHTML; });

  var termsEditor = new Quill('#terms-editor', { theme: 'snow', modules: { toolbar: quillToolbar } });
  var termsTextarea = document.getElementById('terms_conditions_content_input');
  if (termsTextarea.value) { termsEditor.root.innerHTML = termsTextarea.value; }
  termsEditor.on('text-change', function() { termsTextarea.value = termsEditor.root.innerHTML; });

  // ── Save content via AJAX ──────────────────────────────────────
  var contentSaveUrl = '{{ route('settings.store.content') }}';
  var csrfToken = document.querySelector('meta[name="csrf-token"]') ?
    document.querySelector('meta[name="csrf-token"]').getAttribute('content') :
    document.querySelector('input[name="_token"]').value;

  $('#sa-plus-price-save').on('click', function () {
    var button = $(this);
    var input = document.getElementById('sa-plus-price');
    if (!input.reportValidity()) return;
    button.prop('disabled', true);
    $.ajax({url: '{{ route('settings.player-plus-price') }}', method: 'POST',
      data: {_token: csrfToken, player_plus_annual_price: input.value},
      success: function (response) { $('#sa-plus-price-status').text(response.message); },
      error: function (xhr) { $('#sa-plus-price-status').text(xhr.responseJSON?.errors?.player_plus_annual_price?.[0] || 'The annual price could not be saved.'); },
      complete: function () { button.prop('disabled', false); }
    });
  });

  $('#sa-plus-sales').on('change', function () {
    var input = $(this);
    input.prop('disabled', true);
    $.ajax({url: '{{ route('settings.store.single') }}', method: 'POST',
      data: {_token: csrfToken, key: 'player_rating_membership_enabled', value: input.is(':checked') ? '1' : '0'},
      success: function () { $('#sa-plus-price-status').text('Purchase availability saved. Existing paid access is unchanged.'); },
      error: function () { input.prop('checked', !input.is(':checked')); $('#sa-plus-price-status').text('Purchase availability could not be saved.'); },
      complete: function () { input.prop('disabled', false); }
    });
  });

  document.querySelectorAll('.save-content-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var key      = btn.dataset.key;
      var textarea = document.getElementById(btn.dataset.textarea);

      btn.disabled = true;
      btn.innerHTML = '<i class="ti ti-loader me-1"></i> Saving...';

      fetch(contentSaveUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body:    JSON.stringify({ key: key, content: textarea.value })
      })
      .then(async function(r) {
        const result = await r.json();
        if (!r.ok || result.success === false) throw new Error(result.message || 'Content could not be saved.');
        return result;
      })
      .then(function(res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check me-1"></i> Saved';
        setTimeout(function() { btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Save'; }, 2000);
      })
      .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-alert-circle me-1"></i> Error';
        setTimeout(function() { btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Save'; }, 2000);
      });
    });
  });
</script>
@endsection
