@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Series Settings')

{{-- Vendor styles --}}
@section('vendor-style')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

{{-- Vendor scripts --}}
@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@endsection

@section('page-style')
<style>
  .settings-section-title {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #a0aec0;
    margin-bottom: .75rem;
  }
  .toggle-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .75rem 1rem;
    border-radius: .5rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
  }
  .toggle-row + .toggle-row { margin-top: .5rem; }
  .toggle-row .toggle-info { flex: 1; }
  .toggle-row .toggle-info strong { font-size: .875rem; display: block; margin-bottom: .15rem; }
  .toggle-row .toggle-info small { color: #6c757d; font-size: .78rem; }
  /* Tab nav styling */
  .settings-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: .6rem 1.1rem;
    font-weight: 500;
    font-size: .9rem;
  }
  .settings-tabs .nav-link.active {
    color: #696cff;
    border-bottom-color: #696cff;
    background: transparent;
  }
  .settings-tabs .nav-link i { font-size: 1rem; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- PAGE HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0 fw-bold">Series Settings</h4>
      <div class="text-muted small mt-1">
        <i class="ti ti-tournament me-1"></i>{{ $series->name }} &middot; {{ $series->year }}
      </div>
    </div>
    <a href="{{ route('series.show', $series) }}" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i>Back to Series
    </a>
  </div>

  {{-- TABS CARD --}}
  <div class="card shadow-sm">

    {{-- Tab Navigation --}}
    <div class="card-header border-bottom p-0">
      <ul class="nav settings-tabs px-3" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-general" data-bs-toggle="tab" href="#pane-general" role="tab">
            <i class="ti ti-settings me-1"></i>General
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-points" data-bs-toggle="tab" href="#pane-points" role="tab">
            <i class="ti ti-trophy me-1"></i>Points
          </a>
        </li>
        @if($series->ranking_lists->isNotEmpty())
        <li class="nav-item">
          <a class="nav-link" id="tab-categories" data-bs-toggle="tab" href="#pane-categories" role="tab">
            <i class="ti ti-category me-1"></i>Categories
          </a>
        </li>
        @endif
      </ul>
    </div>

    {{-- Tab Panes --}}
    <div class="tab-content">

      {{-- ── GENERAL TAB ── --}}
      <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
        <div class="card-body" style="max-width: 640px;">
          <form id="series-settings-form">
            @csrf

            <div class="settings-section-title">Identity</div>
            <div class="row g-3 mb-4">
              <div class="col-8">
                <label class="form-label fw-semibold small mb-1">Series Name</label>
                <input type="text" name="name" class="form-control" required value="{{ $series->name }}">
              </div>
              <div class="col-4">
                <label class="form-label fw-semibold small mb-1">Year</label>
                <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{ $series->year }}">
              </div>
            </div>

            <div class="settings-section-title">Scoring</div>
            <div class="row g-3 mb-4">
              <div class="col-6">
                <label class="form-label fw-semibold small mb-1">Best Results Counted</label>
                <input type="number" name="best_num_of_scores" class="form-control" min="1" required value="{{ $series->best_num_of_scores }}">
                <div class="form-text">Series-wide default.</div>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold small mb-1">Rank Type</label>
                <select name="rank_type" class="form-select" required
                  {{ $series->points_template_created ? 'disabled title="Rank type cannot be changed after points have been applied."' : '' }}>
                  @foreach($rankTypes as $type)
                    <option value="{{ $type->id }}" {{ (int)$series->rank_type === (int)$type->id ? 'selected' : '' }}>
                      {{ $type->type }}
                    </option>
                  @endforeach
                </select>
                @if($series->points_template_created)
                  <div class="form-text text-warning"><i class="ti ti-lock me-1"></i>Locked – points template already created.</div>
                @endif
              </div>
            </div>

            <div class="settings-section-title">Visibility & Rules</div>

            <div class="toggle-row">
              <div class="toggle-info">
                <strong>Rankings Published</strong>
                <small>Controls whether the leaderboard is visible to the public.</small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="leaderboard_published" id="leaderboard_published"
                       {{ $series->leaderboard_published ? 'checked' : '' }}>
              </div>
            </div>

            <div class="toggle-row mt-2">
              <div class="toggle-info">
                <strong>Auto-Award Rule</strong>
                <small>A player who wins 2 of 3 legs is automatically awarded 1st place for any unplayed leg.</small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="auto_award_rule" id="auto_award_rule"
                       {{ ($series->auto_award_rule ?? true) ? 'checked' : '' }}>
              </div>
            </div>

          </form>
        </div>
        <div class="card-footer bg-white border-top d-flex justify-content-end">
          <button type="button" class="btn btn-primary" id="save-series-btn">
            <i class="ti ti-device-floppy me-1"></i>Save Settings
          </button>
        </div>
      </div>

      {{-- ── POINTS TAB ── --}}
      <div class="tab-pane fade" id="pane-points" role="tabpanel">
        <div class="card-body pb-0">
          <p class="text-muted small mb-3">Define points awarded per finishing position (1–{{ count($positions) }}).</p>
        </div>
        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th class="ps-4" style="width:130px;">Position</th>
                <th>Points Awarded</th>
              </tr>
            </thead>
            <tbody>
              @foreach($positions as $pos)
                @php $point = optional($series->points->firstWhere('position', $pos))->score ?? 0; @endphp
                <tr>
                  <td class="ps-4 align-middle">
                    <span class="badge bg-label-secondary">#{{ $pos }}</span>
                  </td>
                  <td class="align-middle py-1">
                    <input type="number" class="form-control form-control-sm point-input"
                           data-position="{{ $pos }}" min="0" value="{{ $point }}"
                           style="max-width:120px;">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-white border-top d-flex justify-content-end">
          <button type="button" class="btn btn-success" id="save-points-btn">
            <i class="ti ti-device-floppy me-1"></i>Save Points
          </button>
        </div>
      </div>

      {{-- ── CATEGORIES TAB ── --}}
      @if($series->ranking_lists->isNotEmpty())
      <div class="tab-pane fade" id="pane-categories" role="tabpanel">
        <div class="card-body pb-0">
          <p class="text-muted small mb-3">
            Override the number of best results counted per category. Leave blank to use the series default
            <strong>({{ $series->best_num_of_scores }})</strong>.
          </p>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Category</th>
                <th style="width:220px;">Best N Events</th>
                <th style="width:70px;"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($series->ranking_lists as $rl)
              <tr>
                <td class="ps-4 align-middle">{{ $rl->category?->name ?? 'Category #'.$rl->category_id }}</td>
                <td class="align-middle py-1">
                  <input type="number" class="form-control form-control-sm cat-best-input"
                         data-id="{{ $rl->id }}" min="1" max="99"
                         placeholder="{{ $series->best_num_of_scores }} (default)"
                         value="{{ $rl->best_num_of_scores ?? '' }}">
                </td>
                <td class="align-middle">
                  <button class="btn btn-sm btn-outline-success btn-save-cat-best" data-id="{{ $rl->id }}" title="Save this row">
                    <i class="ti ti-device-floppy"></i>
                  </button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-white border-top d-flex justify-content-end">
          <button class="btn btn-info btn-sm" id="btn-save-all-cat-best">
            <i class="ti ti-device-floppy me-1"></i>Save All Categories
          </button>
        </div>
      </div>
      @endif

    </div>{{-- /tab-content --}}
  </div>{{-- /card --}}

</div>
@endsection

@section('page-script')
<script>
  toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 2500
  };

  // ── General Settings ──────────────────────────────────
  document.getElementById('save-series-btn').addEventListener('click', () => {
    const btn = document.getElementById('save-series-btn');
    btn.disabled = true;

    fetch('{{ route('ranking.series.update', $series) }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({
        name:                 document.querySelector('[name="name"]').value,
        year:                 document.querySelector('[name="year"]').value,
        best_num_of_scores:   document.querySelector('[name="best_num_of_scores"]').value,
        rank_type:            document.querySelector('[name="rank_type"]:not([disabled])') ? document.querySelector('[name="rank_type"]').value : null,
        leaderboard_published: document.getElementById('leaderboard_published').checked ? 1 : 0,
        auto_award_rule:      document.getElementById('auto_award_rule').checked ? 1 : 0,
      })
    })
    .then(r => r.json())
    .then(r => toastr.success(r.message || 'Series settings saved'))
    .catch(() => toastr.error('Failed to save series settings'))
    .finally(() => btn.disabled = false);
  });

  // ── Points Allocation ─────────────────────────────────
  document.getElementById('save-points-btn').addEventListener('click', () => {
    const btn = document.getElementById('save-points-btn');
    btn.disabled = true;

    const points = [];
    document.querySelectorAll('.point-input').forEach(input => {
      points.push({ position: Number(input.dataset.position), score: Number(input.value || 0) });
    });

    fetch('{{ route('ranking.points.update', $series) }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
      body: JSON.stringify({ points })
    })
    .then(r => r.json())
    .then(r => toastr.success(r.message || 'Points saved successfully'))
    .catch(() => toastr.error('Failed to save points'))
    .finally(() => btn.disabled = false);
  });

  // ── Per-Category Best-N ───────────────────────────────
  const catBestUrl = '{{ route('series.category-best-num', $series) }}';
  const csrfToken  = '{{ csrf_token() }}';

  function saveCatBest(payload, btn) {
    if (btn) btn.disabled = true;
    fetch(catBestUrl, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ category_best: payload })
    })
    .then(r => r.json())
    .then(r => toastr.success(r.message || 'Saved'))
    .catch(() => toastr.error('Failed to save'))
    .finally(() => { if (btn) btn.disabled = false; });
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-save-cat-best');
    if (!btn) return;
    const id  = btn.dataset.id;
    const val = document.querySelector(`.cat-best-input[data-id="${id}"]`).value;
    saveCatBest({ [id]: val || null }, btn);
  });

  const saveAllBtn = document.getElementById('btn-save-all-cat-best');
  if (saveAllBtn) {
    saveAllBtn.addEventListener('click', function () {
      const payload = {};
      document.querySelectorAll('.cat-best-input').forEach(input => {
        payload[input.dataset.id] = input.value || null;
      });
      saveCatBest(payload, saveAllBtn);
    });
  }

  // ── Persist active tab across page loads ──────────────
  const tabLinks = document.querySelectorAll('.settings-tabs .nav-link');
  const savedTab = localStorage.getItem('seriesSettingsTab');
  if (savedTab) {
    const target = document.querySelector(`.settings-tabs .nav-link[href="${savedTab}"]`);
    if (target) bootstrap.Tab.getOrCreateInstance(target).show();
  }
  tabLinks.forEach(link => {
    link.addEventListener('shown.bs.tab', () => {
      localStorage.setItem('seriesSettingsTab', link.getAttribute('href'));
    });
  });
</script>
@endsection
