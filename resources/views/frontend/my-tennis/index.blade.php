@extends('layouts/contentNavbarLayout')

@section('title', 'My Tennis')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div id="my-tennis-page" class="container-xxl flex-grow-1 container-p-y">
  <style>
    #my-tennis-page { min-width: 0; overflow-x: clip; }
    #my-tennis-page .row, #my-tennis-page .card, #my-tennis-page .card-body { min-width: 0; }
    #my-tennis-page .table-responsive { max-width: 100%; }
    #my-tennis-tabs { overflow-x: auto; flex-wrap: nowrap; }
    #my-tennis-tabs .nav-item { flex: 1 1 0; min-width: 11rem; }
    #my-tennis-tabs .nav-link { white-space: nowrap; width: 100%; }
    #my-tennis-player + .select2-container { min-width: 11rem; max-width: 100%; }
    #my-tennis-page .select2-dropdown { max-width: min(18rem, calc(100vw - 2rem)); }
    .my-tennis-manage-card .player-chip { align-items: center; display: flex; gap: .75rem; justify-content: space-between; }
    .my-tennis-manage-card .player-chip + .player-chip { border-top: 1px solid rgba(75, 70, 92, .12); padding-top: .75rem; }
    .my-tennis-manage-card .player-chip + .player-chip { margin-top: .75rem; }
  </style>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">My Tennis</h4>
      <p class="text-muted mb-0">Your players, entries and published match information.</p>
    </div>
    @if($players->isNotEmpty())
      <form method="get" action="{{ route('my.tennis') }}" class="d-flex align-items-center gap-2">
        <label for="my-tennis-player" class="visually-hidden">Player</label>
        <select id="my-tennis-player" name="player" class="form-select my-tennis-player-select" onchange="this.form.submit()">
          @foreach($players as $playerOption)
            <option value="{{ $playerOption->id }}" @selected($selectedPlayer?->id === $playerOption->id)>{{ $playerOption->full_name }}</option>
          @endforeach
        </select>
      </form>
    @endif
  </div>

  <ul class="nav nav-pills nav-fill bg-white rounded shadow-sm p-2 mb-4" id="my-tennis-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" id="my-tennis-overview-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-overview" type="button" role="tab" aria-controls="my-tennis-overview" aria-selected="true"><i class="ti ti-dashboard me-1" aria-hidden="true"></i>Overview</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-history-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-history" type="button" role="tab" aria-controls="my-tennis-history" aria-selected="false"><i class="ti ti-clipboard-list me-1" aria-hidden="true"></i>Entries & history</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" id="my-tennis-manage-tab" data-bs-toggle="pill" data-bs-target="#my-tennis-manage" type="button" role="tab" aria-controls="my-tennis-manage" aria-selected="false"><i class="ti ti-users me-1" aria-hidden="true"></i>Manage players</button></li>
  </ul>

  <div class="tab-content" id="my-tennis-tab-content">
  <div class="tab-pane fade show active" id="my-tennis-overview" role="tabpanel" aria-labelledby="my-tennis-overview-tab">
  @if(!$selectedPlayer)
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-5 text-center">
        <div class="avatar avatar-lg mx-auto mb-3"><span class="avatar-initial rounded bg-label-primary"><i class="ti ti-user-plus fs-3"></i></span></div>
        <h5 class="mb-2">Your tennis dashboard is ready</h5>
        <p class="text-muted mb-3">Link a player profile to see entries, matches, results and rankings here.</p>
        <button class="btn btn-primary" type="button" data-my-tennis-open-manage><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Link a player</button>
      </div>
    </div>
  @else
    <div class="row g-4 mb-4">
      <div class="col-12 col-lg-4">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-3"><div><p class="text-muted small mb-1">Selected player</p><h5 class="mb-2">{{ $selectedPlayer->full_name }}</h5></div><span class="badge bg-label-{{ $profile['badge'] }}">{{ $profile['message'] }}</span></div>
          <p class="text-muted small mb-0">Your tennis activity and published information for this player.</p>
        </div></div>
      </div>
      <div class="col-12 col-lg-8">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><h5 class="mb-0">Upcoming matches</h5><i class="ti ti-calendar-event text-primary fs-4" aria-hidden="true"></i></div>
          @forelse($upcomingMatches as $match)
            <div class="border-bottom py-2"><div class="fw-semibold">{{ $match->draw?->event?->name ?? 'Published draw' }}</div><div class="small text-muted">{{ $match->scheduled ?: 'Time to be confirmed' }} · {{ $match->venue?->name ?? 'Court to be confirmed' }}</div></div>
          @empty
            <p class="text-muted mb-0">No published upcoming matches found.</p>
          @endforelse
        </div></div>
      </div>
    </div>
  @endif
  </div>

  @if($selectedPlayer)
  <div class="tab-pane fade" id="my-tennis-history" role="tabpanel" aria-labelledby="my-tennis-history-tab">
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm"><div class="card-body">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h5 class="mb-0">Entries</h5><i class="ti ti-clipboard-list text-primary fs-4" aria-hidden="true"></i></div>
          <p class="text-muted small mb-3">Only paid entries are confirmed and eligible for an event. An unpaid record is not an event entry until payment is completed.</p>
          <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Event</th><th>Category</th><th>Status</th><th>Payment</th></tr></thead><tbody>
          @forelse($entries as $entry)
            <tr><td>{{ $entry->categoryEvent?->event?->name ?? 'Event' }}</td><td>{{ $entry->categoryEvent?->category?->name ?? 'Category' }}</td><td>{{ $entry->status ?: 'Active' }}</td><td>{{ $entry->is_paid ? 'Paid' : 'Not paid — entry not confirmed' }}</td></tr>
          @empty
            <tr><td colspan="4" class="text-muted">No entries found for this player.</td></tr>
          @endforelse
          </tbody></table></div>
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h5>Recent published results</h5>
          @forelse($history['placements'] as $placement)<div class="border-bottom py-2"><div class="fw-semibold">{{ $placement->categoryEvent?->event?->name ?? 'Event' }}</div><div class="small text-muted">{{ $placement->categoryEvent?->category?->name ?? 'Category' }} · Place {{ $placement->position }}</div></div>@empty<p class="text-muted mb-0">No published results yet.</p>@endforelse
        </div></div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0 shadow-sm"><div class="card-body"><h5>Current rankings</h5>
          @forelse($history['seriesRankings'] as $ranking)<div class="border-bottom py-2"><div class="fw-semibold">{{ $ranking->series?->name ?? 'Series' }}</div><div class="small text-muted">{{ $ranking->category?->name ?? 'Category' }} · #{{ $ranking->rank_position }} · {{ $ranking->total_points }} points</div></div>@empty<p class="text-muted mb-0">No published series ranking yet.</p>@endforelse
        </div></div>
      </div>
    </div>
  </div>
  @endif

  <div class="tab-pane fade" id="my-tennis-manage" role="tabpanel" aria-labelledby="my-tennis-manage-tab">
  <div class="card my-tennis-manage-card mb-4 border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
          <h5 class="mb-1">Manage player profiles</h5>
          <p class="text-muted mb-0">Link another player or manage the profiles connected to your account.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button id="my-tennis-bulk-unlink" class="btn btn-outline-danger" type="button" disabled><i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected</button>
          <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#link-player-panel" aria-expanded="false" aria-controls="link-player-panel"><i class="ti ti-user-plus me-1" aria-hidden="true"></i>Link a player</button>
        </div>
      </div>
      <div class="mb-3" id="my-tennis-link-feedback" role="status" aria-live="polite"></div>
      <div class="collapse" id="link-player-panel">
        <div class="border rounded p-3 mb-3">
          <div class="alert alert-info d-flex gap-2 align-items-start mb-3"><i class="ti ti-shield-check fs-4" aria-hidden="true"></i><div><strong>Safe linking</strong><br><span class="small">Search finds the profile, but it is only linked after you verify the player’s date of birth and the email or mobile number recorded for that profile. We never display that private value in search results.</span></div></div>
          <label for="my-tennis-player-search" class="form-label">1. Find the player profile</label>
          <div class="input-group">
            <input id="my-tennis-player-search" class="form-control" type="search" minlength="2" maxlength="100" placeholder="Search by name or email">
            <button id="my-tennis-player-search-button" class="btn btn-outline-primary" type="button">Search</button>
          </div>
          <div id="my-tennis-player-results" class="list-group mt-3"></div>
          <div class="row g-2 mt-2"><div class="col-12 col-md-5"><label for="my-tennis-link-dob" class="form-label small mb-1">2. Player date of birth</label><input id="my-tennis-link-dob" type="date" class="form-control"></div><div class="col-12 col-md-7"><label for="my-tennis-link-contact" class="form-label small mb-1">3. Recorded email or mobile number</label><input id="my-tennis-link-contact" type="text" class="form-control" placeholder="Enter it exactly as recorded"></div></div>
          <p class="form-text mb-0 mt-2">If the details are outdated or you do not recognise the profile, contact Cape Tennis support.</p>
        </div>
      </div>
      <div id="my-tennis-linked-players">
        @forelse($linkedPlayerPage->items() as $playerOption)
          <div class="player-chip" data-player-row="{{ $playerOption->id }}">
            <div>
              <input class="form-check-input my-tennis-player-check me-2" type="checkbox" value="{{ $playerOption->id }}" aria-label="Select {{ $playerOption->full_name }} for bulk unlink">
              <div class="fw-semibold">{{ $playerOption->full_name }}</div>
              @if(in_array((int) $playerOption->id, $linkedPlayerIds, true))
                <small class="text-muted">Linked to this account</small>
              @else
                <small class="text-muted">Legacy account link</small>
              @endif
            </div>
            <button class="btn btn-sm btn-outline-danger my-tennis-unlink" type="button" data-player-id="{{ $playerOption->id }}" data-player-name="{{ $playerOption->full_name }}">
              <i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink
            </button>
          </div>
        @empty
          <p class="text-muted mb-0">No linked players yet. Search above to link a player profile.</p>
        @endforelse
      </div>
      @if($linkedPlayerPage->hasMorePages())
        <button id="my-tennis-load-more" class="btn btn-outline-secondary w-100 mt-3" type="button" data-next-page="2">Load more players</button>
      @endif
    </div>
  </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
(() => {
  const playerSelect = document.getElementById('my-tennis-player');
  if (playerSelect && window.jQuery?.fn?.select2) {
    window.jQuery(playerSelect).select2({ width: '100%', minimumResultsForSearch: 0, placeholder: 'Choose a player' });
  }
  document.querySelector('[data-my-tennis-open-manage]')?.addEventListener('click', () => {
    document.getElementById('my-tennis-manage-tab')?.click();
    window.setTimeout(() => document.querySelector('[data-bs-target="#link-player-panel"]')?.click(), 150);
  });
  const searchInput = document.getElementById('my-tennis-player-search');
  const searchButton = document.getElementById('my-tennis-player-search-button');
  const results = document.getElementById('my-tennis-player-results');
  const feedback = document.getElementById('my-tennis-link-feedback');
  const linkDob = document.getElementById('my-tennis-link-dob');
  const linkContact = document.getElementById('my-tennis-link-contact');
  const linkedPlayers = document.getElementById('my-tennis-linked-players');
  const bulkButton = document.getElementById('my-tennis-bulk-unlink');
  const loadMoreButton = document.getElementById('my-tennis-load-more');
  if (!searchInput || !searchButton || !results || !feedback) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const searchUrl = @json(route('player.search'));
  const linkUrl = @json(route('backend.user.players.store', $accountUser));
  const unlinkUrl = @json(route('backend.user.players.destroy', [$accountUser, '__PLAYER__']));
  const bulkUnlinkUrl = @json(route('backend.user.players.bulk-destroy', $accountUser));
  const playersUrl = @json(route('my.tennis.players'));
  const linkedIds = new Set(@json(array_map('intval', $linkedPlayerIds)));

  const showFeedback = (message, type = 'info') => {
    feedback.innerHTML = `<div class="alert alert-${type} py-2 mb-0">${message}</div>`;
  };

  const selectedIds = () => [...document.querySelectorAll('.my-tennis-player-check:checked')].map(input => Number(input.value));
  const refreshBulkButton = () => {
    const count = selectedIds().length;
    bulkButton.disabled = count === 0;
    bulkButton.innerHTML = `<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected${count ? ` (${count})` : ''}`;
  };

  document.addEventListener('change', event => {
    if (event.target.matches('.my-tennis-player-check')) refreshBulkButton();
  });

  const appendPlayerRows = players => {
    players.forEach(player => {
      const row = document.createElement('div');
      row.className = 'player-chip';
      row.dataset.playerRow = player.id;
      const details = document.createElement('div');
      const checkbox = document.createElement('input');
      checkbox.className = 'form-check-input my-tennis-player-check me-2';
      checkbox.type = 'checkbox'; checkbox.value = player.id;
      checkbox.setAttribute('aria-label', `Select ${player.name} for bulk unlink`);
      const name = document.createElement('div'); name.className = 'fw-semibold'; name.textContent = player.name;
      const source = document.createElement('small'); source.className = 'text-muted'; source.textContent = player.linked ? 'Linked to this account' : 'Legacy account link';
      details.append(checkbox, name, source);
      const unlink = document.createElement('button');
      unlink.className = 'btn btn-sm btn-outline-danger my-tennis-unlink'; unlink.type = 'button'; unlink.dataset.playerId = player.id; unlink.dataset.playerName = player.name;
      unlink.innerHTML = '<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink';
      row.append(details, unlink); linkedPlayers.append(row);
    });
  };

  const renderResults = players => {
    results.replaceChildren();
    if (!players.length) {
      results.innerHTML = '<div class="text-muted small">No matching player profiles found.</div>';
      return;
    }
    players.forEach(player => {
      const row = document.createElement('div');
      row.className = 'list-group-item d-flex justify-content-between align-items-center gap-2';
      const label = document.createElement('span');
      label.textContent = `${player.name || ''} ${player.surname || ''}`.trim();
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-sm btn-primary';
      button.textContent = linkedIds.has(Number(player.id)) ? 'Linked' : 'Link';
      button.disabled = linkedIds.has(Number(player.id));
      button.addEventListener('click', () => linkPlayer(player, button));
      row.append(label, button);
      results.append(row);
    });
  };

  const search = async () => {
    const query = searchInput.value.trim();
    if (query.length < 2) return showFeedback('Enter at least two characters to search.', 'warning');
    searchButton.disabled = true;
    try {
      const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Search failed');
      renderResults(await response.json());
    } catch (error) {
      showFeedback('Player search is currently unavailable. Please try again.', 'danger');
    } finally { searchButton.disabled = false; }
  };

  const linkPlayer = async (player, button) => {
    button.disabled = true;
    try {
      const response = await fetch(linkUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ player_id: player.id, date_of_birth: linkDob?.value || '', contact: linkContact?.value || '' }) });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to link player');
      showFeedback(`${player.name} ${player.surname || ''} is now linked to your account.`, 'success');
      window.location.reload();
    } catch (error) { button.disabled = false; showFeedback(error.message, 'danger'); }
  };

  const unlinkPlayer = async button => {
    const name = button.dataset.playerName;
    if (!window.confirm(`Unlink ${name} from your account? This will not delete the player or their history.`)) return;
    button.disabled = true;
    try {
      const response = await fetch(unlinkUrl.replace('__PLAYER__', button.dataset.playerId), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to unlink player');
      window.location.reload();
    } catch (error) { button.disabled = false; showFeedback(error.message, 'danger'); }
  };

  document.addEventListener('click', event => {
    const button = event.target.closest('.my-tennis-unlink');
    if (button) unlinkPlayer(button);
  });

  bulkButton.addEventListener('click', async () => {
    const ids = selectedIds();
    if (!ids.length || !window.confirm(`Unlink ${ids.length} selected player${ids.length === 1 ? '' : 's'}? Their records and history will be kept.`)) return;
    bulkButton.disabled = true;
    try {
      const response = await fetch(bulkUnlinkUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ player_ids: ids }) });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Unable to unlink selected players');
      window.location.reload();
    } catch (error) { showFeedback(error.message, 'danger'); refreshBulkButton(); }
  });

  loadMoreButton?.addEventListener('click', async () => {
    const page = Number(loadMoreButton.dataset.nextPage || 2);
    loadMoreButton.disabled = true;
    try {
      const response = await fetch(`${playersUrl}?page=${page}`, { headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Unable to load more players');
      const payload = await response.json(); appendPlayerRows(payload.data || []);
      if (page >= Number(payload.meta?.last_page || page)) loadMoreButton.remove();
      else { loadMoreButton.dataset.nextPage = page + 1; loadMoreButton.disabled = false; }
    } catch (error) { showFeedback(error.message, 'danger'); loadMoreButton.disabled = false; }
  });

  searchButton.addEventListener('click', search);
  searchInput.addEventListener('keydown', event => { if (event.key === 'Enter') search(); });
})();
</script>
@endsection
