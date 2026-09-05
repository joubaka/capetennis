<div class="modal fade" id="scheduleModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Schedule Matches</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="schedule-form">

        <div class="modal-body">

          {{-- ========================================
                SELECT MODE
          ========================================= --}}
          <div class="mb-3">
            <label class="form-label fw-bold">Scheduling Mode</label>
            <div class="d-flex gap-2">

              <div class="form-check">
                <input class="form-check-input mode-radio" type="radio" name="mode" value="draw" checked>
                <label class="form-check-label">Entire Draw</label>
              </div>

              <div class="form-check">
                <input class="form-check-input mode-radio" type="radio" name="mode" value="round">
                <label class="form-check-label">Selected Round</label>
              </div>

              <div class="form-check">
                <input class="form-check-input mode-radio" type="radio" name="mode" value="match">
                <label class="form-check-label">Selected Match</label>
              </div>

            </div>
          </div>


          {{-- ========================================
                ROUND SELECTOR
          ========================================= --}}
          <div class="mb-3 mode-field" id="round-field" style="display:none;">
            <label class="form-label">Round</label>
            <select class="form-select" name="round" id="roundSelect">
              <option value="">Select round</option>
              @foreach(range(1,10) as $r)
                <option value="{{ $r }}">Round {{ $r }}</option>
              @endforeach
            </select>
          </div>


          {{-- ========================================
                MATCH SELECTOR
          ========================================= --}}
          <div class="mb-3 mode-field" id="match-field" style="display:none;">
            <label class="form-label">Match</label>
            <select class="form-select" name="fixture_id" id="matchSelect">
              <option value="">Select match</option>
              {{-- JS populated --}}
            </select>
          </div>


          {{-- ========================================
                VENUE + COURT
          ========================================= --}}
          <div id="manual-schedule-fields" style="display:none;">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Venue</label>
              <select class="form-select" name="venue_id" id="venueSelect">
                <option value="">Select venue</option>
                @foreach($draw->venues as $venue)
                  <option value="{{ $venue->id }}">{{ $venue->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Court</label>
              <select class="form-select" name="court" id="courtSelect">
                <option value="">Select court</option>
              </select>
            </div>
          </div>


          {{-- ========================================
                TIME + DURATION
          ========================================= --}}
          <div class="row mb-3">

            <div class="col-md-6">
              <label class="form-label">Start Time</label>
              <input type="datetime-local" class="form-control" name="scheduled_at"
                value="{{ \Illuminate\Support\Carbon::parse($draw->event?->start_date ?? now())->format('Y-m-d') }}T08:00">
            </div>

            <div class="col-md-6">
              <label class="form-label">Duration (minutes)</label>
              <select class="form-select" name="duration">
                <option value="45">45 min</option>
                <option value="60" selected>60 min</option>
                <option value="75">75 min</option>
                <option value="90">90 min</option>
                <option value="120">120 min</option>
              </select>
            </div>

          </div>
          </div>


          {{-- ========================================
                AUTO-SCHEDULE OPTIONS
          ========================================= --}}
          <div class="border rounded p-3 mb-3" id="auto-schedule-options">

            <h6 class="fw-bold mb-2">Auto-Schedule Options</h6>

            <div class="row mb-2">
              <div class="col-md-6">
                <label class="form-label">Start date and time</label>
                <input type="datetime-local" class="form-control" id="autoStart"
                  value="{{ \Illuminate\Support\Carbon::parse($draw->event?->start_date ?? now())->format('Y-m-d') }}T08:00">
              </div>

              <div class="col-md-3">
                <label class="form-label">Duration</label>
                <input type="number" id="autoDuration" class="form-control" min="1" max="1440" value="60">
              </div>

              <div class="col-md-3">
                <label class="form-label">Gap</label>
                <input type="number" id="autoGap" class="form-control" min="0" max="1440" value="0">
              </div>
            </div>

            <button type="button" id="autoScheduleBtn" class="btn btn-warning w-100">
              Auto-schedule entire draw
            </button>

          </div>


        </div>


        {{-- ========================================
                FOOTER
        ========================================= --}}
        <div class="modal-footer">

          <button type="button" id="clearScheduleBtn" class="btn btn-danger me-auto">
            Clear
          </button>

          <button type="button" id="saveScheduleBtn" class="btn btn-primary">
            Save selected match
          </button>

        </div>

      </form>

    </div>
  </div>
</div>
<script>
function initScheduleModal() {
    'use strict';

    let scheduleBusy = false;
    // ------------------------------------------------------------------
    // CONFIG
    // ------------------------------------------------------------------
    const drawId = $('#drawId').val();
    const routes = {
        data:  APP_URL + `/backend/individual-schedule/${drawId}/data`,
        apply: APP_URL + `/backend/individual-schedule/${drawId}/save`,
        auto:  APP_URL + `/backend/individual-schedule/${drawId}/auto`,
        clear: APP_URL + `/backend/individual-schedule/${drawId}/clear`,
    };

    // ------------------------------------------------------------------
    // MODE SWITCHING UI
    // ------------------------------------------------------------------
    function updateMode() {
        const mode = $('input[name=mode]:checked').val();
        $('.mode-field').hide();
        if (mode === 'round') $('#round-field').show();
        if (mode === 'match') {
            $('#match-field, #manual-schedule-fields, #saveScheduleBtn').show();
            $('#auto-schedule-options').hide();
        } else {
            $('#manual-schedule-fields, #saveScheduleBtn').hide();
            $('#auto-schedule-options').show();
            $('#autoScheduleBtn').text(mode === 'round'
                ? 'Auto-schedule selected round'
                : 'Auto-schedule entire draw');
        }
    }
    $('.mode-radio').on('change', updateMode);
    updateMode();

    function errorMessage(xhr, fallback) {
        const response = xhr.responseJSON || {};
        if (response.message) return response.message;
        if (response.error) return response.error;
        if (response.errors) {
            const messages = Object.values(response.errors).flat();
            if (messages.length) return messages.join(' ');
        }
        return fallback;
    }

    // ------------------------------------------------------------------
    // LOAD FIXTURES + VENUES
    // ------------------------------------------------------------------
    function loadScheduleData(thenRun) {
        $.getJSON(routes.data, function (resp) {

            // Matches
            let matchSelect = $('#matchSelect');
            matchSelect.empty().append(`<option value="">Select match</option>`);

            resp.fixtures.forEach(fx => {
                matchSelect.append(new Option(`#${fx.match_nr} — ${fx.p1} vs ${fx.p2}`, fx.id));
            });

            // Venues
            let venueSelect = $('#venueSelect');
            venueSelect.empty().append(`<option value="">Select venue</option>`);

            resp.venues.forEach(v => {
                const option = new Option(v.name, v.id);
                option.dataset.courts = v.num_courts;
                venueSelect.append(option);
            });

            if (thenRun) thenRun();
        }).fail(xhr => toastr.error(errorMessage(xhr, 'Could not load scheduling data.')));
    }

    // Initial load
    loadScheduleData();

    // ------------------------------------------------------------------
    // VENUE → COURTS
    // ------------------------------------------------------------------
    $('#venueSelect').on('change', function () {
        let selected = $(this).find(':selected');
        let numCourts = selected.data('courts') || 0;

        let courtSelect = $('#courtSelect');
        courtSelect.empty().append(`<option value="">Select court</option>`);

        for (let i = 1; i <= numCourts; i++) {
            courtSelect.append(new Option(`Court ${i}`, String(i)));
        }
    });

    // ------------------------------------------------------------------
    // APPLY SCHEDULE
    // ------------------------------------------------------------------
    $('#saveScheduleBtn').on('click', function () {
        if (scheduleBusy) return;
        const data = {
            fixture_id:       $('#matchSelect').val(),
            scheduled_at:     $('input[name=scheduled_at]').val(),
            venue_id:         $('#venueSelect').val(),
            court_label:      $('#courtSelect').val(),
            duration_minutes: $('select[name=duration]').val(),
            _token:           $('meta[name="csrf-token"]').attr('content')
        };
        if (!data.fixture_id || !data.scheduled_at || !data.venue_id || !data.court_label) {
            toastr.error('Select a match, venue, court, and start date and time.');
            return;
        }
        scheduleBusy = true;
        $('#saveScheduleBtn, #autoScheduleBtn, #clearScheduleBtn').prop('disabled', true);

        $.post(routes.apply, data, function () {
            $('#scheduleModal').modal('hide');
            toastr.success("Schedule updated");
            refreshScheduleTable();
        }).fail(function(xhr) {
            toastr.error(errorMessage(xhr, 'Could not save the selected match.'));
        }).always(function() {
            scheduleBusy = false;
            $('#saveScheduleBtn, #autoScheduleBtn, #clearScheduleBtn').prop('disabled', false);
        });
    });

    // ------------------------------------------------------------------
    // AUTO SCHEDULE
    // ------------------------------------------------------------------
    $('#autoScheduleBtn').on('click', function () {
        if (scheduleBusy) return;
        const mode = $('input[name=mode]:checked').val();
        const autoData = {
            start:    $('#autoStart').val(),
            duration: $('#autoDuration').val(),
            gap:      $('#autoGap').val(),
            _token:   $('meta[name="csrf-token"]').attr('content')
        };
        if (mode === 'round') autoData.round = $('#roundSelect').val();
        if (!autoData.start) {
            toastr.error('Select a start date and time.');
            return;
        }
        if (mode === 'round' && !autoData.round) {
            toastr.error('Select the round to schedule.');
            return;
        }
        scheduleBusy = true;
        $('#saveScheduleBtn, #autoScheduleBtn, #clearScheduleBtn').prop('disabled', true);

        $.post(routes.auto, autoData, function () {
            $('#scheduleModal').modal('hide');
            toastr.success("Auto-schedule complete");
            refreshScheduleTable();
        }).fail(function(xhr) {
            toastr.error(errorMessage(xhr, 'Could not auto-schedule the matches.'));
        }).always(function() {
            scheduleBusy = false;
            $('#saveScheduleBtn, #autoScheduleBtn, #clearScheduleBtn').prop('disabled', false);
        });
    });

    // ------------------------------------------------------------------
    // CLEAR
    // ------------------------------------------------------------------
    $('#clearScheduleBtn').on('click', function () {
        if (scheduleBusy || !window.confirm('Clear the schedule for this draw?')) return;
        scheduleBusy = true;
        $('#saveScheduleBtn, #autoScheduleBtn, #clearScheduleBtn').prop('disabled', true);
        $.post(routes.clear, { _token: $('meta[name="csrf-token"]').attr('content') }, function () {
            toastr.info("All schedules cleared");
            refreshScheduleTable();
        }).fail(function(xhr) {
            toastr.error(errorMessage(xhr, 'Could not clear the schedule.'));
        }).always(function() {
            scheduleBusy = false;
            $('#saveScheduleBtn, #autoScheduleBtn, #clearScheduleBtn').prop('disabled', false);
        });
    });

    // ------------------------------------------------------------------
    // REFRESH DATATABLE
    // ------------------------------------------------------------------
    function refreshScheduleTable() {
        if (window.RRWorkspace) RRWorkspace.refreshHub().catch(error => toastr.error(error.message));
        if (window.scheduleTable) {
            window.scheduleTable.ajax.reload(null, false);
        }
    }

}

// Defer until jQuery is available (this script runs before jQuery loads)
(function waitForJQuery() {
    if (typeof window.jQuery !== 'undefined') {
        jQuery(initScheduleModal);
    } else {
        setTimeout(waitForJQuery, 50);
    }
})();
</script>
