/**
 * RR Brackets module — load, generate, and zoom playoff brackets.
 *
 * Depends on: AdminApi, AdminToast, AdminLoading, AdminRoutes, AdminConfirm
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;

  var SPINNER_LARGE = [
    '<div class="text-center py-5">',
    '<div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"></div>',
    '<div class="mt-3 fw-bold text-muted">Generating playoff brackets…</div>',
    '<small class="text-muted">This may take a few seconds</small>',
    '</div>'
  ].join('');

  var SPINNER_SMALL = [
    '<div class="text-center py-5">',
    '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>',
    '<div class="mt-2 text-muted">Loading bracket…</div>',
    '</div>'
  ].join('');

  // ─── Load bracket via jQuery .load() ─────────────────────────────
  function loadMainBracket(force) {
    $('#main-bracket-wrapper')
      .html(SPINNER_SMALL)
      .load(AdminRoutes.appUrl() + '/backend/draw/' + DRAW_ID + '/main-bracket?force=' + (force ? 1 : 0));
  }

  function loadPlateBracket(force) {
    $('#plate-bracket-wrapper')
      .html(SPINNER_SMALL)
      .load(AdminRoutes.appUrl() + '/backend/draw/' + DRAW_ID + '/plate-bracket?force=' + (force ? 1 : 0));
  }

  function loadConsBracket(force) {
    $('#cons-bracket-wrapper')
      .html(SPINNER_SMALL)
      .load(AdminRoutes.appUrl() + '/backend/draw/' + DRAW_ID + '/cons-bracket?force=' + (force ? 1 : 0));
  }

  // ─── Generate all playoffs ────────────────────────────────────────
  function generateMainBracket(force) {
    var $btn    = $('#btn-generate-main-bracket');
    var restore = AdminLoading.button($btn, 'Generating…');
    $('#main-bracket-wrapper').html(SPINNER_LARGE);

    var payload = force ? { force: 1 } : {};

    AdminApi.post(AdminRoutes.appUrl() + '/backend/draw/' + DRAW_ID + '/generate-main-bracket', payload)
      .then(function (res) {
        if (res.success) {
          AdminToast.success(res.message || 'Brackets generated');
          // Sync OOP so Order of Play tab updates immediately
          if (res.oop) AdminState.setOop(_normaliseOop(res.oop));
          loadMainBracket();
        } else {
          AdminToast.error(res.message || 'Generation failed');
          $('#main-bracket-wrapper').html('<div class="alert alert-danger">' + (res.message || 'Generation failed.') + '</div>');
        }
      })
      .catch(function (err) {
        var msg = (err && err.message) ? err.message : 'Error generating bracket';
        // 422 = RR not complete — offer force override
        if (err && err.status === 422) {
          setTimeout(function () {
            AdminConfirm.destructive('RR not complete', msg + '<br><br>Generate anyway with incomplete scores?').then(function (ok) {
              if (ok) { generateMainBracket(true); }
              else    { $('#main-bracket-wrapper').html('<div class="alert alert-warning">' + msg + '</div>'); }
            });
          }, 300);
        } else {
          AdminToast.error(msg);
          $('#main-bracket-wrapper').html('<div class="alert alert-danger">Error generating bracket.</div>');
        }
      })
      .then(function () { restore(); });
  }

  // ─── OOP normaliser ───────────────────────────────────────────────
  function _normaliseOop(raw) {
    return (raw || []).map(function (fx) {
      return {
        id:             fx.id,
        stage:          fx.stage        || '',
        round:          fx.round        || '',
        match_nr:       fx.match_nr     || '',
        time:           fx.time         || '',
        home:           fx.home         || fx.name1 || '',
        away:           fx.away         || fx.name2 || '',
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


  var _zoom = 1;
  var MIN_ZOOM = 0.3, MAX_ZOOM = 3, STEP = 0.2;

  function _applyZoom() {
    var $inner = $('#bracket-zoom-inner');
    if (!$inner.length) return;
    $inner.css({ transform: 'scale(' + _zoom + ')', 'transform-origin': '0 0' });
    $('#bracket-zoom-label').text(Math.round(_zoom * 100) + '%');
  }

  function _setZoom(val) {
    _zoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, val));
    _applyZoom();
  }

  function _initZoom() {
    $('#bracket-zoom-in').on('click',    function () { _setZoom(_zoom + STEP); });
    $('#bracket-zoom-out').on('click',   function () { _setZoom(_zoom - STEP); });
    $('#bracket-zoom-reset').on('click', function () { _setZoom(1); });

    var $wrapper   = $('#main-bracket-wrapper')[0];
    var startDist  = 0, startZoom = 1;

    if ($wrapper) {
      $wrapper.addEventListener('touchstart', function (e) {
        if (e.touches.length === 2) {
          e.preventDefault();
          startDist = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
          );
          startZoom = _zoom;
        }
      }, { passive: false });

      $wrapper.addEventListener('touchmove', function (e) {
        if (e.touches.length === 2) {
          e.preventDefault();
          var d = Math.hypot(
            e.touches[0].clientX - e.touches[1].clientX,
            e.touches[0].clientY - e.touches[1].clientY
          );
          _setZoom(startZoom * (d / startDist));
        }
      }, { passive: false });

      $wrapper.addEventListener('wheel', function (e) {
        if (e.ctrlKey) {
          e.preventDefault();
          _setZoom(_zoom + (e.deltaY > 0 ? -STEP : STEP));
        }
      }, { passive: false });
    }
  }

  // ─── Bind ─────────────────────────────────────────────────────────
  function bind() {
    $(document).on('click', '#btn-generate-main-bracket', generateMainBracket);

    // Load on tab activation (lazy-load)
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
      if ($(e.target).attr('id') === 'main-bracket-tab') {
        loadMainBracket();
      }
    });

    // Reload after score save (bracket mode)
    AdminState.on('score:saved', function (e) {
      if (e.detail && e.detail.mode === 'BRACKET') {
        loadMainBracket(true);
        loadPlateBracket(true);
        loadConsBracket(true);
      }
    });

    AdminState.on('score:deleted', function () {
      if ($('#main-bracket-pane').hasClass('active') || $('#main-bracket-pane').hasClass('show')) {
        loadMainBracket(true);
      }
    });
  }

  function init(drawId) {
    DRAW_ID = drawId;
    bind();
    _initZoom();

    // Expose for legacy shims
    root.loadMainBracket  = loadMainBracket;
    root.loadPlateBracket = loadPlateBracket;
    root.loadConsBracket  = loadConsBracket;
  }

  root.RRBrackets = {
    init:              init,
    loadMainBracket:   loadMainBracket,
    loadPlateBracket:  loadPlateBracket,
    loadConsBracket:   loadConsBracket,
    generateMain:      generateMainBracket
  };

}(jQuery, window));
