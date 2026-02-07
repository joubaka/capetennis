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
              <input type="text" class="form-control flatpickr-time" name="time" placeholder="Select time">
            </div>

            <div class="col-md-6">
              <label class="form-label">Duration (minutes)</label>
              <select class="form-select" name="duration">
                <option value="60">60 min</option>
                <option value="75" selected>75 min</option>
                <option value="90">90 min</option>
                <option value="120">120 min</option>
              </select>
            </div>

          </div>


          {{-- ========================================
                AUTO-SCHEDULE OPTIONS
          ========================================= --}}
          <div class="border rounded p-3 mb-3">

            <h6 class="fw-bold mb-2">Auto-Schedule Options</h6>

            <div class="row mb-2">
              <div class="col-md-6">
                <label class="form-label">Auto Start Time</label>
                <input type="text" class="form-control flatpickr-time" id="autoStart" placeholder="08:00">
              </div>

              <div class="col-md-3">
                <label class="form-label">Duration</label>
                <input type="number" id="autoDuration" class="form-control" value="75">
              </div>

              <div class="col-md-3">
                <label class="form-label">Gap</label>
                <input type="number" id="autoGap" class="form-control" value="0">
              </div>
            </div>

            <button type="button" id="autoScheduleBtn" class="btn btn-warning w-100">
              Auto Schedule
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
            Apply Schedule
          </button>

        </div>

      </form>

    </div>
  </div>
</div>
<script>
$(function () {
    'use strict';

    // ------------------------------------------------------------------
    // CONFIG
    // ------------------------------------------------------------------
    const drawId = $('#drawId').val();
    const routes = {
        data:  `/backend/draw/${drawId}/scheduleData`,
        apply: `/backend/draw/${drawId}/schedule/apply`,
        auto:  `/backend/draw/${drawId}/schedule/auto`,
        clear: `/backend/draw/${drawId}/schedule/clear`,
    };

    // ------------------------------------------------------------------
    // FLATPICKR
    // ------------------------------------------------------------------
    $('.flatpickr-time').flatpickr({
        noCalendar: true,
        enableTime: true,
        dateFormat: 'H:i',
        time_24hr: true,
    });

    // ------------------------------------------------------------------
    // MODE SWITCHING UI
    // ------------------------------------------------------------------
    $('.mode-radio').on('change', function () {
        let mode = $(this).val();
        $('.mode-field').hide();

        if (mode === 'round') $('#round-field').show();
        if (mode === 'match') $('#match-field').show();
    });

    // ------------------------------------------------------------------
    // LOAD FIXTURES + VENUES
    // ------------------------------------------------------------------
    function loadScheduleData(thenRun) {
        $.getJSON(routes.data, function (resp) {

            // Matches
            let matchSelect = $('#matchSelect');
            matchSelect.empty().append(`<option value="">Select match</option>`);

            resp.fixtures.forEach(fx => {
                matchSelect.append(
                    `<option value="${fx.id}">
                        #${fx.match_nr} — ${fx.p1} vs ${fx.p2}
                     </option>`
                );
            });

            // Venues
            let venueSelect = $('#venueSelect');
            venueSelect.empty().append(`<option value="">Select venue</option>`);

            resp.venues.forEach(v => {
                venueSelect.append(
                    `<option data-courts="${v.num_courts}" value="${v.id}">${v.name}</option>`
                );
            });

            if (thenRun) thenRun();
        });
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
            courtSelect.append(`<option value="C${i}">C${i}</option>`);
        }
    });

    // ------------------------------------------------------------------
    // APPLY SCHEDULE
    // ------------------------------------------------------------------
    $('#saveScheduleBtn').on('click', function () {

        let data = {
            mode:       $('input[name=mode]:checked').val(),
            venue_id:   $('#venueSelect').val(),
            court:      $('#courtSelect').val(),
            time:       $('input[name=time]').val(),
            duration:   $('select[name=duration]').val(),
            round:      $('#roundSelect').val(),
            fixture_id: $('#matchSelect').val(),
            _token:     $('meta[name="csrf-token"]').attr('content')
        };

        $.post(routes.apply, data, function () {
            $('#scheduleModal').modal('hide');
            toastr.success("Schedule updated");
            refreshScheduleTable();
        });
    });

    // ------------------------------------------------------------------
    // AUTO SCHEDULE
    // ------------------------------------------------------------------
    $('#autoScheduleBtn').on('click', function () {

        let autoData = {
            start_time: $('#autoStart').val(),
            duration:   $('#autoDuration').val(),
            gap:        $('#autoGap').val(),
            _token:     $('meta[name="csrf-token"]').attr('content')
        };

        $.post(routes.auto, autoData, function () {
            $('#scheduleModal').modal('hide');
            toastr.success("Auto-schedule complete");
            refreshScheduleTable();
        });
    });

    // ------------------------------------------------------------------
    // CLEAR
    // ------------------------------------------------------------------
    $('#clearScheduleBtn').on('click', function () {
        $.post(routes.clear, { _token: $('meta[name="csrf-token"]').attr('content') }, function () {
            toastr.info("All schedules cleared");
            refreshScheduleTable();
        });
    });

    // ------------------------------------------------------------------
    // REFRESH DATATABLE
    // ------------------------------------------------------------------
    function refreshScheduleTable() {
        if (window.scheduleTable) {
            window.scheduleTable.ajax.reload(null, false);
        }
    }

});
</script>

