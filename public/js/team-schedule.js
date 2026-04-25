(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function() {
return /******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!*********************************************!*\
  !*** ./resources/js/pages/team-schedule.js ***!
  \*********************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
function _iterableToArrayLimit(arr, i) { var _i = null == arr ? null : "undefined" != typeof Symbol && arr[Symbol.iterator] || arr["@@iterator"]; if (null != _i) { var _s, _e, _x, _r, _arr = [], _n = !0, _d = !1; try { if (_x = (_i = _i.call(arr)).next, 0 === i) { if (Object(_i) !== _i) return; _n = !1; } else for (; !(_n = (_s = _x.call(_i)).done) && (_arr.push(_s.value), _arr.length !== i); _n = !0); } catch (err) { _d = !0, _e = err; } finally { try { if (!_n && null != _i["return"] && (_r = _i["return"](), Object(_r) !== _r)) return; } finally { if (_d) throw _e; } } return _arr; } }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
$(function () {
  'use strict';

  // =====================================================
  // GLOBAL STATE
  // =====================================================
  window.VENUES = [];
  window.rankVenueMap = {};
  var CONFIG = window.scheduleConfig;
  var ROUTES = CONFIG.routes;
  var csrf = $('meta[name="csrf-token"]').attr('content');
  var fpOpts = {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    time_24hr: true
  };
  var DEBUG = true;
  function log() {
    var _console;
    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }
    if (DEBUG) (_console = console).log.apply(_console, ['[SCHEDULE]'].concat(args));
  }

  // =====================================================
  // HELPERS
  // =====================================================
  function safeFlatpickr(el) {
    if (!el._flatpickr) {
      flatpickr(el, fpOpts);
      log('Flatpickr init', el);
    }
  }
  function safeSelect2($el) {
    var opts = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.select2(_objectSpread({
      width: '100%'
    }, opts));
  }
  function initRowEditors($row) {
    $row.find('.dtp').each(function () {
      safeFlatpickr(this);
      var val = $(this).data('val');
      if (val && this._flatpickr) this._flatpickr.setDate(val, true);
    });
    $row.find('.venue-select').each(function () {
      safeSelect2($(this));
    });
  }
  function venueOptionsHtml(selectedId) {
    return '<option value="">-- Select --</option>' + VENUES.map(function (v) {
      return "<option value=\"".concat(v.id, "\" ").concat(+selectedId === +v.id ? 'selected' : '', ">\n                ").concat(v.name, " (x").concat(v.num_courts, ")\n            </option>");
    }).join('');
  }
  function rowToRender(r) {
    var _r$round, _r$tie, _r$home_rank, _r$court_label, _r$duration_min;
    return {
      id: r.id,
      round: (_r$round = r.round) !== null && _r$round !== void 0 ? _r$round : '',
      tie: (_r$tie = r.tie) !== null && _r$tie !== void 0 ? _r$tie : '',
      rank: (_r$home_rank = r.home_rank) !== null && _r$home_rank !== void 0 ? _r$home_rank : '',
      teams: "".concat(r.p1, " <span class=\"text-muted\">vs</span> ").concat(r.p2),
      datetime_html: "<input type=\"text\" class=\"form-control form-control-sm dtp\"\n                data-id=\"".concat(r.id, "\"\n                data-val=\"").concat(r.scheduled_at || '', "\"\n                value=\"").concat(r.scheduled_at || '', "\"\n                placeholder=\"YYYY-MM-DD HH:mm\">"),
      venue_html: "<select class=\"form-select form-select-sm venue-select\"\n                data-id=\"".concat(r.id, "\">\n                ").concat(venueOptionsHtml(r.venue_id), "\n            </select>"),
      court_html: "<input class=\"form-control form-control-sm court-input\"\n                data-id=\"".concat(r.id, "\"\n                value=\"").concat((_r$court_label = r.court_label) !== null && _r$court_label !== void 0 ? _r$court_label : '', "\"\n                maxlength=\"50\">"),
      duration_html: "<input type=\"number\" min=\"20\" max=\"480\" step=\"5\"\n                class=\"form-control form-control-sm dur-input text-center\"\n                data-id=\"".concat(r.id, "\"\n                value=\"").concat((_r$duration_min = r.duration_min) !== null && _r$duration_min !== void 0 ? _r$duration_min : '', "\"\n                placeholder=\"min\">"),
      status_html: r.clash_flag ? '<span class="badge bg-danger">Clash</span>' : r.scheduled_at ? '<span class="badge bg-success">Scheduled</span>' : '<span class="badge bg-secondary">Unscheduled</span>',
      actions_html: "<button class=\"btn btn-sm btn-primary btn-save\"\n                data-id=\"".concat(r.id, "\">Save</button>")
    };
  }

  // =====================================================
  // DATATABLE
  // =====================================================
  var table = $('#scheduleTable').DataTable({
    paging: true,
    searching: true,
    ordering: false,
    pageLength: 25,
    autoWidth: false,
    columns: [{
      data: 'id',
      className: 'text-center',
      width: '50px'
    }, {
      data: 'round',
      className: 'text-center',
      width: '60px'
    }, {
      data: 'tie',
      className: 'text-center',
      width: '50px'
    }, {
      data: 'rank',
      className: 'text-center',
      width: '50px'
    }, {
      data: 'teams'
    }, {
      data: 'datetime_html'
    }, {
      data: 'venue_html'
    }, {
      data: 'court_html',
      width: '80px'
    }, {
      data: 'duration_html',
      className: 'text-center',
      width: '70px'
    }, {
      data: 'status_html',
      className: 'text-center'
    }, {
      data: 'actions_html',
      className: 'text-center'
    }],
    drawCallback: function drawCallback() {
      $('#scheduleTable tbody tr').each(function () {
        initRowEditors($(this));
      });
    }
  });

  // =====================================================
  // LOAD DATA
  // =====================================================
  function loadData() {
    $.get(ROUTES.data).done(function (res) {
      VENUES = res.venues || [];

      // Populate the venues filter dropdown
      var $venueSelect = $('#venues');
      $venueSelect.empty();
      VENUES.forEach(function (v) {
        $venueSelect.append("<option value=\"".concat(v.id, "\">").concat(v.name, " (x").concat(v.num_courts, ")</option>"));
      });
      safeSelect2($venueSelect, {
        placeholder: 'All venues (leave empty)'
      });
      var rows = (res.fixtures || []).map(rowToRender);
      table.clear().rows.add(rows).draw();
      log('Loaded fixtures:', rows.length);
    }).fail(function (xhr) {
      console.error('[SCHEDULE] Load failed', xhr);
      toastr.error('Failed to load schedule data');
    });
  }

  // =====================================================
  // SAVE FIXTURE
  // =====================================================
  $('#scheduleTable').on('click', '.btn-save', function () {
    var id = $(this).data('id');
    $.post(ROUTES.save, {
      _token: csrf,
      fixture_id: id,
      scheduled_at: $(".dtp[data-id=\"".concat(id, "\"]")).val() || null,
      venue_id: $(".venue-select[data-id=\"".concat(id, "\"]")).val() || null,
      court_label: $(".court-input[data-id=\"".concat(id, "\"]")).val() || null,
      duration_min: $(".dur-input[data-id=\"".concat(id, "\"]")).val() || null
    }).done(function () {
      toastr.success('Saved');
      loadData();
    }).fail(function (xhr) {
      console.error('[SCHEDULE] Save failed', xhr);
      toastr.error('Save failed');
    });
  });

  // =====================================================
  // RANK → VENUE MAP RENDER
  // =====================================================
  function renderRankVenueRows() {
    var $tbody = $('#rankVenueRows').empty();
    Object.entries(rankVenueMap).forEach(function (_ref) {
      var _config$venue_id, _config$duration;
      var _ref2 = _slicedToArray(_ref, 2),
        rank = _ref2[0],
        config = _ref2[1];
      var venueId = (_config$venue_id = config === null || config === void 0 ? void 0 : config.venue_id) !== null && _config$venue_id !== void 0 ? _config$venue_id : '';
      var duration = (_config$duration = config === null || config === void 0 ? void 0 : config.duration) !== null && _config$duration !== void 0 ? _config$duration : '';
      $tbody.append("\n            <tr data-rank=\"".concat(rank, "\">\n                <td>\n                    <input type=\"number\"\n                        class=\"form-control form-control-sm rank-input\"\n                        value=\"").concat(rank, "\" min=\"1\">\n                </td>\n                <td>\n                    <select class=\"form-select form-select-sm venue-select-row\">\n                        ").concat(VENUES.map(function (v) {
        return "<option value=\"".concat(v.id, "\"\n                                ").concat(v.id == venueId ? 'selected' : '', ">\n                                ").concat(v.name, "\n                            </option>");
      }).join(''), "\n                    </select>\n                </td>\n                <td>\n                    <input type=\"number\"\n                        class=\"form-control form-control-sm dur-override\"\n                        value=\"").concat(duration, "\"\n                        min=\"20\" step=\"5\">\n                </td>\n                <td>\n                    <button type=\"button\"\n                        class=\"btn btn-sm btn-outline-danger btnRemoveRankVenue\">\n                        \u2715\n                    </button>\n                </td>\n            </tr>\n        "));
    });
    $('#rankVenueRows .venue-select-row').each(function () {
      safeSelect2($(this));
    });
  }

  // =====================================================
  // BUILD PAYLOAD
  // =====================================================
  function buildPayload() {
    var _$$val;
    var simpleMap = {};
    var durationMap = {};
    Object.entries(rankVenueMap).forEach(function (_ref3) {
      var _ref4 = _slicedToArray(_ref3, 2),
        rank = _ref4[0],
        config = _ref4[1];
      simpleMap[rank] = config.venue_id;
      if (config.duration) {
        durationMap[rank] = config.duration;
      }
    });
    return {
      _token: csrf,
      start: $('#start').val(),
      end: $('#end').val(),
      duration: $('#duration').val(),
      gap: $('#gap').val(),
      round: $('#round').val() || null,
      venues: (_$$val = $('#venues').val()) !== null && _$$val !== void 0 ? _$$val : [],
      rank_venue_map: simpleMap,
      rank_duration_map: durationMap
    };
  }

  // =====================================================
  // AUTO SCHEDULE
  // =====================================================
  $('#btnAuto').on('click', function () {
    var payload = buildPayload();
    if (!payload.start || !payload.end) {
      toastr.error('Please set start and end times');
      return;
    }
    $.post(ROUTES.auto, payload).done(function (res) {
      var _res$count;
      toastr.success("Auto-scheduled ".concat((_res$count = res.count) !== null && _res$count !== void 0 ? _res$count : 0, " matches"));
      loadData();
    }).fail(function (xhr) {
      var _xhr$responseJSON;
      console.error('[SCHEDULE] Auto failed', xhr);
      toastr.error(((_xhr$responseJSON = xhr.responseJSON) === null || _xhr$responseJSON === void 0 ? void 0 : _xhr$responseJSON.message) || 'Auto failed');
    });
  });

  // =====================================================
  // CLEAR / RESET
  // =====================================================
  $('#btn-clear-schedule').on('click', function () {
    if (!confirm('Clear ALL scheduled fixtures?')) return;
    $.post(ROUTES.clear, {
      _token: csrf
    }).done(function (res) {
      toastr.success(res.message || 'Schedules cleared');
      loadData();
    }).fail(function () {
      return toastr.error('Failed to clear');
    });
  });
  $('#btn-reset-schedule').on('click', function () {
    if (!confirm('Reset and auto-schedule again?')) return;
    $.post(ROUTES.reset, buildPayload()).done(function () {
      toastr.success('Reset complete');
      loadData();
    }).fail(function () {
      return toastr.error('Reset failed');
    });
  });

  // =====================================================
  // RANK → VENUE MAP ACTIONS
  // =====================================================
  $('#btnAddRankVenue').on('click', function () {
    // Get selected venues from the filter dropdown
    var selectedVenueIds = $('#venues').val() || [];
    var selectedVenues = selectedVenueIds.length > 0 ? VENUES.filter(function (v) {
      return selectedVenueIds.includes(String(v.id));
    }) : VENUES;
    if (selectedVenues.length === 0) {
      toastr.warning('No venues selected or available');
      return;
    }

    // Get unique ranks from current fixtures
    var ranks = [];
    table.rows().every(function () {
      var rank = this.data().rank;
      if (rank && !ranks.includes(rank)) ranks.push(rank);
    });
    ranks.sort(function (a, b) {
      return a - b;
    });
    if (ranks.length === 0) {
      toastr.warning('No ranks found in fixtures');
      return;
    }

    // Clear existing map
    rankVenueMap = {};
    if (selectedVenues.length === 1) {
      // Single venue: assign all ranks to it
      ranks.forEach(function (rank) {
        rankVenueMap[rank] = {
          venue_id: selectedVenues[0].id,
          duration: ''
        };
      });
    } else if (selectedVenues.length === 2) {
      // Two venues: split ranks in half
      var midpoint = Math.ceil(ranks.length / 2);
      ranks.forEach(function (rank, index) {
        var venueIndex = index < midpoint ? 0 : 1;
        rankVenueMap[rank] = {
          venue_id: selectedVenues[venueIndex].id,
          duration: ''
        };
      });
    } else {
      // More than 2 venues: distribute round-robin
      ranks.forEach(function (rank, index) {
        var venueIndex = index % selectedVenues.length;
        rankVenueMap[rank] = {
          venue_id: selectedVenues[venueIndex].id,
          duration: ''
        };
      });
    }
    renderRankVenueRows();
    toastr.success("Mapped ".concat(ranks.length, " rank(s) to ").concat(selectedVenues.length, " venue(s)"));
  });
  $('#btnAutoMapRanks').on('click', function () {
    // Same logic as Add Mapping for auto-map
    $('#btnAddRankVenue').trigger('click');
  });

  // Remove single mapping row
  $('#rankVenueRows').on('click', '.btnRemoveRankVenue', function () {
    var rank = $(this).closest('tr').data('rank');
    delete rankVenueMap[rank];
    renderRankVenueRows();
  });

  // Update map when row values change
  $('#rankVenueRows').on('change', '.rank-input, .venue-select-row, .dur-override', function () {
    var $row = $(this).closest('tr');
    var oldRank = $row.data('rank');
    var newRank = $row.find('.rank-input').val();
    var venueId = $row.find('.venue-select-row').val();
    var duration = $row.find('.dur-override').val();
    if (oldRank !== newRank) {
      delete rankVenueMap[oldRank];
    }
    rankVenueMap[newRank] = {
      venue_id: venueId,
      duration: duration || ''
    };
    $row.data('rank', newRank);
  });

  // =====================================================
  // INIT
  // =====================================================
  var today = new Date();
  var pad = function pad(n) {
    return n.toString().padStart(2, '0');
  };
  var dateStr = "".concat(today.getFullYear(), "-").concat(pad(today.getMonth() + 1), "-").concat(pad(today.getDate()));
  var startStr = "".concat(dateStr, " 08:00");
  var endStr = "".concat(dateStr, " 18:00");

  // Set input values directly
  $('#start').val(startStr);
  $('#end').val(endStr);

  // Now initialize flatpickr
  flatpickr('#start', fpOpts);
  flatpickr('#end', fpOpts);
  $('#btnReload').on('click', loadData);
  loadData();
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});