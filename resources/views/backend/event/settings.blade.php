@extends('layouts.backend')

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
  .settings-shell { max-width: 1180px; margin-inline: auto; }
  .settings-hero { border: 0; background: linear-gradient(135deg, rgba(105, 108, 255, .12), rgba(105, 108, 255, .03)); }
  .settings-card { border: 0; box-shadow: 0 .125rem .5rem rgba(34, 48, 62, .06); }
  .settings-card .card-header { padding-bottom: .25rem; border-bottom: 0; }
  .section-icon { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: .5rem; color: var(--bs-primary); background: rgba(105, 108, 255, .12); flex: 0 0 auto; }
  .logo-preview-wrap { min-height: 152px; background: var(--bs-body-bg); border: 1px dashed var(--bs-border-color); border-radius: .5rem; }
  .logo-preview { max-width: 180px; max-height: 112px; object-fit: contain; }
  .save-status { min-width: 118px; }
  .form-label { font-weight: 500; }
  .field-help { margin-top: .35rem; font-size: .8125rem; color: var(--bs-secondary-color); }
  .select2-admins + .select2-container .select2-selection__choice,
  .select2-scoring-accounts + .select2-container .select2-selection__choice { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
  @media (max-width: 767.98px) {
    .settings-hero .card-body { padding: 1.25rem; }
    .settings-card .card-body { padding: 1.25rem; }
    .save-status { min-width: 0; }
  }
</style>

<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xl settings-shell">

<form method="POST"
      action="{{ route('admin.events.settings.update', $event) }}"
      enctype="multipart/form-data">
@csrf
@method('PATCH')

@include('backend.event.partials.header', [
  'eventWorkspaceActive' => 'settings',
  'eventWorkspaceIcon' => 'ti-settings',
  'eventWorkspaceSubtitle' => 'Event settings',
])
<div class="d-flex justify-content-end mb-3 no-print">
  <div id="save-status" class="save-status d-flex align-items-center justify-content-sm-end gap-2 text-success" aria-live="polite">
      <i class="ti ti-circle-check"></i>
      <span>All changes saved</span>
  </div>
</div>

<div class="row g-4">

{{-- BASICS --}}
<div class="col-12">
  <div class="card settings-card">
    <div class="card-header d-flex align-items-start gap-3">
      <span class="section-icon"><i class="ti ti-adjustments-horizontal"></i></span>
      <div><h5 class="mb-1">Basics & visibility</h5><p class="text-muted small mb-0">The event identity and what visitors can access.</p></div>
    </div>
    <div class="card-body pt-3">
      <div class="row g-4">
        <div class="col-lg-8">
          <label class="form-label" for="event-name">Event name</label>
          <input id="event-name" class="form-control autosave" name="name" value="{{ $event->name }}">

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label" for="event-type">Event type</label>
              <select id="event-type" class="form-select autosave" name="eventType">
                @foreach(\App\Models\EventType::all() as $type)
                  <option value="{{ $type->id }}" @selected($event->eventType == $type->id)>{{ $type->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="entry-status">Entry status</label>
              <select id="entry-status" class="form-select autosave" name="status">
                <option value="draft" @selected($event->status === 'draft')>Draft</option>
                <option value="open" @selected($event->status === 'open')>Open</option>
                <option value="closed" @selected($event->status === 'closed')>Closed</option>
              </select>
            </div>
          </div>

          <div class="d-flex flex-column flex-sm-row gap-3 gap-sm-5 mt-4 p-3 rounded bg-body">
        <div class="form-check form-switch">
          <input class="form-check-input autosave" type="checkbox"
                 id="event-published" name="published" {{ $event->published ? 'checked' : '' }}>
          <label class="form-check-label fw-semibold" for="event-published">Published</label>
          <div class="field-help mt-0">Show the event publicly.</div>
        </div>

        <div class="form-check form-switch">
          <input class="form-check-input autosave" type="checkbox"
                 id="signup-open" name="signUp" {{ $event->signUp ? 'checked' : '' }}>
          <label class="form-check-label fw-semibold" for="signup-open">Signup open</label>
          <div class="field-help mt-0">Allow players to enter.</div>
        </div>
      </div>
        </div>

        <div class="col-lg-4">
          <label class="form-label">Event logo</label>
          <div class="logo-preview-wrap d-flex align-items-center justify-content-center p-3 mb-3">
        @if($event->logo)
          <img
            id="logo-preview"
            src="{{ asset('assets/img/logos/'.$event->logo) }}"
            class="logo-preview img-fluid"
            alt="Current event logo"
          >
        @else
          <img
            id="logo-preview"
            src="{{ asset('assets/img/placeholder-logo.png') }}"
            class="logo-preview img-fluid"
            alt="No event logo selected"
          >
        @endif
      </div>
      <label class="form-label" for="logo-existing-select">Choose an existing logo</label>
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
      <label class="form-label" for="logo-upload">Or upload a new logo</label>
      <input
        id="logo-upload"
        type="file"
        class="form-control"
        name="logo_upload"
        accept="image/*">
      <div class="field-help">Your selection is applied automatically. Maximum file size: 2 MB.</div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- INFORMATION --}}
<div class="col-12">
  <div class="card settings-card">
    <div class="card-header d-flex align-items-start gap-3">
      <span class="section-icon"><i class="ti ti-file-description"></i></span>
      <div><h5 class="mb-1">Public information</h5><p class="text-muted small mb-0">The description players and parents will see on the event page.</p></div>
    </div>
    <div class="card-body pt-3">
      <div id="info-editor">{!! $event->information !!}</div>
    </div>
  </div>
</div>

{{-- DATES --}}
<div class="col-12">
  <div class="card settings-card">
    <div class="card-header d-flex align-items-start gap-3">
      <span class="section-icon"><i class="ti ti-calendar-event"></i></span>
      <div><h5 class="mb-1">Schedule & registration</h5><p class="text-muted small mb-0">Set the event dates, deadlines, fee and organizer contact.</p></div>
    </div>
    <div class="card-body pt-3">
      <div class="row g-3">
        <div class="col-md-6">
      <label class="form-label" for="start-date">Start date</label>
      <input type="date" class="form-control autosave"
             id="start-date" name="start_date"
             value="{{ optional($event->start_date)->format('Y-m-d') }}">
        </div>
        <div class="col-md-6">
      <label class="form-label" for="end-date">End date</label>
      <input type="date" class="form-control autosave"
             id="end-date" name="end_date"
             value="{{ optional($event->end_date)->format('Y-m-d') }}">
        </div>
        <div class="col-md-6">
      <label class="form-label" for="registration-deadline">Registration deadline</label>
      <div class="input-group">
        <input type="number" class="form-control autosave"
               id="registration-deadline" name="deadline" min="0" value="{{ $event->deadline }}">
        <span class="input-group-text">days before start</span>
      </div>
      <div class="field-help" id="registration-closure-text"></div>
        </div>
        <div class="col-md-6">
      <label class="form-label" for="withdrawal-deadline">Withdrawal deadline</label>
      <div class="input-group">
        <input type="number" class="form-control autosave"
               id="withdrawal-deadline" name="withdrawal_days" min="0"
               value="{{ $event->withdrawalDeadlineDaysBeforeStart() }}">
        <span class="input-group-text">days before start</span>
      </div>
      <div class="field-help" id="withdrawal-closure-text"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="entry-fee">Default entry fee</label>
          <div class="input-group"><span class="input-group-text">R</span><input id="entry-fee" type="number" min="0" class="form-control autosave" name="entryFee" value="{{ $event->entryFee }}"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="organizer-email">Organizer email</label>
          <input id="organizer-email" type="email" class="form-control autosave" name="email" value="{{ $event->email }}">
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ADMINS --}}
<div class="col-lg-5">
  <div class="card settings-card h-100">
    <div class="card-header d-flex align-items-start gap-3">
      <span class="section-icon"><i class="ti ti-shield-lock"></i></span>
      <div><h5 class="mb-1">Event admins</h5><p class="text-muted small mb-0">Full event management access.</p></div>
    </div>
    <div class="card-body">
      <select class="form-select select2-admins"
              name="admins" multiple
              data-placeholder="Select event admins">
        @foreach($users as $user)
          <option value="{{ $user->id }}"
            @selected($event->admins->contains($user->id))>
            {{ $user->name }} ({{ $user->email }})
          </option>
        @endforeach
      </select>
    </div>
  </div>
</div>

{{-- CONVENORS --}}
@php $convenorIds = $convenors->pluck('user_id')->toArray(); @endphp
<div class="col-lg-7">
  <div class="card settings-card h-100">
    <div class="card-header d-flex align-items-start gap-3">
      <span class="section-icon"><i class="ti ti-users"></i></span>
      <div><h5 class="mb-1">Event directors</h5><p class="text-muted small mb-0">Time-limited operational access for tournament staff.</p></div>
    </div>
    <div class="card-body">
      <select class="form-select select2-convenors"
              name="convenors" multiple
              data-placeholder="Select event directors">
        @foreach($users as $user)
          <option value="{{ $user->id }}"
            @selected(in_array($user->id, $convenorIds))>
            {{ $user->name }} ({{ $user->email }})
          </option>
        @endforeach
      </select>

      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label">Access starts</label>
          <input type="datetime-local" class="form-control autosave"
                 name="convenor_starts_at"
                 value="{{ optional($convenors->first())->starts_at?->format('Y-m-d\TH:i') ?? optional($event->start_date)->format('Y-m-d\TH:i') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">Access expires</label>
          <input type="datetime-local" class="form-control autosave"
                 name="convenor_expires_at"
                 value="{{ optional($convenors->first())->expires_at?->format('Y-m-d\TH:i') ?? optional($event->end_date)->format('Y-m-d\TH:i') }}">
        </div>
      </div>

      @if($convenors->isNotEmpty())
        <div class="mt-3">
          <small class="text-muted fw-semibold">Active Event Directors</small>
          <ul class="list-unstyled mb-0 mt-1">
            @foreach($convenors as $c)
              <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                <span>{{ $c->user->name ?? 'Unknown' }}</span>
                @if($c->isActive())
                  @if($c->timeRemaining())
                    <span class="badge bg-warning text-dark">
                      <i class="ti ti-clock me-1"></i>{{ $c->timeRemaining() }} left
                    </span>
                  @else
                    <span class="badge bg-success">No expiry</span>
                  @endif
                @else
                  <span class="badge bg-danger">Expired</span>
                @endif
              </li>
            @endforeach
          </ul>
        </div>
      @endif

    </div>
  </div>
</div>

{{-- SCORING ACCOUNTS --}}
@php
  $scoringAccountIds = $scoringAccounts->pluck('user_id')->toArray();
  $firstScoringAccount = $scoringAccounts->first();
@endphp
<div class="col-12">
  <div class="card settings-card">
    <div class="card-header d-flex align-items-start gap-3">
      <span class="section-icon"><i class="ti ti-scoreboard"></i></span>
      <div>
        <h5 class="mb-1">Scoring accounts</h5>
        <p class="text-muted small mb-0">Give dedicated user accounts score-entry access for this event only.</p>
      </div>
    </div>
    <div class="card-body">
      <label class="form-label" for="scoring-accounts">Accounts allowed to score</label>
      <select id="scoring-accounts"
              class="form-select select2-scoring-accounts"
              name="scoring_accounts" multiple
              data-placeholder="Select scoring accounts">
        @foreach($scoringUsers as $user)
          <option value="{{ $user->id }}" @selected(in_array($user->id, $scoringAccountIds))>
            {{ $user->name }} ({{ $user->email }})
          </option>
        @endforeach
      </select>
      <div class="field-help">These accounts can enter and correct scores, but cannot change draws, publish, or lock the event. Each person must already have a user account.</div>

      <div class="row g-3 mt-1">
        <div class="col-md-6">
          <label class="form-label" for="scoring-starts-at">Scoring access starts</label>
          <input id="scoring-starts-at" type="datetime-local" class="form-control autosave"
                 name="scoring_starts_at"
                 value="{{ $firstScoringAccount?->starts_at?->format('Y-m-d\TH:i') ?? optional($event->start_date)->format('Y-m-d\TH:i') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="scoring-expires-at">Scoring access expires</label>
          <input id="scoring-expires-at" type="datetime-local" class="form-control autosave"
                 name="scoring_expires_at"
                 value="{{ $firstScoringAccount?->expires_at?->format('Y-m-d\TH:i') ?? optional($event->end_date)->format('Y-m-d\TH:i') }}">
        </div>
      </div>

      @if($scoringAccounts->isNotEmpty())
        <div class="mt-3">
          <small class="text-muted fw-semibold">Assigned scoring accounts</small>
          <ul class="list-unstyled mb-0 mt-1">
            @foreach($scoringAccounts as $account)
              <li class="d-flex flex-column flex-sm-row justify-content-between gap-1 py-2 border-bottom">
                <span>{{ $account->user->name ?? 'Unknown' }} <span class="text-muted">{{ $account->user->email ?? '' }}</span></span>
                <span class="badge {{ $account->isActive() ? 'bg-success' : 'bg-secondary' }} align-self-start">
                  {{ $account->isActive() ? 'Scoring access active' : 'Outside access window' }}
                </span>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
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

  function setSaveStatus(state, message) {
    const states = {
      saved:  { className: 'text-success', icon: 'ti-circle-check' },
      saving: { className: 'text-primary', icon: 'ti-loader-2' },
      error:  { className: 'text-danger', icon: 'ti-alert-circle' }
    };
    const status = states[state] || states.saved;
    $('#save-status')
      .removeClass('text-success text-primary text-danger')
      .addClass(status.className)
      .find('i')
      .attr('class', `ti ${status.icon}${state === 'saving' ? ' ti-spin' : ''}`);
    $('#save-status span').text(message);
  }

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
    setSaveStatus('saving', 'Saving changes…');
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
      payload.convenors = $('.select2-convenors').val() || [];
      payload.scoring_accounts = $('.select2-scoring-accounts').val() || [];

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
        setSaveStatus('saved', 'All changes saved');
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

        setSaveStatus('error', 'Changes not saved');
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

  $('.select2-convenors').select2({
    width: '100%',
    allowClear: true,
    placeholder: $('.select2-convenors').data('placeholder')
  }).on('change', function () {
    console.log('👥 Convenors changed:', $(this).val());
    autosave();
  });

  $('.select2-scoring-accounts').select2({
    width: '100%',
    allowClear: true,
    placeholder: $('.select2-scoring-accounts').data('placeholder')
  }).on('change', function () {
    console.log('🎾 Scoring accounts changed:', $(this).val());
    autosave();
  });

  /* =========================
     QUILL AUTOSAVE
  ========================= */
  const quill = new Quill('#info-editor', { theme: 'snow' });
  let infoTimer = null;

  quill.on('text-change', function () {
    setSaveStatus('saving', 'Saving information…');
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
        setSaveStatus('saved', 'All changes saved');
        toastr.success('Info saved');
      })
      .fail((xhr) => {
        console.error('❌ Info save failed', xhr.responseText);
        setSaveStatus('error', 'Information not saved');
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
  setSaveStatus('saving', 'Saving logo…');

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
    setSaveStatus('saved', 'All changes saved');
    toastr.success('Logo saved');
  })
  .fail((xhr) => {
    console.error('❌ Logo autosave failed', xhr.responseText);
    setSaveStatus('error', 'Logo not saved');
    toastr.error('Logo save failed');
  });

});

});
</script>



@endsection
