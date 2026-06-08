/**
 * RR Groups module — group assignment, sortable drag-drop, save/regenerate.
 *
 * Depends on: AdminApi, AdminToast, AdminModal, AdminConfirm, AdminState,
 *             AdminLoading, AdminRoutes, Sortable
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;

  // ─── Lock helper ─────────────────────────────────────────────────
  function isLocked() {
    // Prefer the canonical permissions object when available
    if (root.RR_PERMISSIONS) return root.RR_PERMISSIONS.canEditAssignments === false;
    return root.RR_DRAW_LOCKED === true;
  }

  function _warnLocked() {
    AdminToast.warning('Draw is locked. Unlock the draw to make changes.');
  }
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

        // Sync AdminState so matrix and other modules see fresh group membership
        var stateGroups = sorted.map(function (g, gi) {
          return {
            id:   g.id,
            name: g.name,
            registrations: (g.players || []).map(function (p, pi) {
              return {
                id:           p.id,
                display_name: p.name,
                pivot:        { seed: pi + 1 }
              };
            })
          };
        });
        AdminState.setGroups(stateGroups);
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
        setTimeout(function () {
          initSortable(true);
          _applyLockUI();
        }, 50);
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
      Array.from(el.children).forEach(function (c) {
        c.setAttribute('draggable', isLocked() ? 'false' : 'true');
        if (isLocked()) {
          c.classList.add('rr-item-locked');
        } else {
          c.classList.remove('rr-item-locked');
        }
      });

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
          disabled:        isLocked(),
          filter:          '.btn-remove-from-group',
          onStart: function () {
            if (isLocked()) { _warnLocked(); return false; }
            $('.rr-sortable').addClass('drop-zone-active');
          },
          onEnd: function (evt) {
            $('.rr-sortable').removeClass('drop-zone-active');
            if (isLocked()) return;
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
    if (isLocked()) { _warnLocked(); return; }
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

  // ─── OOP normaliser (mirrors scores.js) ─────────────────────────
  function _normaliseOop(raw) {
    return (raw || []).map(function (fx) {
      return {
        id:             fx.id,
        stage:          fx.stage        || '',
        round:          fx.round        || fx.round_nr   || '',
        match_nr:       fx.match_nr     || '',
        time:           fx.time         || '',
        home:           fx.home         || fx.home_name  || fx.name1 || '',
        away:           fx.away         || fx.away_name  || fx.name2 || '',
        score:          fx.score        || '',
        winner:         fx.winner_registration || fx.winner || null,
        r1_id:          fx.r1_id,
        r2_id:          fx.r2_id,
        group_id:       fx.group_id     || null,
        group_name:     fx.group_name   || '',
        playoff_type:   fx.playoff_type || null,
        winner_feeders: fx.winner_feeders || [],
        loser_feeders:  fx.loser_feeders  || []
      };
    });
  }

  // ─── Regenerate fixtures ──────────────────────────────────────────
  function regenerateFixtures(force) {
    if (isLocked()) { _warnLocked(); return; }
    AdminConfirm.destructive(
      'Regenerate Fixtures?',
      'This will <strong>delete existing fixtures</strong> and create new round-robin matches based on current group assignments.'
    ).then(function (ok) {
      if (!ok) return;

      var loader = AdminModal.loading('Generating…', 'Please wait while fixtures are being created.');
      var payload = force ? { force: 1 } : {};

      AdminApi.post(AdminRoutes.drawUrl(DRAW_ID, '/regenerate-rr'), payload)
        .then(function (res) {
          loader.close();
          AdminToast.success(res.message || 'Fixtures regenerated successfully');
          // Sync all state so OOP, matrix and standings update immediately
          if (res.rrFixtures) AdminState.setFixtures(res.rrFixtures);
          if (res.standings)  AdminState.setStandings(res.standings);
          if (res.oop)        AdminState.setOop(_normaliseOop(res.oop));
          refreshGroupsAndPlayers();
        })
        .catch(function (err) {
          loader.close();
          // 422 with confirm flag means results already exist — ask user to force
          if (err && err.status === 422 && err.body && err.body.confirm) {
            // Delay to let the loading Swal fully close before opening the confirm dialog
            setTimeout(function () {
              AdminConfirm.destructive(
                'Results already exist',
                err.message || 'Regenerating will delete all results and brackets. Are you sure?'
              ).then(function (confirmed) {
                if (confirmed) { regenerateFixtures(true); }
              });
            }, 300);
          } else {
            AdminToast.error(err.message || 'Failed to regenerate fixtures.');
          }
        });
    });
  }

  // ─── Remove player from group ────────────────────────────────────
  function _removeFromGroup(e) {
    e.preventDefault();
    e.stopPropagation();
    if (isLocked()) { _warnLocked(); return; }
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
    if (isLocked()) { _warnLocked(); $select.val(currentVal); return; }

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
  function _applyLockUI() {
    var locked = isLocked();

    // Buttons
    $('#btn-save-groups').prop('disabled', locked).attr('title', locked ? 'Draw is locked' : '');
    $('#btn-regenerate-fixtures').prop('disabled', locked).attr('title', locked ? 'Draw is locked' : '');
    $('#groups-tab-boxes').prop('disabled', locked).attr('title', locked ? 'Draw is locked' : '');

    // Locked overlay banner
    $('#groups-pane .rr-locked-overlay').toggleClass('d-none', !locked);

    // Player items — draggable attribute + cursor
    $('#groups-pane li[data-id]').each(function () {
      $(this)
        .attr('draggable', locked ? 'false' : 'true')
        .toggleClass('rr-item-locked', locked);
    });

    // Sortable containers — visual locked class
    $('#groups-pane .rr-sortable').toggleClass('rr-sortable-locked', locked);

    // Sortable instances — disable/enable
    __instances.forEach(function (s) {
      if (s && typeof s.option === 'function') {
        s.option('disabled', locked);
      }
    });

    // Remove buttons — hide when locked
    $('#groups-pane .btn-remove-from-group').toggleClass('invisible', locked);

    // Lock toggle button state
    var $lockBtn = $('#btn-toggle-lock');
    if ($lockBtn.length) {
      if (locked) {
        $lockBtn.removeClass('btn-outline-warning btn-success btn-secondary').addClass('btn-danger');
        $lockBtn.find('i').removeClass('ti-lock-open').addClass('ti-lock');
      } else {
        $lockBtn.removeClass('btn-danger btn-success btn-secondary').addClass('btn-outline-warning');
        $lockBtn.find('i').removeClass('ti-lock').addClass('ti-lock-open');
      }
      $('#lock-label').text(locked ? 'Locked' : 'Unlocked');
    }
  }

  function init(drawId) {
    DRAW_ID = drawId;
    bind();

    // Apply locked UI state on load and whenever the groups tab becomes visible
    _applyLockUI();
    $(document).on('shown.bs.tab', 'button[data-bs-target="#groups-pane"], a[href="#groups-pane"]', function () {
      _applyLockUI();
    });

    // Expose for external calls (matrix, settings module)
    root.refreshGroupsAndPlayers = refreshGroupsAndPlayers;
    root.initGroupsSortable      = initSortable;

    // If groups tab already active on load
    if ($('#groups-tab').hasClass('active') || $('#groups-pane').hasClass('show')) {
      setTimeout(function () { initSortable(true); }, 200);
    }
  }

  root.RRGroups = { init: init, refresh: refreshGroupsAndPlayers, initSortable: initSortable, applyLockUI: _applyLockUI };

}(jQuery, window));
