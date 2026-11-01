(() => {
  'use strict';
  window.MyTennisPlayerSearch = class {
    static async link({ url, csrf, playerId }) {
      let response;
      try {
        response = await fetch(url, { method: 'POST', credentials: 'same-origin',
          headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ player_id: playerId }) });
      } catch (error) {
        throw new Error('Unable to reach Cape Tennis. Check your connection and try linking the player again.');
      }
      if (response.redirected || [401, 419].includes(response.status)) throw new Error('Your session has expired. Reload the page and sign in, then try again.');
      const payload = await response.json().catch(() => null);
      if (!response.ok) throw new Error(payload?.message || 'Unable to link this player. Please try again.');
      if (!payload) throw new Error('The server returned an unexpected response. Reload the page and try again.');
      return payload;
    }

    constructor({ select, button, status, url, linkedIds, onLink }) {
      if (!window.jQuery?.fn?.select2) {
        status.textContent = 'Player search could not load. Please reload this page.';
        return;
      }
      const $select = window.jQuery(select);
      $select.select2({
        width: '100%', dropdownParent: $select.parent(), placeholder: 'Search by player name',
        allowClear: true, minimumInputLength: 2, maximumInputLength: 100,
        language: {
          inputTooShort: () => 'Type at least two characters of the player’s name.',
          noResults: () => 'No matching player profiles found.',
          searching: () => 'Searching player profiles…',
          errorLoading: () => 'Player search is unavailable. Try typing the name again.'
        },
        ajax: {
          url, dataType: 'json', delay: 300, cache: false,
          data: params => ({ q: (params.term || '').trim(), format: 'select2', page: params.page || 1 }),
          processResults: data => {
            status.textContent = data.results.length ? 'Choose a profile, then press Link selected player.' : 'No matching player profiles found.';
            return { ...data, results: data.results.map(player => ({ ...player, disabled: linkedIds.has(Number(player.id)) })) };
          },
          transport: (params, success, failure) => {
            if (params.data.q.length < 2) {
              status.textContent = 'Type at least two characters of the player’s name.';
              success({ results: [], pagination: { more: false } });
              return { abort() {} };
            }
            status.textContent = 'Searching player profiles…';
            const request = window.jQuery.ajax(params);
            request.then(success);
            request.fail((xhr, state, error) => {
              if (state === 'abort') return;
              status.textContent = 'Player search is unavailable. Try typing the name again.';
              failure(xhr, state, error);
            });
            return request;
          }
        }
      });
      $select.on('change', () => {
        const player = $select.select2('data')[0];
        button.disabled = !player?.id || linkedIds.has(Number(player.id));
        status.textContent = player?.id ? 'Profile selected. Press Link selected player to confirm.' : '';
      });
      button.addEventListener('click', () => {
        const player = $select.select2('data')[0];
        if (player?.id && !button.disabled && !linkedIds.has(Number(player.id))) onLink(player);
      });
    }
  };
})();
