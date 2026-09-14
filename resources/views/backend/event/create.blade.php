@extends('layouts.backend')

@section('title', $isCopy ? 'Copy Event' : 'Create Event')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.min.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">{{ $isCopy ? 'Copy Event' : 'Create New Event' }}</h4>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger mb-3">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @unless($isCopy)
    <div class="card border-primary mb-4" id="event-brief-card" data-preview-url="{{ route('backend.events.preview-brief') }}">
      <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
          <div>
            <h5 class="mb-1"><i class="ti ti-sparkles me-1" aria-hidden="true"></i>Paste event information</h5>
            <p class="text-muted mb-0">Paste the notice, email or WhatsApp message. AI will fill the form for you, then show a preview before anything is created.</p>
          </div>
          <button type="button" class="btn btn-primary align-self-md-start" id="fill-event-brief">
            Fill event details
          </button>
        </div>
        <label class="visually-hidden" for="event-brief">Event information to extract</label>
        <textarea id="event-brief" class="form-control mt-3" rows="7" placeholder="Example:&#10;Event: Cape Town Junior Open&#10;Dates: 18–20 October 2026&#10;Type: Individual&#10;Entry fee: R350&#10;Entries close: 7 days before the event&#10;Venue: Bellville Tennis Club&#10;Contact: tournaments@example.org"></textarea>
        <div class="form-text" id="event-brief-status" aria-live="polite">You can edit every extracted field before previewing.</div>
      </div>
    </div>
  @endunless

  <form method="POST" id="event-create-form"
        action="{{ route('backend.events.store') }}"
        enctype="multipart/form-data">

    @csrf

    @if ($isCopy)
      <input type="hidden" name="source_event_id" value="{{ $sourceEvent?->id }}">
    @endif

    <div class="row g-4">

      {{-- ================= LEFT SIDE ================= --}}
      <div class="col-xl-8">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Event Details</h5>
          </div>

          <div class="card-body">

            {{-- Name --}}
            <div class="mb-3">
              <label class="form-label">Event Name <span class="text-danger">*</span></label>
              <input name="name"
                     class="form-control @error('name') is-invalid @enderror"
                     value="{{ old('name', $isCopy ? (($sourceEvent?->name ?? '') . ' (Copy)') : '') }}"
                     required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Dates --}}
            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Start Date</label>
                <input type="date"
                       name="start_date"
                       class="form-control @error('start_date') is-invalid @enderror"
                       value="{{ old('start_date', optional($sourceEvent?->start_date)->format('Y-m-d')) }}">
                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col">
                <label class="form-label">End Date</label>
                <input type="date"
                       name="end_date"
                       class="form-control @error('end_date') is-invalid @enderror"
                       value="{{ old('end_date', optional($sourceEvent?->end_date)->format('Y-m-d')) }}">
                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            {{-- Event Type --}}
            <div class="mb-3">
              <label class="form-label">Event Type <span class="text-danger">*</span></label>
              <select name="eventType"
                      class="form-select @error('eventType') is-invalid @enderror"
                      required>
                <option value="">— Select type —</option>
                @foreach($eventTypes as $type)
                  <option value="{{ $type->id }}" @selected(old('eventType', $sourceEvent?->eventType) == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
              @error('eventType')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Rich information --}}
            <div class="mb-3">
              <label class="form-label">Information</label>
              <div id="information-editor" class="border rounded">
                {!! old('information', $sourceEvent?->information ?? '') !!}
              </div>
              <input type="hidden"
                     name="information"
                     id="information-input"
                     value="{{ old('information', $sourceEvent?->information ?? '') }}">
              <div class="form-text">AI formats this into readable paragraphs and lists. You can edit the result before previewing.</div>
            </div>

            {{-- Venue Notes --}}
            <div class="mb-3">
              <label class="form-label">Venue Notes</label>
              <textarea name="venue_notes"
                        class="form-control"
                        rows="3">{{ old('venue_notes', $sourceEvent?->venue_notes ?? '') }}</textarea>
            </div>

            {{-- Logo --}}
            <div class="mb-3">
              <label class="form-label" for="logo-existing">Use Existing Logo</label>
              @php($selectedLogo = old('logo_existing', $isCopy ? $sourceEvent?->logo : ''))
              <select id="logo-existing"
                      name="logo_existing"
                      class="form-select @error('logo_existing') is-invalid @enderror"
                      data-logo-base="{{ asset('assets/img/logos') }}">
                <option value="">— No existing logo selected —</option>
                @foreach($logoFiles as $logoFile)
                  <option value="{{ $logoFile }}" @selected($selectedLogo === $logoFile)>{{ $logoFile }}</option>
                @endforeach
              </select>
              @error('logo_existing')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text">Choose a logo already used on the website, or upload a new one below.</div>

              <img id="logo-preview"
                   src="{{ $selectedLogo ? asset('assets/img/logos/'.$selectedLogo) : '' }}"
                   alt="Selected event logo preview"
                   class="img-thumbnail mt-2 {{ $selectedLogo ? '' : 'd-none' }}"
                   style="width: 160px; height: 100px; object-fit: contain;">

              <label class="form-label mt-3" for="logo-upload">Upload New Logo</label>
              <input type="file"
                     id="logo-upload"
                     name="logo_upload"
                     class="form-control @error('logo_upload') is-invalid @enderror"
                     accept="image/*">
              <div class="form-text">A newly uploaded logo takes priority over the selected existing logo.</div>
              @error('logo_upload')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

          </div>
        </div>
      </div>

      {{-- ================= RIGHT SIDE ================= --}}
      <div class="col-xl-4">
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Settings</h5>
          </div>

          <div class="card-body">

            {{-- Entry Fee --}}
            <div class="mb-3">
              <label class="form-label">Entry Fee</label>
              <input type="number"
                     name="entryFee"
                     class="form-control"
                     value="{{ old('entryFee', $sourceEvent?->entryFee ?? '') }}">
            </div>

            {{-- Deadline --}}
            <div class="mb-3">
              <label class="form-label">Deadline (days before start)</label>
              <input type="number"
                     name="deadline"
                     class="form-control"
                     value="{{ old('deadline', $sourceEvent?->deadline ?? 7) }}">
              <div class="form-text">Defaults to 7 days before the event unless the event material states otherwise.</div>
            </div>

            {{-- Withdrawal Deadline --}}
            <div class="mb-3">
              <label class="form-label">Withdrawal Deadline</label>
              <input type="datetime-local"
                     name="withdrawal_deadline"
                     class="form-control"
                     value="{{ old('withdrawal_deadline', optional($sourceEvent?->withdrawal_deadline)->format('Y-m-d\TH:i')) }}">
            </div>

            {{-- Organizer --}}
            <div class="mb-3">
              <label class="form-label">Organizer</label>
              <input type="text"
                     name="organizer"
                     class="form-control"
                     value="{{ old('organizer', $sourceEvent?->organizer ?? '') }}">
            </div>

            {{-- Email --}}
            <div class="mb-3">
              <label class="form-label">Contact Email</label>
              <input type="email"
                     name="email"
                     class="form-control @error('email') is-invalid @enderror"
                     value="{{ old('email', $sourceEvent?->email ?? '') }}">
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Admins --}}
            <div class="mb-3">
              <label class="form-label">Event Admins</label>
              <select name="admins[]"
                      class="form-select select2"
                      multiple
                      data-placeholder="Select admins">
                @foreach($users as $user)
                  <option value="{{ $user->id }}"
                    @selected(is_array(old('admins', $adminIds ?? [])) ? in_array($user->id, old('admins', $adminIds ?? [])) : in_array($user->id, $adminIds ?? []))>
                    {{ $user->name }} ({{ $user->email }})
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Published --}}
            <div class="form-check mb-2">
              <input class="form-check-input"
                     type="checkbox"
                     name="published"
                     value="1"
                     @checked(old('published', false))>
              <label class="form-check-label">Published</label>
            </div>

            {{-- SignUp --}}
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     name="signUp"
                     value="1"
                     @checked(old('signUp', false))>
              <label class="form-check-label">Allow Sign-Up</label>
            </div>

          </div>
        </div>
      </div>

    </div>

    {{-- BUTTONS --}}
    <div class="d-flex justify-content-end mt-4 gap-2">
      <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
        Cancel
      </a>
      <button type="submit" class="btn btn-primary">
        {{ $isCopy ? 'Save Copied Event' : 'Preview Event' }}
      </button>
    </div>

  </form>
</div>

@unless($isCopy)
<div class="modal fade" id="eventPreviewModal" tabindex="-1" aria-labelledby="eventPreviewTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="eventPreviewTitle">Review event before creating</h5>
          <div class="small text-muted">Nothing has been saved yet.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="event-preview-content"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep editing</button>
        <button type="button" class="btn btn-primary" id="confirm-create-event">
          <i class="ti ti-check me-1" aria-hidden="true"></i>Create Event
        </button>
      </div>
    </div>
  </div>
</div>
@endunless
@endsection

@section('page-script')
<script>
  $(document).ready(function () {
    $('.select2').select2();
  });

  (() => {
    const form = document.getElementById('event-create-form');
    if (!form) return;
    const informationInput = document.getElementById('information-input');
    const existingLogo = document.getElementById('logo-existing');
    const logoUpload = document.getElementById('logo-upload');
    const logoPreview = document.getElementById('logo-preview');
    let uploadedLogoPreviewUrl = null;
    const showLogoPreview = source => {
      logoPreview.src = source || '';
      logoPreview.classList.toggle('d-none', !source);
    };
    existingLogo?.addEventListener('change', () => {
      if (uploadedLogoPreviewUrl) {
        URL.revokeObjectURL(uploadedLogoPreviewUrl);
        uploadedLogoPreviewUrl = null;
      }
      logoUpload.value = '';
      showLogoPreview(existingLogo.value ? `${existingLogo.dataset.logoBase}/${encodeURIComponent(existingLogo.value)}` : '');
    });
    logoUpload?.addEventListener('change', () => {
      if (uploadedLogoPreviewUrl) URL.revokeObjectURL(uploadedLogoPreviewUrl);
      uploadedLogoPreviewUrl = logoUpload.files[0] ? URL.createObjectURL(logoUpload.files[0]) : null;
      showLogoPreview(uploadedLogoPreviewUrl || (existingLogo.value ? `${existingLogo.dataset.logoBase}/${encodeURIComponent(existingLogo.value)}` : ''));
    });
    const informationEditor = new Quill('#information-editor', {
      theme: 'snow',
      modules: {
        toolbar: [[{ header: [3, 4, false] }], ['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']]
      }
    });
    const brief = document.getElementById('event-brief');
    const syncInformation = () => {
      informationInput.value = informationEditor.getText().trim() ? informationEditor.root.innerHTML : '';
    };
    informationEditor.on('text-change', syncInformation);
    form.addEventListener('submit', syncInformation);

    const value = name => {
      if (name === 'information') syncInformation();
      return form.elements[name]?.value?.trim() || '';
    };
    const setValue = (name, next) => {
      if (next === null || next === undefined || next === '') return false;
      if (name === 'information') {
        informationEditor.clipboard.dangerouslyPasteHTML(String(next));
        syncInformation();
        return true;
      }
      const control = form.elements[name];
      if (!control) return false;
      control.value = next;
      control.dispatchEvent(new Event('change', { bubbles: true }));
      return true;
    };
    const escapeHtml = text => String(text ?? '').replace(/[&<>'"]/g, char => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[char]);
    if (!brief) return;
    document.getElementById('fill-event-brief').addEventListener('click', async event => {
      const text = brief.value.trim();
      const status = document.getElementById('event-brief-status');
      if (text.length < 20) {
        status.textContent = 'Paste at least a short event notice first.';
        brief.focus();
        return;
      }

      const button = event.currentTarget;
      button.disabled = true;
      button.textContent = 'Reading with AI…';
      status.textContent = 'AI is preparing an editable draft…';

      try {
        const response = await fetch(document.getElementById('event-brief-card').dataset.previewUrl, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
          },
          body: JSON.stringify({ brief: text }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'The AI event assistant could not prepare a draft.');

        const draft = payload.draft || {};
        let filled = 0;
        for (const field of ['name', 'start_date', 'end_date', 'information', 'venue_notes', 'entryFee', 'deadline', 'withdrawal_deadline', 'organizer', 'email']) {
          filled += setValue(field, draft[field]) ? 1 : 0;
        }
        filled += setValue('eventType', draft.event_type_id) ? 1 : 0;
        form.elements.published.checked = draft.published === true;
        form.elements.signUp.checked = draft.signUp === true;

        const notes = Array.isArray(draft.review_notes) && draft.review_notes.length
          ? ` Please review: ${draft.review_notes.join(' ')}`
          : '';
        status.textContent = `${filled} field${filled === 1 ? '' : 's'} filled by AI. Check the draft, then preview the event.${notes}`;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        status.textContent = error.message || 'The AI event assistant could not prepare a draft.';
      } finally {
        button.disabled = false;
        button.textContent = 'Fill event details';
      }
    });

    form.addEventListener('submit', event => {
      if (form.dataset.confirmed === 'true') return;
      event.preventDefault();
      if (!form.reportValidity()) return;

      const type = form.elements.eventType.options[form.elements.eventType.selectedIndex]?.text || 'Not selected';
      const admins = [...form.querySelectorAll('[name="admins[]"] option:checked')].map(option => option.text);
      const rows = [
        ['Event name', value('name')], ['Dates', [value('start_date'), value('end_date')].filter(Boolean).join(' to ')],
        ['Event type', type], ['Entry fee', value('entryFee') ? `R${value('entryFee')}` : 'Not set'],
        ['Registration deadline', value('deadline') ? `${value('deadline')} days before start` : 'Not set'],
        ['Withdrawal deadline', value('withdrawal_deadline') || 'Not set'], ['Organizer', value('organizer') || 'Not set'],
        ['Contact email', value('email') || 'Not set'], ['Event admins', admins.join(', ') || 'None selected'],
        ['Published', form.elements.published.checked ? 'Yes' : 'No'], ['Allow sign-up', form.elements.signUp.checked ? 'Yes' : 'No']
      ];
      document.getElementById('event-preview-content').innerHTML = `
        <dl class="row mb-0">${rows.map(([label, content]) => `<dt class="col-sm-4">${escapeHtml(label)}</dt><dd class="col-sm-8">${escapeHtml(content || 'Not set')}</dd>`).join('')}</dl>
        ${value('information') ? `<hr><h6>Information</h6><div class="event-information-content mb-3">${value('information')}</div>` : ''}
        ${value('venue_notes') ? `<h6>Venue notes</h6><p class="mb-0" style="white-space:pre-wrap">${escapeHtml(value('venue_notes'))}</p>` : ''}`;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('eventPreviewModal')).show();
    });

    document.getElementById('confirm-create-event').addEventListener('click', event => {
      event.currentTarget.disabled = true;
      event.currentTarget.textContent = 'Creating…';
      form.dataset.confirmed = 'true';
      syncInformation();
      form.requestSubmit();
    });
  })();
</script>
@endsection
