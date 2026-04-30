@extends('layouts/layoutMaster')

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
<div class="container-xl">

  <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <i class="ti ti-info-circle me-2 fs-5"></i>
    <div>
      These settings are also available in the
      <a href="{{ route('backend.superadmin.index') }}#settings"
         onclick="event.preventDefault(); window.location.href='{{ route('backend.superadmin.index') }}'; sessionStorage.setItem('openSettingsTab','1');"
         class="alert-link">Super Admin Dashboard → Settings tab</a>.
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <h4 class="mb-0">Site Settings</h4>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form action="{{ route('settings.store') }}" method="POST">
    @csrf

    {{-- ===== DEFAULT / GLOBAL SETTINGS ===== --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="ti ti-credit-card me-1"></i> Default PayFast Fee Settings</h5>
        <small class="text-muted">
          These defaults apply when the payment method is unknown. The negotiated discount from PayFast benefits Cape Tennis – the convenor is charged at the rates set below.
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
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="ti ti-list me-1"></i> Fee Percentage per Payment Method</h5>
        <small class="text-muted">
          Set the percentage charged to the event convenor for each payment type. The flat fee and VAT above apply to all methods.
        </small>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
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
                      class="form-control method-pct-input"
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
    <div class="card mb-4">
      <div class="card-body">
        <strong>Fee Formula:</strong>
        <code>((amount × percentage%) + R<span id="previewFlat">{{ $payfastSettings['payfast_fee_flat']->value ?? '2.00' }}</span>) × (1 + <span id="previewVat">{{ $payfastSettings['payfast_vat_rate']->value ?? '14' }}</span>%)</code>
      </div>
    </div>

    {{-- ===== CODE OF CONDUCT & TERMS TOGGLES ===== --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="ti ti-file-check me-1"></i> Code of Conduct & Terms</h5>
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
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="mb-0"><i class="ti ti-file-text me-1"></i> Code of Conduct Content</h5>
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

    <div class="mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="ti ti-device-floppy me-1"></i> Save Settings
      </button>
    </div>

  </form>

</div>
@endsection

@section('page-script')
<script>
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
      .then(function(r) { return r.json(); })
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
