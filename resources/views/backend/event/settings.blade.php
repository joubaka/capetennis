@extends('layouts/layoutMaster')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('title', $event->name . ' – Settings')

@section('content')
<style>
  .select2-container { z-index: 1055; }
  .logo-preview { max-height: 90px; }
</style>

<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xl">

<form method="POST"
      action="{{ route('admin.events.settings.update', $event) }}"
      enctype="multipart/form-data">
@csrf
@method('PATCH')

{{-- HEADER --}}
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Event Settings</h4>
    <button class="btn btn-primary btn-sm">
      <i class="ti ti-device-floppy me-1"></i> Save Logo
    </button>
  </div>
</div>

<div class="row g-3">

{{-- EVENT --}}
<div class="col-lg-12">
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Event</h5></div>
    <div class="card-body">

      <label class="form-label">Event Name</label>
      <input class="form-control autosave" name="name" value="{{ $event->name }}">

      <div class="d-flex gap-4 mt-3">
        <div class="form-check form-switch">
          <input class="form-check-input autosave" type="checkbox"
                 name="published" {{ $event->published ? 'checked' : '' }}>
          <label class="form-check-label">Published</label>
        </div>

        <div class="form-check form-switch">
          <input class="form-check-input autosave" type="checkbox"
                 name="signUp" {{ $event->signUp ? 'checked' : '' }}>
          <label class="form-check-label">Signup Open</label>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- LOGO --}}
<div class="col-lg-6">
  <div class="card h-100">
    <div class="card-header">
      <h5 class="mb-0">Event Logo</h5>
    </div>

    <div class="card-body">

      {{-- CURRENT LOGO PREVIEW --}}
      <div class="mb-3 text-center">
        @if($event->logo)
          <img
            id="logo-preview"
            src="{{ asset('assets/img/logos/'.$event->logo) }}"
            class="logo-preview img-fluid border rounded mb-2"
          >
        @else
          <img
            id="logo-preview"
            src="{{ asset('assets/img/placeholder-logo.png') }}"
            class="logo-preview img-fluid border rounded mb-2"
          >
        @endif
      </div>

      {{-- EXISTING LOGOS --}}
      <label class="form-label">Choose Existing Logo</label>
      <select
        class="form-select mb-3"
        name="logo_existing"
        id="logo-existing-select">

        <option value="">— No logo —</option>

        @foreach(File::files(public_path('assets/img/logos')) as $file)
          @php $name = $file->getFilename(); @endphp
          <option
            value="{{ $name }}"
            @selected($event->logo === $name)>
            {{ $name }}
          </option>
        @endforeach
      </select>

      {{-- UPLOAD --}}
      <label class="form-label">Upload New Logo</label>
      <input
        type="file"
        class="form-control"
        name="logo_upload"
        accept="image/*">

      <small class="text-muted d-block mt-2">
        Selecting an existing logo or uploading a new one will replace the current logo.
        Click <strong>Save Logo</strong> to apply.
      </small>

    </div>
  </div>
</div>


{{-- INFORMATION --}}
<div class="col-lg-12">
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Event Information</h5></div>
    <div class="card-body">
      <div id="info-editor">{!! $event->information !!}</div>
    </div>
  </div>
</div>

{{-- GENERAL --}}
<div class="col-lg-6">
  <div class="card h-100">
    <div class="card-header"><h5 class="mb-0">General</h5></div>
    <div class="card-body">

      <label class="form-label">Entry Status</label>
      <select class="form-select autosave" name="status">
        <option value="draft" @selected($event->status === 'draft')>Draft</option>
        <option value="open" @selected($event->status === 'open')>Open</option>
        <option value="closed" @selected($event->status === 'closed')>Closed</option>
      </select>

      <label class="form-label mt-3">Default Entry Fee</label>
      <input class="form-control autosave" name="entryFee" value="{{ $event->entryFee }}">

      <label class="form-label mt-3">Event Type</label>
      <select class="form-select autosave" name="eventType">
        @foreach(\App\Models\EventType::all() as $type)
          <option value="{{ $type->id }}" @selected($event->eventType == $type->id)>
            {{ $type->name }}
          </option>
        @endforeach
      </select>

    </div>
  </div>
</div>

{{-- DATES --}}
<div class="col-lg-6">
  <div class="card h-100">
    <div class="card-header"><h5 class="mb-0">Dates & Registration</h5></div>
    <div class="card-body">

      <label class="form-label">Start Date</label>
      <input type="date" class="form-control autosave"
             name="start_date"
             value="{{ optional($event->start_date)->format('Y-m-d') }}">

      <label class="form-label mt-3">End Date</label>
      <input type="date" class="form-control autosave"
             name="end_date"
             value="{{ optional($event->end_date)->format('Y-m-d') }}">

      <label class="form-label mt-3">Registration Deadline</label>
      <div class="input-group">
        <input type="number" class="form-control autosave"
               name="deadline" min="0" value="{{ $event->deadline }}">
        <span class="input-group-text">days before start</span>
      </div>
      <small class="text-muted" id="registration-closure-text"></small>

      <label class="form-label mt-3">Withdrawal Deadline</label>
      <div class="input-group">
        <input type="number" class="form-control autosave"
               name="withdrawal_days" min="0"
               value="{{ $event->withdrawal_deadline
                 ? $event->start_date?->diffInDays($event->withdrawal_deadline)
                 : '' }}">
        <span class="input-group-text">days before start</span>
      </div>
      <small class="text-muted" id="withdrawal-closure-text"></small>

      <label class="form-label mt-3">Organizer Email</label>
      <input class="form-control autosave" name="email" value="{{ $event->email }}">

    </div>
  </div>
</div>

{{-- ADMINS --}}
<div class="col-lg-6">
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Event Admins</h5></div>
    <div class="card-body">
      <select class="form-select select2-admins"
              name="admins" multiple
              data-placeholder="Select event admins">
        @foreach(\App\Models\User::orderBy('name')->get() as $user)
          <option value="{{ $user->id }}"
            @selected($event->admins->contains($user->id))>
            {{ $user->name }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
</div>

</div>
</form>
</div>
@endsection

@section('page-script')

<script>
$(function () {

  console.log('⚙️ Event settings JS initialised');

  const csrf = $('meta[name="csrf-token"]').attr('content');
  const updateUrl = @json(route('admin.events.settings.update', $event));

  toastr.options = { closeButton:true, progressBar:true, timeOut:2000 };

  let saveTimer = null;

  /* =========================
     HELPERS
  ========================= */
  function computeDate(start, days) {
    const d = new Date(start);
    d.setDate(d.getDate() - days);
    return d.toISOString().slice(0, 10);
  }

  /* =========================
     AUTOSAVE
  ========================= */
  function autosave() {

    console.log('⏳ Autosave triggered (debounced)');
    clearTimeout(saveTimer);

    saveTimer = setTimeout(function () {

      console.groupCollapsed('💾 AUTOSAVE PAYLOAD BUILD');

      const payload = {};

      $('.autosave').each(function () {
        const el = $(this);
        const name = el.attr('name');
        if (!name) return;

        if (el.attr('type') === 'checkbox') {
          payload[name] = el.is(':checked') ? 1 : 0;
          return;
        }

        payload[name] = el.val() === '' ? null : el.val();
      });

      payload.admins = $('.select2-admins').val() || [];

      // 🔹 Withdrawal logic
      if (payload.withdrawal_days !== undefined && payload.start_date) {

        console.log('↩️ Withdrawal calculation', {
          start_date: payload.start_date,
          withdrawal_days: payload.withdrawal_days
        });

        payload.withdrawal_deadline =
          payload.withdrawal_days === null
            ? null
            : computeDate(
                payload.start_date,
                parseInt(payload.withdrawal_days, 10)
              );

        console.log('📆 Computed withdrawal_deadline:', payload.withdrawal_deadline);
      }

      delete payload.withdrawal_days;

      console.log('📦 Final payload:', payload);
      console.groupEnd();

      console.log('🚀 Sending PATCH →', updateUrl);

      $.ajax({
        url: updateUrl,
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrf },
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify(payload)
      })
      .done(function (res) {
        console.log('✅ Saved response:', res);
        toastr.success('Saved');
        updatePreviews();
      })
      .fail(function (xhr) {
        console.error('❌ Save failed');
        console.error('Status:', xhr.status);
        console.error('Response:', xhr.responseText);

        if (xhr.responseJSON?.errors) {
          console.table(xhr.responseJSON.errors);
        }

        toastr.error('Save failed');
      });

    }, 700);
  }

  /* =========================
     BIND AUTOSAVE
  ========================= */
  $(document).on('change keyup', '.autosave', autosave);

  $('.select2-admins').select2({
    width: '100%',
    allowClear: true,
    placeholder: $('.select2-admins').data('placeholder')
  }).on('change', function () {
    console.log('👥 Admins changed:', $(this).val());
    autosave();
  });

  /* =========================
     QUILL AUTOSAVE
  ========================= */
  const quill = new Quill('#info-editor', { theme: 'snow' });
  let infoTimer = null;

  quill.on('text-change', function () {
    clearTimeout(infoTimer);

    infoTimer = setTimeout(function () {

      const html = quill.root.innerHTML || null;

      console.groupCollapsed('📝 QUILL SAVE');
      console.log('HTML length:', html?.length ?? 0);
      console.groupEnd();

      $.ajax({
        url: updateUrl,
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': csrf },
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ information: html })
      })
      .done(() => {
        console.log('✅ Info saved');
        toastr.success('Info saved');
      })
      .fail((xhr) => {
        console.error('❌ Info save failed', xhr.responseText);
        toastr.error('Info save failed');
      });

    }, 1000);
  });

  /* =========================
     DATE PREVIEWS
  ========================= */
  function updatePreviews() {
    const start = $('[name="start_date"]').val();
    const reg = parseInt($('[name="deadline"]').val(), 10);
    const wit = parseInt($('[name="withdrawal_days"]').val(), 10);

    console.log('📅 Preview update', { start, reg, wit });

    if (start && !isNaN(reg)) {
      const d = new Date(start);
      d.setDate(d.getDate() - reg);

      $('#registration-closure-text').html(
        `Registration closes on <strong>${
          d.toLocaleDateString('en-ZA', {
            day:'2-digit', month:'short', year:'numeric'
          })
        }</strong>`
      );
    }

    if (start && !isNaN(wit)) {
      const d = new Date(start);
      d.setDate(d.getDate() - wit);

      $('#withdrawal-closure-text').html(
        `Withdrawals close on <strong>${
          d.toLocaleDateString('en-ZA', {
            day:'2-digit', month:'short', year:'numeric'
          })
        }</strong>`
      );
    }
  }

  $(document).on(
    'keyup change',
    '[name="deadline"], [name="withdrawal_days"], [name="start_date"]',
    updatePreviews
  );

  updatePreviews();
  // =========================
// LOGO PREVIEW (EXISTING)
// =========================
$('#logo-existing-select').on('change', function () {
  const filename = $(this).val();

  console.log('🖼 Existing logo selected:', filename);

  if (!filename) {
    $('#logo-preview').attr(
      'src',
      '{{ asset('assets/img/placeholder-logo.png') }}'
    );
    return;
  }

  $('#logo-preview').attr(
    'src',
    '{{ asset('assets/img/logos') }}/' + filename
  );
});
$('[name="logo_upload"], #logo-existing-select').on('change', function () {

  console.log('🖼 Logo autosave triggered');

  const formData = new FormData();
  formData.append('_method', 'PATCH');
  formData.append('_token', csrf);

  const file = $('[name="logo_upload"]')[0].files[0];
  const existing = $('#logo-existing-select').val();

  if (file) {
    formData.append('logo_upload', file);
  } else if (existing) {
    formData.append('logo_existing', existing);
  }

  $.ajax({
    url: updateUrl,
    method: 'POST',
    processData: false,
    contentType: false,
    data: formData
  })
  .done(() => {
    console.log('✅ Logo autosaved');
    toastr.success('Logo saved');
  })
  .fail((xhr) => {
    console.error('❌ Logo autosave failed', xhr.responseText);
    toastr.error('Logo save failed');
  });

});

});
</script>



@endsection
