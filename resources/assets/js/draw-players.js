$(document).ready(function () {
  const drawId = @json($draw->id);

  function loadPlayers() {
    $.get(`/admin/draws/${drawId}/players`, function (data) {
      const tbody = $('#draw-players-table tbody');
      tbody.empty();
      data.forEach((player, index) => {
        tbody.append(`
          <tr>
            <td>${index + 1}</td>
            <td>${player.name}</td>
            <td>${player.team?.name ?? ''}</td>
            <td>${player.category?.name ?? ''}</td>
            <td>
              <button class="btn btn-danger btn-sm remove-player" data-id="${player.id}">Remove</button>
            </td>
          </tr>
        `);
      });
    });
  }

  // Load on page ready
  loadPlayers();

  // Import from Category
  $('#import-category').click(function () {
    $.post(`/admin/draws/${drawId}/import-category`, function () {
      loadPlayers();
    });
  });

  // Add selected player
  $('#add-player').click(function () {
    const playerId = $('#player-select').val();
    if (!playerId) return;
    $.post(`/admin/draws/${drawId}/add-player`, { player_id: playerId }, function () {
      loadPlayers();
      $('#player-select').val('');
    });
  });

  // Remove player
  $('#draw-players-table').on('click', '.remove-player', function () {
    const playerId = $(this).data('id');
    $.ajax({
      url: `/admin/draws/${drawId}/remove-player`,
      type: 'DELETE',
      data: { player_id: playerId },
      success: loadPlayers
    });
  });
});
