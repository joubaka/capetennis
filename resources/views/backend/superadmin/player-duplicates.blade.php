@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-user-search me-2 text-primary"></i>Duplicate Player Review</h4>
      <p class="text-muted mb-0">Each pair is a candidate only. A Super Admin must compare identity and linked history before merging.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('superadmin.player-duplicates.index', ['merge_filter' => 'ranking_2026', 'per_page' => 'all']) }}" class="btn btn-warning btn-sm">
        <i class="ti ti-trophy me-1"></i>Find 2026 ranking duplicates
      </a>
      <button type="button" id="toggle-quick-candidates" class="btn btn-outline-primary btn-sm" aria-pressed="false">
        <i class="ti ti-bolt me-1"></i>Quick candidates only
      </button>
      <a href="{{ route('superadmin.player-duplicates.index', array_filter(['include_reviewed' => $includeReviewed ? 0 : 1, 'per_page' => $perPageOption, 'merge_filter' => $mergeFilter !== 'all' ? $mergeFilter : null])) }}" class="btn btn-outline-secondary btn-sm">
        {{ $includeReviewed ? 'Hide reviewed' : 'Show reviewed' }}
      </a>
      <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">Back to Super Admin</a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="ti ti-shield-check fs-4"></i>
    <div><strong>Protected bulk merging.</strong> Select any candidate to use its recommended keep/remove plan, or review every quick candidate across all pages. Every selected plan is checked for identity, linked history, tournament results, rankings and financial collisions before confirmation.</div>
  </div>

  <div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-end gap-3 py-3">
      <div>
        <div class="fw-semibold">Candidate list</div>
        <div class="small text-muted">Showing {{ $candidatePairs->count() }} of {{ $candidatePairs->total() }} matching pairs.</div>
      </div>
      <div class="d-flex flex-wrap align-items-end gap-2">
        <div class="btn-group" role="group" aria-label="Candidate merge filter">
          <a href="{{ route('superadmin.player-duplicates.index', array_filter(['include_reviewed' => $includeReviewed ? 1 : null, 'per_page' => $perPageOption])) }}"
             class="btn btn-sm {{ $mergeFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All candidates</a>
          <a href="{{ route('superadmin.player-duplicates.index', array_filter(['include_reviewed' => $includeReviewed ? 1 : null, 'per_page' => $perPageOption, 'merge_filter' => 'auto_resolvable'])) }}"
             class="btn btn-sm {{ in_array($mergeFilter, ['auto_resolvable', 'ranking_auto'], true) ? 'btn-warning' : 'btn-outline-warning' }}">
            <i class="ti ti-wand me-1"></i>Can resolve automatically
          </a>
          <a href="{{ route('superadmin.player-duplicates.index', array_filter(['include_reviewed' => $includeReviewed ? 1 : null, 'per_page' => 'all', 'merge_filter' => 'ranking_2026'])) }}"
             class="btn btn-sm {{ $mergeFilter === 'ranking_2026' ? 'btn-warning' : 'btn-outline-warning' }}">
            <i class="ti ti-trophy me-1"></i>2026 ranking duplicates
          </a>
        </div>
        <form method="GET" action="{{ route('superadmin.player-duplicates.index') }}" class="d-flex align-items-end gap-2">
          @if($includeReviewed)<input type="hidden" name="include_reviewed" value="1">@endif
          @if($mergeFilter !== 'all')<input type="hidden" name="merge_filter" value="{{ $mergeFilter }}">@endif
          <div>
            <label for="duplicate-page-size" class="form-label small mb-1">Records per page</label>
            <select id="duplicate-page-size" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
              @foreach(['25' => '25', '50' => '50', '100' => '100', '200' => '200', 'all' => 'All matching (max 400)'] as $value => $label)
                <option value="{{ $value }}" @selected($perPageOption === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <noscript><button class="btn btn-outline-secondary btn-sm" type="submit">Apply</button></noscript>
        </form>
      </div>
    </div>
  </div>

  <form id="bulk-merge-selection" method="POST" action="{{ route('superadmin.player-duplicates.bulk-review') }}" class="card mb-4">
    @csrf
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 py-3">
      <label class="form-check mb-0">
        <input class="form-check-input" type="checkbox" id="select-all-quick" autocomplete="off">
        <span class="form-check-label"><strong>Select all visible suggested plans</strong><span class="d-block small text-muted">Checked cards use the displayed “Recommended keep” direction. The all-pages button remains limited to quick candidates.</span></span>
      </label>
      <div class="d-flex flex-wrap gap-2">
        <button type="submit" id="review-all-merges" name="selection_scope" value="all" data-selection-scope="all" class="btn btn-primary">
          <i class="ti ti-stack-2 me-1"></i>Review all quick candidates
        </button>
        <button type="submit" id="review-selected-merges" name="selection_scope" value="page" data-selection-scope="page" class="btn btn-danger" disabled>
          <i class="ti ti-git-merge me-1"></i>Review selected (<span id="selected-merge-count">0</span>)
        </button>
        @if($mergeFilter === 'ranking_2026')
          <button type="submit" id="review-all-2026-ranking-merges" name="selection_scope" value="page" class="btn btn-warning" onclick="document.querySelectorAll('.js-bulk-pair').forEach(function (box) { box.checked = true; });">
            <i class="ti ti-trophy me-1"></i>Review all 2026 ranking duplicates
          </button>
        @endif
      </div>
      <div id="bulk-review-progress" class="d-none w-100" role="status" aria-live="polite">
        <div class="d-flex justify-content-between align-items-center small mb-1">
          <strong id="bulk-review-progress-label">Preparing duplicate safety review…</strong>
          <span id="bulk-review-progress-percent">0%</span>
        </div>
        <div class="progress" style="height: 10px;">
          <div id="bulk-review-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
        </div>
        <div id="bulk-review-progress-detail" class="small text-muted mt-1"></div>
      </div>
    </div>
  </form>

  @forelse($candidatePairs as $pair)
    @php($first = $pair->first)
    @php($second = $pair->second)
    <div class="card mb-4 border-{{ $pair->confidence['class'] }} duplicate-candidate-card" data-quick-candidate="{{ $pair->quick_merge ? '1' : '0' }}" data-ranking-auto-candidate="{{ $pair->ranking_auto_merge ? '1' : '0' }}">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <strong>{{ $first['player']->full_name }}</strong>
          <span class="text-muted">— profiles #{{ $first['player']->id }} and #{{ $second['player']->id }}</span>
          <span class="badge bg-label-primary ms-1">Recommended keep #{{ $pair->recommended_keep_id }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check mb-0 me-1">
            <input class="form-check-input js-bulk-pair" form="bulk-merge-selection" type="checkbox" name="pairs[]" value="{{ $first['player']->id }}:{{ $second['player']->id }}" autocomplete="off" aria-label="Use the suggested merge plan for profiles #{{ $first['player']->id }} and #{{ $second['player']->id }}">
            <span class="form-check-label small">Use suggested</span>
          </label>
          @if($pair->quick_merge)
            <span class="badge bg-label-primary"><i class="ti ti-bolt me-1"></i>Quick merge eligible</span>
          @endif
          @if($pair->ranking_auto_merge)
            <span class="badge bg-label-warning"><i class="ti ti-refresh me-1"></i>Calculated ranking rebuild eligible</span>
          @endif
          @if($pair->decision)
            <span class="badge bg-label-secondary">{{ str_replace('_', ' ', ucfirst($pair->decision->decision)) }}</span>
          @endif
          <span class="badge bg-label-{{ $pair->confidence['class'] }}">{{ $pair->confidence['label'] }}</span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Profile</th><th>Identity</th><th>Contact and accounts</th><th>Linked history</th></tr></thead>
          <tbody>
          @foreach([$first, $second] as $item)
            <tr>
              <td><strong>#{{ $item['player']->id }}</strong><div class="small text-muted">Created {{ optional($item['player']->created_at)->format('Y-m-d') ?: 'unknown' }}</div></td>
              <td>
                <div>{{ $item['player']->full_name }}</div>
                <div class="small">DOB: {{ $item['player']->dateOfBirth ?: 'Not set' }}</div>
                <div class="small">Gender: {{ $item['player']->gender ?: 'Not set' }}</div>
              </td>
              <td>
                <div class="small">{{ $item['player']->email ?: 'No player email' }}</div>
                <div class="small">{{ $item['player']->cellNr ?: 'No cellphone' }}</div>
                @if($item['player']->user)
                  <div class="small text-primary mt-1"><strong>Created by user #{{ $item['player']->user->id }}</strong></div>
                  <div class="small text-primary">{{ $item['player']->user->name ?: trim(($item['player']->user->userName ?? '').' '.($item['player']->user->userSurname ?? '')) ?: 'Unnamed user' }}</div>
                  <div class="small text-primary">{{ $item['player']->user->email ?: 'No user email' }}{{ $item['player']->user->cell_nr ? ' · '.$item['player']->user->cell_nr : '' }}</div>
                @else
                  <div class="small text-muted mt-1">Created by: no linked user</div>
                @endif
                @forelse($item['owners'] as $owner)
                  <div class="small text-muted">{{ $owner['name'] }} ({{ $owner['email'] }})</div>
                @empty
                  <div class="small text-muted">No linked user</div>
                @endforelse
              </td>
              <td>
                @forelse($item['usage'] as $table => $count)
                  <span class="badge bg-label-secondary me-1 mb-1">{{ str_replace('_', ' ', $table) }}: {{ $count }}</span>
                @empty
                  <span class="badge bg-label-success">No linked history</span>
                @endforelse
                @if(!empty($item['events']))
                  <details class="mt-2 small">
                    <summary class="text-primary">Events participated in ({{ count($item['events']) }})</summary>
                    <div class="mt-2">
                      @foreach($item['events'] as $event)
                        <div class="border rounded p-2 mb-2">
                          <strong>{{ $event['event_name'] ?: 'Event #'.$event['event_id'] }}</strong>
                          <div class="text-muted">{{ $event['start_date'] ?: 'Date unknown' }}{{ $event['end_date'] ? ' to '.$event['end_date'] : '' }} · {{ $event['registrations'] }} registration(s)</div>
                          @foreach($event['categories'] as $category)
                            <span class="badge bg-label-{{ $category['withdrawn'] ? 'secondary' : 'success' }} me-1 mt-1">
                              {{ $category['category_name'] }} · {{ $category['withdrawn'] ? 'withdrawn' : ($category['status'] ?: 'entered') }}
                            </span>
                          @endforeach
                        </div>
                      @endforeach
                    </div>
                  </details>
                @else
                  <div class="small text-muted mt-2">No event registrations found</div>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted">{{ implode('. ', $pair->confidence['reasons']) }}</span>
        <div class="d-flex gap-2">
          @if($pair->quick_merge)
            <button type="button"
                    class="btn btn-primary btn-sm js-quick-merge"
                    data-quick-review-url="{{ route('superadmin.player-duplicates.quick-review', [$first['player'], $second['player']]) }}">
              <i class="ti ti-bolt me-1"></i>Quick merge into #{{ $pair->quick_merge['keep_id'] }}
            </button>
          @endif
          <a href="{{ route('superadmin.player-duplicates.review', [$first['player'], $second['player']]) }}" class="btn btn-outline-primary btn-sm">
            Compare and review
          </a>
          <a href="{{ route('superadmin.player-duplicates.review', [$first['player'], $second['player']]) }}?published=1" class="btn btn-outline-warning btn-sm">
            <i class="ti ti-refresh me-1"></i>2026 published merge
          </a>
        </div>
      </div>
    </div>
  @empty
    <div class="card"><div class="card-body text-center py-5">
      <i class="ti ti-circle-check fs-1 text-success"></i>
      <h5 class="mt-2">{{ $mergeFilter === 'ranking_auto' ? 'No auto-resolvable ranking collisions' : 'No unreviewed duplicate candidates' }}</h5>
      <p class="text-muted mb-0">{{ $mergeFilter === 'ranking_auto' ? 'No candidate currently has a calculated-only ranking collision that can be rebuilt safely.' : 'Use “Show reviewed” to inspect deferred or rejected pairs.' }}</p>
    </div></div>
  @endforelse

  <div id="no-quick-candidates" class="card d-none"><div class="card-body text-center py-4 text-muted">
    No quick-merge candidates are shown on this page. Turn off the filter or continue to the next page.
  </div></div>

  {{ $candidatePairs->links('pagination::bootstrap-5') }}

  @if($recentMerges->isNotEmpty())
    <div class="card mt-4">
      <div class="card-header"><strong>Recent completed merges</strong></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>When</th><th>Source</th><th>Canonical</th><th>Approved by</th><th>Reason</th></tr></thead>
          <tbody>
          @foreach($recentMerges as $merge)
            <tr>
              <td>{{ optional($merge->merged_at)->format('Y-m-d H:i') }}</td>
              <td>#{{ $merge->removed_player_id }}</td>
              <td>#{{ $merge->kept_player_id }}</td>
              <td>{{ $merge->approvedBy?->name ?: 'User #'.$merge->approved_by }}</td>
              <td>{{ $merge->reason }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>

<div class="modal fade" id="quick-merge-modal" tabindex="-1" aria-labelledby="quick-merge-modal-title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quick-merge-modal-title"><i class="ti ti-bolt me-2 text-primary"></i>Quick duplicate merge</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="quick-merge-modal-body">
        <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted">Running the full safety check…</div></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalElement = document.getElementById('quick-merge-modal');
  var modalBody = document.getElementById('quick-merge-modal-body');
  var quickModal = new bootstrap.Modal(modalElement);
  var loading = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted">Running the full safety check…</div></div>';

  document.querySelectorAll('.js-quick-merge').forEach(function (button) {
    button.addEventListener('click', function () {
      modalBody.innerHTML = loading;
      quickModal.show();
      fetch(button.dataset.quickReviewUrl, {
        headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function (response) {
        if (!response.ok) throw new Error('Unable to load the safety review.');
        return response.text();
      }).then(function (html) {
        modalBody.innerHTML = html;
      }).catch(function () {
        modalBody.innerHTML = '<div class="alert alert-danger mb-0">The quick safety review could not be loaded. Close this window and use Compare and review.</div>';
      });
    });
  });

  var filterButton = document.getElementById('toggle-quick-candidates');
  var cards = Array.from(document.querySelectorAll('.duplicate-candidate-card'));
  var emptyState = document.getElementById('no-quick-candidates');
  filterButton.addEventListener('click', function () {
    var active = filterButton.getAttribute('aria-pressed') !== 'true';
    filterButton.setAttribute('aria-pressed', active ? 'true' : 'false');
    filterButton.classList.toggle('btn-primary', active);
    filterButton.classList.toggle('btn-outline-primary', !active);
    var visible = 0;
    cards.forEach(function (card) {
      var show = !active || card.dataset.quickCandidate === '1';
      card.classList.toggle('d-none', !show);
      if (show) visible++;
    });
    emptyState.classList.toggle('d-none', visible > 0);
    updateBulkSelection();
  });

  var pairCheckboxes = Array.from(document.querySelectorAll('.js-bulk-pair'));
  var bulkForm = document.getElementById('bulk-merge-selection');
  var selectAll = document.getElementById('select-all-quick');
  var reviewAllButton = document.getElementById('review-all-merges');
  var bulkButton = document.getElementById('review-selected-merges');
  var selectedCount = document.getElementById('selected-merge-count');
  var progressPanel = document.getElementById('bulk-review-progress');
  var progressBar = document.getElementById('bulk-review-progress-bar');
  var progressPercent = document.getElementById('bulk-review-progress-percent');
  var progressLabel = document.getElementById('bulk-review-progress-label');
  var progressDetail = document.getElementById('bulk-review-progress-detail');
  function visiblePairCheckboxes() {
    return pairCheckboxes.filter(function (checkbox) {
      var card = checkbox.closest('.duplicate-candidate-card');
      return card && !card.classList.contains('d-none');
    });
  }
  function updateBulkSelection() {
    var count = pairCheckboxes.filter(function (checkbox) { return checkbox.checked; }).length;
    var visibleCheckboxes = visiblePairCheckboxes();
    var visibleChecked = visibleCheckboxes.filter(function (checkbox) { return checkbox.checked; }).length;
    selectedCount.textContent = count;
    bulkButton.disabled = count === 0;
    selectAll.checked = visibleCheckboxes.length > 0 && visibleChecked === visibleCheckboxes.length;
    selectAll.indeterminate = visibleChecked > 0 && visibleChecked < visibleCheckboxes.length;
  }
  selectAll.addEventListener('change', function () {
    visiblePairCheckboxes().forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
    updateBulkSelection();
  });
  pairCheckboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', updateBulkSelection);
  });
  bulkForm.addEventListener('submit', function (event) {
    var count = pairCheckboxes.filter(function (checkbox) { return checkbox.checked; }).length;
    var scope = event.submitter && event.submitter.dataset.selectionScope === 'all' ? 'all' : 'page';
    if ((scope === 'page' && count === 0) || !window.fetch) return;
    event.preventDefault();
    var formData = new FormData(bulkForm);
    formData.set('selection_scope', scope);

    bulkButton.disabled = true;
    reviewAllButton.disabled = true;
    selectAll.disabled = true;
    pairCheckboxes.forEach(function (checkbox) { checkbox.disabled = true; });
    progressPanel.classList.remove('d-none');
    progressDetail.textContent = scope === 'all'
      ? 'Finding every unreviewed quick candidate across all pages, then running identity, history, tournament result, ranking and financial collision checks.'
      : 'Running identity, history, tournament result, ranking and financial collision checks for ' + count + ' selected candidate' + (count === 1 ? '' : 's') + '.';

    var progress = 2;
    var startedAt = Date.now();
    var requestController = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var progressUnits = scope === 'all' ? 12 : Math.max(1, count);
    function renderProgress(value) {
      progress = Math.max(progress, Math.min(100, value));
      progressBar.style.width = progress + '%';
      progressBar.setAttribute('aria-valuenow', String(progress));
      progressPercent.textContent = progress + '%';
    }
    renderProgress(progress);
    var progressTimer = window.setInterval(function () {
      var elapsedSeconds = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
      var increment = Math.max(1, Math.round((90 - progress) / Math.max(5, progressUnits)));
      renderProgress(Math.min(90, progress + increment));
      progressLabel.textContent = progress >= 90
        ? 'Finalizing the server safety review…'
        : 'Checking duplicate candidates…';
      progressPercent.textContent = progress + '% · ' + elapsedSeconds + 's';
    }, 450);
    var requestTimeout = window.setTimeout(function () {
      if (requestController) requestController.abort();
    }, 60000);

    fetch(bulkForm.action, {
      method: 'POST',
      body: formData,
      headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal: requestController ? requestController.signal : undefined
    }).then(function (response) {
      return response.text().then(function (html) {
        if (!response.ok) {
          var error = new Error('The server returned HTTP ' + response.status + '. No players were merged.');
          error.status = response.status;
          throw error;
        }
        return html;
      });
    }).then(function (html) {
      window.clearInterval(progressTimer);
      window.clearTimeout(requestTimeout);
      progressLabel.textContent = 'Safety review ready';
      progressDetail.textContent = 'Opening the ready and skipped candidate summary…';
      renderProgress(100);
      window.setTimeout(function () {
        document.open();
        document.write(html);
        document.close();
      }, 250);
    }).catch(function (error) {
      window.clearInterval(progressTimer);
      window.clearTimeout(requestTimeout);
      progressBar.classList.remove('progress-bar-animated');
      progressBar.classList.add('bg-danger');
      var elapsedSeconds = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
      progressPercent.textContent = 'Failed · ' + elapsedSeconds + 's';
      if (error && error.name === 'AbortError') {
        progressLabel.textContent = 'Server review stopped after 60 seconds';
        progressDetail.textContent = 'No players were merged. Reload the page before retrying; if this repeats, review smaller selections.';
      } else {
        progressLabel.textContent = 'Server review failed';
        progressDetail.textContent = error && error.message
          ? error.message
          : 'No players were merged. Reload the page and try again.';
      }
      bulkButton.disabled = false;
      reviewAllButton.disabled = false;
      selectAll.disabled = false;
      pairCheckboxes.forEach(function (checkbox) { checkbox.disabled = false; });
    });
  });
  updateBulkSelection();
  window.addEventListener('pageshow', updateBulkSelection);
  window.setTimeout(updateBulkSelection, 0);
});
</script>
@endsection
