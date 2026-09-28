@extends('layouts.backend')
@section('title', 'Interprovincial Trials nominations and invitations')
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection
@section('content')
<div class="container-xl">
  @include('backend.event.partials.header', ['event' => $event])

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Nominations &amp; invitations</h2><p class="text-muted mb-0">Nominate existing Cape Tennis players per category, then prepare an exact invitation snapshot.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.events.overview', $event) }}">Back to event overview</a>
  </div>
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

  <section class="card mb-4" aria-labelledby="invitation-readiness-heading" data-testid="invitation-readiness">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1" id="invitation-readiness-heading">Invitation readiness</h5>
        <p class="text-muted small mb-0">A read-only view of nominations, recipients, message review and registration access.</p>
      </div>
      <span class="badge {{ $readiness['batch_status'] === 'reviewed' ? 'bg-label-success' : 'bg-label-info' }} text-break">
        {{ $readiness['batch_status'] ? 'Batch '.ucfirst($readiness['batch_status']) : 'No invitation batch' }}
      </span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Categories</div><div class="fs-4 fw-semibold">{{ $readiness['category_count'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Nominations</div><div class="fs-4 fw-semibold">{{ $readiness['nomination_count'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Ready recipients</div><div class="fs-4 fw-semibold">{{ $readiness['ready_recipient_count'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Recipient blockers</div><div class="fs-4 fw-semibold">{{ $readiness['blocked_recipient_count'] }}</div></div></div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3" aria-label="Readiness checks">
        <span class="badge {{ $readiness['nomination_count'] > 0 ? 'bg-label-success' : 'bg-label-warning' }}">{{ $readiness['nomination_count'] > 0 ? 'Nominations ready' : 'Add nominations' }}</span>
        <span class="badge {{ $readiness['invitation_count'] > 0 && $readiness['blocked_recipient_count'] === 0 ? 'bg-label-success' : 'bg-label-warning' }}">{{ $readiness['invitation_count'] > 0 && $readiness['blocked_recipient_count'] === 0 ? 'Recipients ready' : 'Recipients need attention' }}</span>
        <span class="badge {{ $readiness['message_saved'] ? 'bg-label-success' : 'bg-label-warning' }}">{{ $readiness['message_saved'] ? 'Message saved' : 'Message not saved' }}</span>
        <span class="badge {{ $readiness['message_reviewed'] ? 'bg-label-success' : 'bg-label-warning' }}">{{ $readiness['message_reviewed'] ? 'Message reviewed' : 'Review required' }}</span>
        <span class="badge {{ $readiness['registration_open'] ? 'bg-label-success' : 'bg-label-danger' }}">Registration {{ $readiness['registration_open'] ? 'open' : 'closed' }}</span>
      </div>
      @if($readiness['state_counts']->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 mt-3" aria-label="Invitation status counts">
          @foreach($readiness['state_counts'] as $status => $count)
            <span class="badge bg-label-secondary text-break">{{ str($status)->replace('_', ' ')->title() }}: {{ $count }}</span>
          @endforeach
        </div>
      @endif
    </div>
  </section>

  @php
    $publishedCategoryCount = $event->categoryEvents->where('nominations_published', true)->count();
    $publicationStatus = $publishedCategoryCount === 0 ? 'Private' : ($publishedCategoryCount === $event->categoryEvents->count() ? 'Published' : 'Mixed');
  @endphp
  <div class="card mb-4"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><strong>Public nomination list: {{ $publicationStatus }}</strong><div class="text-muted small">Publishing deliberately shows nominated player names on the public event page.</div></div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('events.show', $event) }}" target="_blank" rel="noopener">View public page</a>
      <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.publication', $event) }}">@csrf @method('PUT')<input type="hidden" name="published" value="{{ $publicationStatus === 'Published' ? 0 : 1 }}"><button class="btn {{ $publicationStatus === 'Published' ? 'btn-outline-warning' : 'btn-outline-primary' }}" onclick="return confirm('{{ $publicationStatus === 'Published' ? 'Make every nomination category private?' : 'Publish nominated player names in every category?' }}')">{{ $publicationStatus === 'Published' ? 'Unpublish all names' : 'Publish all names' }}</button></form>
    </div>
  </div></div>

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">1. Nominate an existing player</h5></div><div class="card-body">
    <form method="POST" action="{{ route('backend.interprovincial-trials.nominations.bulk-store', $event) }}" id="nomination-form" class="row g-3">
      @csrf
      <div class="col-lg-6">
        <label class="form-label" for="nomination-player">Player</label>
        <select class="form-select" id="nomination-player" name="player_ids[]" style="width:100%" multiple required aria-describedby="nomination-player-help"></select>
        <div class="form-text" id="nomination-player-help">Search by player name or surname. You may select up to 50 players.</div>
      </div>
      <div class="col-lg-4">
        <label class="form-label" for="nomination-category">Category</label>
        <select class="form-select" id="nomination-category" name="category_event_id" required>
          <option value="">Choose a category</option>
          @foreach($event->categoryEvents as $categoryEvent)
            <option value="{{ $categoryEvent->id }}">{{ $categoryEvent->category?->name ?? 'Category' }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit" {{ $event->categoryEvents->isEmpty() ? 'disabled' : '' }}>Add nominations</button>
      </div>
      <div class="col-12"><div id="nomination-feedback" class="small" role="status" aria-live="polite"></div></div>
    </form>
  </div></div>

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">2. Review nominations by category</h5></div><div class="card-body"><div class="row g-3">
    @forelse($event->categoryEvents as $categoryEvent)
      <div class="col-lg-6"><div class="border rounded p-3 h-100" data-nomination-category="{{ $categoryEvent->id }}"><h6>{{ $categoryEvent->category?->name ?? 'Category' }} <span class="badge bg-label-primary" data-nomination-count>{{ $categoryEvent->nominations->count() }}</span></h6><div class="list-group list-group-flush" data-nomination-list>
      @forelse($categoryEvent->nominations as $nomination)
        <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2"><span>{{ $nomination->player?->name }} {{ $nomination->player?->surname }}</span><form class="nomination-remove-form" method="POST" action="{{ route('backend.interprovincial-trials.nominations.destroy', [$event, $categoryEvent, $nomination]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></div>
      @empty<div class="text-muted" data-empty-nominations>No players nominated yet.</div>@endforelse
      </div></div></div>
    @empty<div class="col-12"><div class="alert alert-warning mb-0">Add event categories before nominating players.</div></div>@endforelse
  </div></div></div>

  <div class="card"><div class="card-header"><h5 class="mb-0">3. Prepare and review invitations</h5></div><div class="card-body"><form method="POST" action="{{ route('backend.interprovincial-trials.invitations.prepare', $event) }}">@csrf<button class="btn btn-primary">Prepare invitations from current nominations</button></form></div></div>

  @if($batch)
  <div class="card mt-4 overflow-hidden"><div class="card-header d-flex flex-wrap gap-2"><strong class="text-break">Invitation batch #{{ $batch->id }}</strong> <span class="badge bg-label-info text-break">{{ ucfirst($batch->status) }}</span></div>
  <div class="card-body border-bottom"><h6>Invitation message</h6><form method="POST" action="{{ route('backend.interprovincial-trials.batches.message', [$event, $batch]) }}">@csrf @method('PUT')<div class="mb-3"><label class="form-label" for="email-subject">Subject</label><input id="email-subject" class="form-control" name="email_subject" maxlength="150" value="{{ old('email_subject', $batch->email_subject) }}" required></div><div class="mb-3"><label class="form-label" for="email-body">Plain-text message</label><textarea id="email-body" class="form-control" name="email_body" rows="7" maxlength="5000" required>{{ old('email_body', $batch->email_body) }}</textarea></div><button class="btn btn-outline-primary">Save invitation message</button></form></div>
  <div class="card-body border-bottom"><strong>Exact recipient preview</strong><div class="small text-muted">Review every player, category, recipient and the stored message below. Missing account emails block review and queueing.</div></div>
  <div class="table-responsive"><table class="table"><thead><tr><th>Player</th><th>Category</th><th>Recipient</th><th>Readiness</th></tr></thead><tbody>
  @foreach($batch->invitations as $invitation)<tr><td>{{ $invitation->player->name }} {{ $invitation->player->surname }}</td><td>{{ $invitation->categoryEvent->category->name }}</td><td>{{ $invitation->recipient_email ?: 'No linked account email' }}</td><td>{{ $invitation->recipient_email ? 'Ready' : 'Blocked' }} @if($invitation->status === 'failed')<form class="d-inline" method="POST" action="{{ route('backend.interprovincial-trials.invitations.retry', [$event,$batch,$invitation]) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry email</button></form>@endif</td></tr>@endforeach
  </tbody></table></div><div class="card-body d-flex flex-wrap gap-2">
  @if($batch->status === 'draft' && $batch->message_hash)<form method="POST" action="{{ route('backend.interprovincial-trials.batches.review', [$event,$batch]) }}">@csrf<input type="hidden" name="snapshot_hash" value="{{ $batch->snapshot_hash }}"><input type="hidden" name="message_hash" value="{{ $batch->message_hash }}"><button class="btn btn-success">I reviewed these exact recipients and message</button></form>@endif
  @if($batch->status === 'reviewed')<form method="POST" action="{{ route('backend.interprovincial-trials.batches.send', [$event,$batch]) }}">@csrf<div class="form-check mb-2"><input class="form-check-input" type="checkbox" value="1" name="confirm_exact_recipients_and_message" id="confirm-exact" required><label class="form-check-label" for="confirm-exact">I confirm this exact recipient list and stored message must be queued.</label></div><button class="btn btn-primary">Queue invitations</button></form>@endif
  </div></div>
  @endif
</div>
@endsection

@section('page-script')
<script>
$(function () {
  const player = $('#nomination-player');
  const form = document.getElementById('nomination-form');
  const feedback = document.getElementById('nomination-feedback');
  const csrf = form.querySelector('input[name="_token"]').value;

  const showFeedback = (message, isError = false) => {
    feedback.textContent = message;
    feedback.className = `small ${isError ? 'text-danger' : 'text-success'}`;
  };

  const rebuildCategory = data => {
    const card = document.querySelector(`[data-nomination-category="${data.category_event_id}"]`);
    if (!card) return;
    card.querySelector('[data-nomination-count]').textContent = data.count;
    const list = card.querySelector('[data-nomination-list]');
    list.replaceChildren();
    if (!data.nominations.length) {
      const empty = document.createElement('div');
      empty.className = 'text-muted';
      empty.dataset.emptyNominations = '';
      empty.textContent = 'No players nominated yet.';
      list.appendChild(empty);
      return;
    }
    data.nominations.forEach(nomination => {
      const row = document.createElement('div');
      row.className = 'list-group-item px-0 d-flex justify-content-between align-items-center gap-2';
      const name = document.createElement('span');
      name.textContent = nomination.player_name;
      const removeForm = document.createElement('form');
      removeForm.className = 'nomination-remove-form';
      removeForm.method = 'POST';
      removeForm.action = nomination.destroy_url;
      const token = document.createElement('input');
      token.type = 'hidden'; token.name = '_token'; token.value = csrf;
      const method = document.createElement('input');
      method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
      const button = document.createElement('button');
      button.type = 'submit'; button.className = 'btn btn-sm btn-outline-danger'; button.textContent = 'Remove';
      removeForm.append(token, method, button);
      row.append(name, removeForm);
      list.appendChild(row);
    });
  };
  player.select2({
    width: '100%',
    placeholder: 'Search by player name or surname',
    allowClear: true,
    closeOnSelect: false,
    minimumInputLength: 2,
    language: {
      inputTooShort: () => 'Type at least two characters to search.',
      searching: () => 'Searching players…',
      noResults: () => 'No existing players found.'
    },
    ajax: {
      url: @json(route('backend.interprovincial-trials.players.index', $event)),
      dataType: 'json',
      delay: 250,
      cache: true,
      data: params => ({ q: params.term, page: params.page || 1 }),
      processResults: data => data
    }
  });

  player.on('select2:select', function () {
    window.setTimeout(() => {
      const instance = player.data('select2');
      const searchFields = instance.$selection.add(instance.$dropdown).find('.select2-search__field');
      searchFields.val('').trigger('input');
      searchFields.filter(':visible').first().trigger('focus');
    }, 0);
  });

  $(form).on('submit', async function (event) {
    event.preventDefault();
    showFeedback('Adding nominations…');
    try {
      const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Nominations could not be added.'));
      rebuildCategory(data);
      player.val(null).trigger('change');
      showFeedback(data.message);
    } catch (error) {
      showFeedback(error.message || 'Nominations could not be added.', true);
    }
  });

  document.addEventListener('submit', async event => {
    const removeForm = event.target.closest('.nomination-remove-form');
    if (!removeForm || event.defaultPrevented) return;
    event.preventDefault();
    if (!confirm('Remove this nomination?')) return;
    showFeedback('Removing nomination…');
    try {
      const response = await fetch(removeForm.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(removeForm) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Nomination could not be removed.');
      rebuildCategory(data);
      showFeedback(data.message);
    } catch (error) {
      showFeedback(error.message || 'Nomination could not be removed.', true);
    }
  });
});
</script>
@endsection
