@unless(request()->header('X-My-Tennis-Navigation') === '1')
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/player-rating-history-refresh.js') }}"></script>
<script src="{{ asset('assets/js/player-pathway.js') }}?v={{ filemtime(public_path('assets/js/player-pathway.js')) }}"></script>
<script src="{{ asset('assets/js/my-tennis-player-search.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-player-search.js')) }}"></script>
@once('my-tennis-player-switch-script')
<script defer src="{{ asset('assets/js/my-tennis-player-switch.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-player-switch.js')) }}"></script>
@endonce
<script src="{{ asset('assets/js/my-tennis-workspace.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-workspace.js')) }}"></script>
<script defer src="{{ asset('assets/js/my-tennis-navigation.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-navigation.js')) }}"></script>
<script>
function mountMyTennisRating() {
  const button = document.getElementById('my-tennis-rating-tab');
  const panel = document.getElementById('my-tennis-rating');
  if (!button || !panel) return;
  document.querySelector('[data-overview-rating-link]')?.addEventListener('click', event => {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    bootstrap.Tab.getOrCreateInstance(button).show();
  });
  if (!panel.dataset.ratingUrl) {
    if (window.location.hash === '#rating') bootstrap.Tab.getOrCreateInstance(button).show();
    return;
  }
  let loaded = false, loading = false, refreshPending = false, pendingUrl = null, lastUrl = panel.dataset.ratingUrl;
  let loadingHandle = null;
  let controller = null;
  const lifetime = new AbortController();
  document.addEventListener('my-tennis:unmounting', () => {
    lifetime.abort();
    controller?.abort();
    clearTimeout(refreshUI.timer);
    loadingHandle?.close();
  }, { once: true });
  let focusId = null;
  const syncAgeFilter = () => {
    const level = panel.querySelector('[name="rating_level"]:not([type="hidden"])');
    const age = panel.querySelector('[data-rating-age-filter]');
    if (age && level) age.hidden = level.value !== 'overall';
  };
  const refreshUI = new window.PlayerRatingHistoryRefreshUI(panel, () => {
    if (loading) { refreshPending = true; return; }
    return load(lastUrl);
  }, {
    csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
    fetch: (url, options) => window.fetch(url, { ...options, signal: lifetime.signal }),
    setTimer: (callback, delay) => lifetime.signal.aborted ? null : setTimeout(callback, delay),
  });
  new window.PlayerPathwayUI(panel, () => {
    if (loading) { refreshPending = true; return; }
    return load(lastUrl);
  }, { csrf: document.querySelector('meta[name="csrf-token"]')?.content || '', signal: lifetime.signal });
  const load = async (url = null) => {
    if (lifetime.signal.aborted) return;
    if (loading) {
      if (url) { lastUrl = url; pendingUrl = url; }
      return;
    }
    if (loaded && !url) return;
    lastUrl = url || lastUrl;
    const requestUrl = lastUrl;
    loading = true;
    if (!loadingHandle) {
      focusId = panel.contains(document.activeElement) ? document.activeElement.id : button.id;
      loadingHandle = window.CapeLoading.show({title: 'Loading ratings', message: 'Please wait while we load your saved rating history.'});
    }
    panel.querySelectorAll('[data-rating-placeholder], [data-rating-retry]').forEach(element => { element.hidden = true; });
    panel.setAttribute('aria-busy', 'true');
    let status = panel.querySelector('[data-rating-load-status]');
    if (!status) { status = document.createElement('p'); status.dataset.ratingLoadStatus = ''; status.setAttribute('role', 'status'); status.className = 'small text-muted d-flex align-items-center gap-2'; panel.prepend(status); }
    status.textContent = '';
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm flex-shrink-0';
    spinner.setAttribute('aria-hidden', 'true');
    status.append(spinner, 'Loading saved rating history…');
    controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(requestUrl, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', signal: controller.signal });
      if (!response.ok || response.redirected) throw new Error('Unavailable');
      const html = await response.text();
      if (lifetime.signal.aborted || pendingUrl) return;
      const activeSection = panel.querySelector('[data-rating-section].active')?.dataset.ratingSection || 'progress';
      const adminToolsOpen = panel.querySelector('[data-rating-admin-tools]')?.open;
      panel.innerHTML = html;
      const adminTools = panel.querySelector('[data-rating-admin-tools]');
      if (adminTools && adminToolsOpen) adminTools.open = true;
      const currentBadge = document.querySelector('[data-profile-rating-badge]');
      const updatedBadge = panel.querySelector('[data-profile-rating-update]')?.content.firstElementChild;
      if (currentBadge && updatedBadge) currentBadge.replaceWith(updatedBadge.cloneNode(true));
      syncAgeFilter();
      const sectionButton = panel.querySelector('[data-rating-section="' + activeSection + '"]');
      if (sectionButton) bootstrap.Tab.getOrCreateInstance(sectionButton).show();
      loaded = true;
      refreshUI.sync();
    } catch (error) {
      if (lifetime.signal.aborted || pendingUrl) return;
      status.textContent = 'Rating history could not be loaded. ';
      const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-sm btn-outline-primary'; retry.dataset.ratingRetry = ''; retry.textContent = 'Try again'; status.append(retry);
    } finally {
      clearTimeout(timeout);
      if (lifetime.signal.aborted) return;
      loading = false;
      panel.removeAttribute('aria-busy');
      if (pendingUrl) { const nextUrl = pendingUrl; pendingUrl = null; refreshPending = false; load(nextUrl); }
      else if (refreshPending) { refreshPending = false; load(lastUrl); }
      else {
        loadingHandle?.close();
        loadingHandle = null;
        const focusTarget = (focusId && document.getElementById(focusId)) || panel.querySelector('[data-rating-list-focus]') || button;
        focusTarget.focus({ preventScroll: true });
      }
    }
  };
  button.addEventListener('shown.bs.tab', () => load());
  panel.addEventListener('click', event => {
    const link = event.target.closest('[data-rating-link]');
    if (link && (event.button === undefined || event.button === 0) && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) { event.preventDefault(); load(link.getAttribute('href')); }
    else if (event.target.closest('[data-rating-retry]')) load(lastUrl);
  });
  const filterUrl = (form, changedControl = null) => {
    const url = new URL(form.action);
    const params = new URLSearchParams(new FormData(form));
    // A second form may still contain hidden values from the previous response.
    // Keep the latest choices while updating and after a failed request.
    const latest = new URL(lastUrl, form.action).searchParams;
    latest.forEach((value, key) => {
      const preserve = form.elements.namedItem(key)?.type === 'hidden'
        || (loading && changedControl && key !== changedControl.name);
      if (params.has(key) && preserve) params.set(key, value);
    });
    if (!params.has('rating_page') || ['rating_age', 'rating_gender', 'rating_level', 'rating_per_page'].includes(changedControl?.name)) params.set('rating_page', '1');
    url.search = params.toString();
    return url.toString();
  };
  panel.addEventListener('change', event => {
    const form = event.target.closest('[data-rating-filters]');
    if (form) {
      syncAgeFilter();
      if (loading && event.target.id) focusId = event.target.id;
      load(filterUrl(form, event.target));
    }
  });
  panel.addEventListener('submit', event => {
    const form = event.target.closest('[data-rating-filters]');
    if (form) { event.preventDefault(); load(filterUrl(form)); }
  });
  if (panel.dataset.ratingPage !== undefined) load();
  else if (window.location.hash === '#rating') bootstrap.Tab.getOrCreateInstance(button).show();
}

document.addEventListener('DOMContentLoaded', mountMyTennisRating);
document.addEventListener('my-tennis:mounted', mountMyTennisRating);
</script>
<script>
function mountMyTennisControls() {
  const workspace = document.querySelector('[data-my-tennis-workspace]');
  if (!workspace) return;
  const lifetime = new AbortController();
  if (workspace.matches('[data-player-pathway-page]')) {
    new window.PlayerPathwayUI(workspace, () => {}, { signal: lifetime.signal });
  }
  document.addEventListener('my-tennis:unmounting', () => {
    lifetime.abort();
    if (window.jQuery?.fn?.select2) {
      window.jQuery(workspace).find('select.select2-hidden-accessible').select2('destroy');
    }
  }, { once: true });
  const playerSelect = document.getElementById('my-tennis-player');
  if (playerSelect && window.jQuery?.fn?.select2) {
    window.jQuery(playerSelect).select2({ width: '100%', minimumResultsForSearch: 0, placeholder: 'Choose a player' });
  }
  const searchInput = document.getElementById('my-tennis-player-search');
  const linkButton = document.getElementById('my-tennis-player-link-button');
  const feedback = document.getElementById('my-tennis-link-feedback');
  const linkedPlayers = document.getElementById('my-tennis-linked-players');
  const bulkButton = document.getElementById('my-tennis-bulk-unlink');
  const loadMoreButton = document.getElementById('my-tennis-load-more');
  const config = workspace.querySelector('[data-my-tennis-manage-config]');
  if (!searchInput || !linkButton || !feedback || !config) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const { searchUrl, linkUrl, unlinkUrl, bulkUnlinkUrl, playersUrl, profileUrl, linkedIds: ids } = JSON.parse(config.textContent);
  const linkedIds = new Set(ids);

  const showFeedback = (message, type = 'info') => {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} py-2 mb-0`;
    alert.textContent = message;
    feedback.replaceChildren(alert);
  };

  const selectedIds = () => [...document.querySelectorAll('.my-tennis-player-check:checked')].map(input => Number(input.value));
  const refreshBulkButton = () => {
    const count = selectedIds().length;
    bulkButton.disabled = count === 0;
    bulkButton.hidden = count === 0;
    bulkButton.innerHTML = `<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink selected${count ? ` (${count})` : ''}`;
  };

  workspace.addEventListener('change', event => {
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
      const name = document.createElement('a'); name.className = 'fw-semibold'; name.textContent = player.name;
      const playerUrl = new URL(profileUrl, window.location.origin); playerUrl.searchParams.set('player', player.id); name.href = playerUrl.href;
      const source = document.createElement('small'); source.className = 'text-muted'; source.textContent = player.linked ? 'Linked to this account' : 'Legacy account link';
      details.append(checkbox, name, source);
      const unlink = document.createElement('button');
      unlink.className = 'btn btn-sm text-danger my-tennis-unlink'; unlink.type = 'button'; unlink.dataset.playerId = player.id; unlink.dataset.playerName = player.name;
      unlink.innerHTML = '<i class="ti ti-user-minus me-1" aria-hidden="true"></i>Unlink';
      const actions = document.createElement('details'); actions.className = 'player-actions';
      const summary = document.createElement('summary'); summary.textContent = 'Actions'; summary.setAttribute('aria-label', `Actions for ${player.name}`);
      actions.append(summary, unlink);
      row.append(details, actions); linkedPlayers.append(row);
    });
  };

  const linkPlayer = async (player, button) => {
    button.disabled = true;
    try {
      await window.MyTennisPlayerSearch.link({ url: linkUrl, csrf, playerId: player.id });
      showFeedback(`Existing access to ${player.name} ${player.surname || ''} has been restored.`, 'success');
      if (!lifetime.signal.aborted) window.location.reload();
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
      if (!lifetime.signal.aborted) window.location.reload();
    } catch (error) { button.disabled = false; showFeedback(error.message, 'danger'); }
  };

  workspace.addEventListener('click', event => {
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
      if (!lifetime.signal.aborted) window.location.reload();
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

  new window.MyTennisPlayerSearch({ select: searchInput, button: linkButton,
    status: document.getElementById('my-tennis-player-search-status'), url: searchUrl, linkedIds,
    onLink: player => linkPlayer({ id: player.id, name: player.text }, linkButton) });
}

document.addEventListener('DOMContentLoaded', mountMyTennisControls);
document.addEventListener('my-tennis:mounted', mountMyTennisControls);
document.addEventListener('DOMContentLoaded', () => {
  const openLegacyTab = () => {
    const url = new URL(window.location.href);
    const tab = {'#rating': 'rating', '#my-tennis-history': 'activity', '#manage': 'manage'}[url.hash];
    if (!tab || url.searchParams.has('tab') || url.searchParams.has('feature')) return;
    url.pathname = @json(route('my.tennis', [], false));
    url.searchParams.set('tab', tab);
    const player = document.querySelector('[data-my-tennis-workspace] select[name="player"]')?.value;
    if (player) url.searchParams.set('player', player);
    if (tab !== 'activity') url.hash = '';
    window.location.replace(url);
  };
  openLegacyTab();
  window.addEventListener('hashchange', openLegacyTab);
});
</script>
@endsection
@endunless
