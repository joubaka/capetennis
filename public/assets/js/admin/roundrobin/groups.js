/**
 * RR Groups module — group assignment, sortable drag-drop, save/regenerate.
 *
 * Depends on: AdminApi, AdminToast, AdminModal, AdminConfirm, AdminState,
 *             AdminLoading, AdminRoutes, Sortable
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;

  var COLORS = {
    A: 'bg-primary text-white',
    B: 'bg-success text-white',
    C: 'bg-warning text-dark',
    D: 'bg-danger text-white',
    E: 'bg-info text-white',
    F: 'bg-secondary text-white',
    G: 'bg-dark text-white',
    H: 'bg-primary text-white'
  };

  // ─── Sortable instance tracking ──────────────────────────────────
  var __instances  = [];
  var __initDone   = false;
  var __initPending = false;

  // ─── Refresh helpers ─────────────────────────────────────────────

  function refreshGroupsUI() {
    var url = AdminRoutes.drawUrl(DRAW_ID, '/groups-data');
    return AdminApi.get(url)
      .then(function (res) {
        if (!res.groups) return;
        var sorted = res.groups.slice().sort(function (a, b) {
          return (a.name || '').localeCompare(b.name || '');
        });
        _renderGroupsRow(sorted);
      })
      .catch(function () { AdminToast.warning('Could not refresh groups.'); });
  }

  function refreshAvailablePlayersUI() {
    var url = AdminRoutes.drawUrl(DRAW_ID, '/available-players');
    return AdminApi.get(url)
      .then(function (res) {
        if (!res.categories) return;
        var $list = $('#available-players-list');
        if (!$list.length) return;
        var html = '';
        res.categories.forEach(function (cat) {
          if (!cat.players || !cat.players.length) return;
          html += '<div class="mb-3">';
          html += '<div class="fw-bold text-primary small mb-1">';
          html += '<i class="ti ti-category me-1"></i> ' + $('<div>').text(cat.category).html();
          html += ' <span class="badge bg-secondary">' + cat.count + '</span></div>';
          html += '<ul class="list-group list-group-flush rr-sortable" data-type="source">';
          cat.players.forEach(function (p) {
            var eName = $('<div>').text(p.name).html();
            html += '<li class="list-group-item list-group-item-action py-1 px-2"' +
                    ' data-id="' + p.id + '" data-player-name="' + eName + '">' +
                    '<small>' + eName + '</small></li>';
          });
          html += '</ul></div>';
        });
        if (!html) {
          html = '<div class="text-muted text-center py-4">' +
                 '<i class="ti ti-info-circle fs-3 d-block mb-2"></i>All players assigned.</div>';
        }
        $list.html(html);
      })
      .catch(function () { AdminToast.warning('Could not refresh available players.'); });
  }

  function refreshGroupsAndPlayers() {
    return Promise.all([refreshGroupsUI(), refreshAvailablePlayersUI()])
      .then(function () {
        setTimeout(function () { initSortable(true); }, 50);
      });
  }

  function _renderGroupsRow(groups) {
    var $row = $('#rr-groups-row');
    if (!$row.length) return;
    var html = '';
    groups.forEach(function (g) {
      var colorClass = COLORS[g.name] || 'bg-dark text-white';
      html += '<div class="col-6 mb-3">' +
        '<div class="card border h-100">' +
        '<div class="card-header py-2 ' + colorClass + '">' +
        '<h6 class="mb-0"><i class="ti ti-users-group me-1"></i> Group ' + g.name +
        '<span class="badge bg-light text-dark float-end">' + g.players.length + ' players</span></h6></div>' +
        '<div class="card-body p-2" style="min-height:150px;">' +
        '<ul class="list-group list-group-flush rr-sortable rr-group"' +
        ' data-group-id="' + g.id + '" data-type="target">';
      g.players.forEach(function (p) {
        var eName = $('<div>').text(p.name).html();
        html += '<li class="list-group-item list-group-item-action py-1 px-2"' +
                ' data-id="' + p.id + '" data-player-name="' + eName + '">' +
                '<small>' + eName + '</small>' +
                '<button type="button" class="btn btn-sm btn-link text-danger float-end p-0 btn-remove-from-group"' +
                ' data-id="' + p.id + '"><i class="ti ti-x"></i></button></li>';
      });
      html += '</ul>';
      if (!g.players.length) {
        html += '<div class="text-muted text-center py-3 empty-group-placeholder">' +
                '<small>Drop players here</small></div>';
      }
      html += '</div></div></div>';
    });
    if (!html) {
      html = '<div class="col-12"><div class="alert alert-warning">' +
             '<i class="ti ti-alert-triangle me-1"></i> No groups found.</div></div>';
    }
    $row.html(html);
  }

  // ─── Sortable ─────────────────────────────────────────────────────
  function initSortable(forceReinit) {
    if (__initPending) return;
    if (!forceReinit && __initDone) return;
    if (typeof Sortable === 'undefined') return;

    __initPending = true;

    // Destroy existing
    __instances.forEach(function (s) {
      try { if (s && s.destroy) s.destroy(); } catch (e) {}
    });
    __instances = [];
    __initDone  = false;

    var pane = document.getElementById('groups-pane');
    if (!pane) { __initPending = false; return; }

    pane.querySelectorAll('.rr-sortable').forEach(function (el) {
      Array.from(el.children).forEach(function (c) { c.setAttribute('draggable', 'true'); });

      try {
        var inst = new Sortable(el, {
          group:           'shared-players',
          animation:       200,
          ghostClass:      'sortable-ghost',
          chosenClass:     'sortable-chosen',
          dragClass:       'sortable-drag',
          fallbackOnBody:  true,
          swapThreshold:   0.3,
          touchStartThreshold: 5,
          dragoverBubble:  false,
          removeCloneOnHide: true,
          filter:          '.btn-remove-from-group',
          onStart: function () { $('.rr-sortable').addClass('drop-zone-active'); },
          onEnd: function (evt) {
            $('.rr-sortable').removeClass('drop-zone-active');
            var $item   = $(evt.item);
            var $target = $(evt.to);
            if ($target.hasClass('rr-group') && !$item.find('.btn-remove-from-group').length) {
              $item.append(
                '<button type="button" class="btn btn-sm btn-link text-danger float-end p-0 btn-remove-from-group"' +
                ' data-id="' + $item.data('id') + '"><i class="ti ti-x"></i></button>'
              );
            }
            if ($target.data('type') === 'source') {
              $item.find('.btn-remove-from-group').remove();
            }
            _updateEmptyPlaceholders();
          }
        });
        __instances.push(inst);
      } catch (e) {}
    });

    __initDone    = true;
    __initPending = false;
  }

  function _updateEmptyPlaceholders() {
    $('.rr-group').each(function () {
      var $g = $(this);
      var $ph = $g.siblings('.empty-group-placeholder');
      var has = $g.children('li').length > 0;
      if (has) {
        $ph.hide();
      } else {
        if (!$ph.length) {
          $g.after('<div class="text-muted text-center py-3 empty-group-placeholder"><small>Drop players here</small></div>');
        } else {
          $ph.show();
        }
      }
    });
  }

  // ─── Save groups ─────────────────────────────────────────────────
  function saveGroups() {
    var payload = [];
    $('.rr-group').each(function () {
      payload.push({
        group_id: $(this).data('group-id'),
        registration_ids: $(this).find('li').map(function () { return $(this).data('id'); }).get()
      });
    });

    var $btn    = $('#btn-save-groups');
    var restore = AdminLoading.button($btn, 'Saving…');

    AdminApi.post(AdminRoutes.drawUrl(DRAW_ID, '/save-groups'), { groups: payload })
      .then(function () { AdminToast.success('Groups saved successfully'); })
      .catch(function () { AdminToast.error('Failed to save groups'); })
      .then(function () { restore(); });
  }

  // ─── Regenerate fixtures ──────────────────────────────────────────
  function regenerateFixtures() {
    AdminConfirm.destructive(
      'Regenerate Fixtures?',
      'This will <strong>delete existing fixtures</strong> and create new round-robin matches based on current group assignments.'
    ).then(function (ok) {
      if (!ok) return;

      var loader = AdminModal.loading('Generating…', 'Please wait while fixtures are being created.');

      AdminApi.post(AdminRoutes.drawUrl(DRAW_ID, '/regenerate-rr'))
        .then(function (res) {
          loader.close();
          AdminToast.success(res.message || 'Fixtures regenerated successfully');
          refreshGroupsAndPlayers();
        })
        .catch(function (err) {
          loader.close();
          AdminToast.error(err.message || 'Failed to regenerate fixtures.');
        });
    });
  }

  // ─── Remove player from group ────────────────────────────────────
  function _removeFromGroup(e) {
    e.preventDefault();
    e.stopPropagation();
    var $item      = $(this).closest('li');
    var playerName = $item.data('player-name');
    var $sourceList = $('.rr-sortable[data-type="source"]').first();
    if ($sourceList.length) {
      $item.find('.btn-remove-from-group').remove();
      $sourceList.append($item);
      AdminToast.info(playerName + ' removed from group');
      _updateEmptyPlaceholders();
    }
  }

  // ─── Import teams ────────────────────────────────────────────────
  function importTeams() {
    var eventId = root.EVENT_ID;
    AdminConfirm.destructive(
      'Import Teams?',
      'This will create categories and registrations for all teams.'
    ).then(function (ok) {
      if (!ok) return;
      AdminApi.post(AdminRoutes.appUrl() + '/backend/event/' + eventId + '/import-teams')
        .then(function (res) {
          AdminToast.success(res.message);
          refreshGroupsAndPlayers();
        })
        .catch(function () { AdminToast.error('Import failed.'); });
    });
  }

  // ─── Change number of groups ─────────────────────────────────────
  function changeGroups(newBoxes, currentVal, $select) {
    if (newBoxes === currentVal) return;

    AdminModal.confirm({
      title:       'Change Number of Groups?',
      html:        '<p>This will change from <strong>' + currentVal + '</strong> to <strong>' + newBoxes + '</strong> groups.</p>' +
                   '<p class="text-warning"><i class="ti ti-alert-triangle"></i> All players will be moved to <strong>Group A</strong>.</p>',
      confirmText: 'Yes, change groups',
      confirmColor: '#198754'
    }).then(function (ok) {
      if (!ok) { $select.val(currentVal); return; }

      $select.prop('disabled', true);
      var loader = AdminModal.loading('Updating Groups…', 'Please wait while groups are being recreated.');

      AdminApi.post(AdminRoutes.drawUrl(DRAW_ID, '/settings'), {
        _token: $('meta[name="csrf-token"]').attr('content'),
        boxes:  newBoxes
      }).then(function (res) {
        loader.close();
        if (!res.success) {
          AdminToast.error(res.message || 'Failed to update groups.');
          $select.val(currentVal);
          return;
        }
        AdminToast.success('Groups updated successfully!');
        refreshGroupsAndPlayers();

        // Sync selectors
        var cnt = res.groups_count || newBoxes;
        $('#groups-count-label').text('| ' + cnt + ' Groups');
        $('#settings-boxes').val(newBoxes);

        // Notify other modules
        if (typeof root.numGroups !== 'undefined') root.numGroups = newBoxes;
        if (typeof root.updateFlowPreview === 'function') root.updateFlowPreview();

        // Fire state event for playoff module
        document.dispatchEvent(new CustomEvent('rr:groups:count:changed', { detail: { count: newBoxes } }));
      }).catch(function (err) {
        loader.close();
        AdminToast.error(err.message || 'Failed to update groups.');
        $select.val(currentVal);
      }).then(function () {
        $select.prop('disabled', false);
      });
    });
  }

  // ─── Bind DOM events ─────────────────────────────────────────────
  function bind() {
    $(document).on('click', '.btn-remove-from-group', _removeFromGroup);
    $(document).on('click', '#btn-save-groups',         saveGroups);
    $(document).on('click', '#btn-regenerate-fixtures', regenerateFixtures);
    $(document).on('click', '#btn-import-teams',        importTeams);

    $('#groups-tab-boxes').on('change', function () {
      var newBoxes    = parseInt($(this).val(), 10);
      var currentVal  = parseInt($(this).data('current') || $(this).val(), 10);
      changeGroups(newBoxes, currentVal, $(this));
    });

    // Tab activation → init sortable
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
      if ($(e.target).attr('id') === 'groups-tab') {
        setTimeout(function () { initSortable(true); }, 150);
      }
    });
  }

  // ─── Init ─────────────────────────────────────────────────────────
  function init(drawId) {
    DRAW_ID = drawId;
    bind();

    // Expose for external calls (matrix, settings module)
    root.refreshGroupsAndPlayers = refreshGroupsAndPlayers;
    root.initGroupsSortable      = initSortable;

    // If groups tab already active on load
    if ($('#groups-tab').hasClass('active') || $('#groups-pane').hasClass('show')) {
      setTimeout(function () { initSortable(true); }, 200);
    }
  }

  root.RRGroups = { init: init, refresh: refreshGroupsAndPlayers, initSortable: initSortable };

}(jQuery, window));
