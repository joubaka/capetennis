@extends('layouts/layoutMaster')

@section('title', 'Enter Scores — ' . ($draw->drawName ?? 'Draw'))

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl py-4">
  <h2 class="mb-1">Enter Scores</h2>
  <small class="text-muted">{{ $draw->drawName ?? '' }} — {{ $draw->event->name ?? '' }}</small>

  <div class="card mt-4 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="rr-score-table">
          <thead class="table-light">
            <tr>
              <th class="d-none">ID</th>
              <th>Player 1</th>
              <th class="text-center">VS</th>
              <th>Player 2</th>
              <th class="text-center">Round</th>
              <th class="text-center">Group</th>
              <th class="text-center">Score</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($fixtures as $fx)
              @php
                $winner = $fx->winner_registration;

                $reg1 = $fx->registration1;
                $reg2 = $fx->registration2;

                $p1 = $reg1?->players?->first()?->full_name ?? 'TBD';
                $p2 = $reg2?->players?->first()?->full_name ?? 'TBD';

                $cls1 = $winner && $winner == $fx->registration1_id ? 'bg-success text-white' :
                        ($winner && $winner == $fx->registration2_id ? 'bg-danger text-white' : '');

                $cls2 = $winner && $winner == $fx->registration2_id ? 'bg-success text-white' :
                        ($winner && $winner == $fx->registration1_id ? 'bg-danger text-white' : '');

                $allSets = $fx->fixtureResults->sortBy('set_nr')
                    ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
                    ->implode(', ');

                $groupLabel = $fx->stage === 'RR'
                    ? 'Box ' . ($fx->drawGroup?->name ?? '-')
                    : ($fx->stage ?? '-');
              @endphp
              <tr>
                <td class="d-none">{{ $fx->id }}</td>
                <td class="{{ $cls1 }}">{{ $p1 }}</td>
                <td class="text-center"><span class="badge bg-light border text-secondary">vs</span></td>
                <td class="{{ $cls2 }}">{{ $p2 }}</td>
                <td class="text-center">{{ $fx->round ?? '-' }}</td>
                <td class="text-center">{{ $groupLabel }}</td>
                <td class="text-center fw-bold">{{ $allSets }}</td>
                <td class="text-center">
                  <button class="btn btn-sm btn-primary rr-open-modal"
                          data-id="{{ $fx->id }}"
                          data-home="{{ $p1 }}"
                          data-away="{{ $p2 }}">
                    Enter
                  </button>
                  @if($fx->fixtureResults->count())
                    <button class="btn btn-sm btn-outline-danger rr-delete-score"
                            data-id="{{ $fx->id }}">
                      <i class="ti ti-trash"></i>
                    </button>
                  @endif
                </td>
              </tr> 
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="rrScoreModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form id="rr-score-modal-form" class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Enter Score</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="rr-fixture-id">
        <div class="mb-2 fw-bold" id="rr-match-label"></div>

        @for($i = 1; $i <= 3; $i++)
          <div class="row g-2 mb-2">
            <div class="col-12 fw-bold">Set {{ $i }}</div>
            <div class="col-6">
              <label class="form-label"><span class="rr-p1-label">Player 1</span></label>
              <input type="number" min="0" class="form-control rr-s{{ $i }}-p1">
            </div>
            <div class="col-6">
              <label class="form-label"><span class="rr-p2-label">Player 2</span></label>
              <input type="number" min="0" class="form-control rr-s{{ $i }}-p2">
            </div>
          </div>
        @endfor
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
      </div>

    </form>
  </div>
</div>

<style>
.bg-success.text-white { background-color: rgba(40,167,69,.25)!important; color: #155724!important; }
.bg-danger.text-white { background-color: rgba(220,53,69,.25)!important; color: #721c24!important; }
</style>
@endsection

@section('page-script')
<script>
(function($) {
  'use strict';

  var SAVE_URL  = "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}";
  var DEL_URL   = "{{ route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID']) }}";
  var CSRF      = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': CSRF } });

  // Find a table row by fixture id (hidden first cell)
  function getRow(id) {
    return $('#rr-score-table tbody tr').filter(function() {
      return $(this).find('td.d-none').first().text().trim() == String(id);
    });
  }

  function applyColours(tr, winner, r1, r2) {
    tr.find('td').eq(1).removeClass('bg-success bg-danger text-white');
    tr.find('td').eq(3).removeClass('bg-success bg-danger text-white');
    if (!winner) return;
    if (String(winner) === String(r1)) {
      tr.find('td').eq(1).addClass('bg-success text-white');
      tr.find('td').eq(3).addClass('bg-danger text-white');
    } else if (String(winner) === String(r2)) {
      tr.find('td').eq(1).addClass('bg-danger text-white');
      tr.find('td').eq(3).addClass('bg-success text-white');
    }
  }

  function rebuildActions(tr, id, home, away, hasScore) {
    var html = '<button class="btn btn-sm btn-primary rr-open-modal"'
      + ' data-id="' + id + '"'
      + ' data-home="' + home.replace(/"/g,'&quot;') + '"'
      + ' data-away="' + away.replace(/"/g,'&quot;') + '">Enter</button>';
    if (hasScore) {
      html += ' <button class="btn btn-sm btn-outline-danger rr-delete-score" data-id="' + id + '">'
            + '<i class="ti ti-trash"></i></button>';
    }
    tr.find('td').last().html(html);
  }

  // ── OPEN MODAL ──────────────────────────────────────────────────
  $(document).on('click', '.rr-open-modal', function() {
    var id   = $(this).data('id');
    var home = $(this).data('home') || 'Player 1';
    var away = $(this).data('away') || 'Player 2';

    $('#rr-fixture-id').val(id);
    $('#rr-match-label').html('<b>' + home + '</b> vs <b>' + away + '</b>');
    $('.rr-p1-label').text(home);
    $('.rr-p2-label').text(away);

    // Clear all inputs
    $('.rr-s1-p1,.rr-s1-p2,.rr-s2-p1,.rr-s2-p2,.rr-s3-p1,.rr-s3-p2').val('');

    // Pre-fill existing score
    var existing = getRow(id).find('td').eq(5).text().trim();
    if (existing) {
      existing.split(',').forEach(function(set, i) {
        var parts = set.trim().split('-');
        if (parts.length === 2 && i < 3) {
          $('.rr-s' + (i+1) + '-p1').val(parts[0].trim());
          $('.rr-s' + (i+1) + '-p2').val(parts[1].trim());
        }
      });
    }

    new bootstrap.Modal(document.getElementById('rrScoreModal')).show();
  });

  // ── SAVE SCORE ──────────────────────────────────────────────────
  $('#rr-score-modal-form').on('submit', function(e) {
    e.preventDefault();

    var id   = $('#rr-fixture-id').val();
    var home = $('.rr-p1-label').first().text();
    var away = $('.rr-p2-label').first().text();
    var sets = [];
    var valid = true;

    for (var i = 1; i <= 3; i++) {
      var v1 = $('.rr-s' + i + '-p1').val().trim();
      var v2 = $('.rr-s' + i + '-p2').val().trim();
      if (v1 === '' && v2 === '') continue;
      if (v1 === '' || v2 === '') {
        toastr.error('Complete both sides of set ' + i + '.');
        valid = false;
        break;
      }
      sets.push(v1 + '-' + v2);
    }
    if (!valid) return;
    if (!sets.length) { toastr.error('Enter at least one set.'); return; }

    var $btn = $(this).find('[type="submit"]').prop('disabled', true).text('Saving…');
    var url  = SAVE_URL.replace('FIXTURE_ID', id);

    $.post(url, { sets: sets })
      .done(function(res) {
        if (!res.success) { toastr.error('Save failed.'); return; }
        toastr.success('Score saved');
        var tr = getRow(id);
        if (tr.length && res.fixture) {
          tr.find('td').eq(5).text(res.fixture.score || '');
          applyColours(tr, res.fixture.winner_registration, res.fixture.r1_id, res.fixture.r2_id);
          rebuildActions(tr, id, home, away, true);
        }
        bootstrap.Modal.getInstance(document.getElementById('rrScoreModal')).hide();
      })
      .fail(function(xhr) {
        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Error saving score');
      })
      .always(function() { $btn.prop('disabled', false).text('Save'); });
  });

  // ── DELETE SCORE ─────────────────────────────────────────────────
  $(document).on('click', '.rr-delete-score', function() {
    if (!confirm('Delete this score?')) return;
    var $btn = $(this).prop('disabled', true);
    var id   = $btn.data('id');
    var tr   = getRow(id);
    var home = tr.find('.rr-open-modal').data('home') || '';
    var away = tr.find('.rr-open-modal').data('away') || '';
    var url  = DEL_URL.replace('FIXTURE_ID', id);

    $.ajax({ url: url, method: 'DELETE' })
      .done(function() {
        toastr.success('Score deleted');
        tr.find('td').eq(5).text('');
        applyColours(tr, null);
        rebuildActions(tr, id, home, away, false);
      })
      .fail(function(xhr) {
        toastr.error((xhr.responseJSON && xhr.responseJSON.message) || 'Error deleting score');
        $btn.prop('disabled', false);
      });
  });

})(jQuery);
</script>
@endsection
