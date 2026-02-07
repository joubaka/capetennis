@extends('layouts/layoutMaster')
{{-- Vendor CSS --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('page-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />

@endsection

{{-- Vendor JS --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

{{-- Page JS --}}
@section('page-script')
  


@endsection
@section('content')
<style>
  /* Highlight winners, losers, draws */
  .winner-home {
    background-color: rgba(40, 167, 69, 0.25) !important;
    color: #155724 !important;
  }
  .loser-home {
    background-color: rgba(220, 53, 69, 0.25) !important;
    color: #721c24 !important;
  }
  .draw-cell {
    background-color: rgba(255, 193, 7, 0.25) !important;
    color: #856404 !important;
  }
</style>

@php
if (!function_exists('region_badge_class')) {
    function region_badge_class(?string $short): string {
        if (!$short) return 'bg-label-secondary';

        $map = [
            'WC' => 'bg-label-primary',
            'CT' => 'bg-label-info',
            'OB' => 'bg-label-success',
            'SW' => 'bg-label-warning',
            'BO' => 'bg-label-danger',
            'WP' => 'bg-label-dark',
        ];

        $palette = [
            'bg-label-primary','bg-label-success','bg-label-warning',
            'bg-label-danger','bg-label-info','bg-label-dark','bg-label-secondary'
        ];

        return $map[$short] ?? $palette[abs(crc32($short)) % count($palette)];
    }
}
@endphp

<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Team Fixtures {{ $event ? '- '.$event->name : '' }}</h4>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Draw</th>
            <th>Round</th>
            <th>Tie</th>
            <th>Home</th>
            <th>Away</th>
            <th>Result</th>
            <th>Scheduled</th>
            <th>Venue</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($fixtures as $fx)
          @php
  $homeClass = '';
  $awayClass = '';
@endphp

@if($fx->fixtureResults && $fx->fixtureResults->count() > 0)
  @php $lastSet = $fx->fixtureResults->last(); @endphp
  @if($lastSet->team1_score > $lastSet->team2_score)
    @php $homeClass = 'winner-home'; $awayClass = 'loser-home'; @endphp
  @elseif($lastSet->team2_score > $lastSet->team1_score)
    @php $homeClass = 'loser-home'; $awayClass = 'winner-home'; @endphp
  @else
    @php $homeClass = 'draw-cell'; $awayClass = 'draw-cell'; @endphp
  @endif
@endif


            <tr id="row-{{ $fx->id }}">
              <td>{{ $fx->id }}</td>
              <td>{{ optional($fx->draw)->drawName ?? '—' }}</td>
              <td>{{ $fx->round_nr }}</td>
              <td>{{ $fx->tie_nr }}</td>

              <td class="home-cell {{ $homeClass }}">
                ({{ $fx->home_rank_nr }})
                {{ $fx->team1->pluck('full_name')->implode(' + ') ?: 'TBD' }}
                @if($fx->region1Name?->short_name)
                  <span class="badge rounded-pill {{ region_badge_class($fx->region1Name->short_name) }} ms-1">
                    {{ $fx->region1Name->short_name }}
                  </span>
                @endif
              </td>

              <td class="away-cell {{ $awayClass }}">
                ({{ $fx->away_rank_nr }})
                {{ $fx->team2->pluck('full_name')->implode(' + ') ?: 'TBD' }}
                @if($fx->region2Name?->short_name)
                  <span class="badge rounded-pill {{ region_badge_class($fx->region2Name->short_name) }} ms-1">
                    {{ $fx->region2Name->short_name }}
                  </span>
                @endif
              </td>

           <td id="result-col-{{ $fx->id }}">
  @forelse($fx->fixtureResults as $r)
    {{ $r->team1_score }}-{{ $r->team2_score }}@if(!$loop->last), @endif
  @empty
    <span class="text-muted">No result</span>
  @endforelse
</td>


           <td>
  @if(!empty($display))
    {{ $display instanceof \Carbon\Carbon ? $display->format('Y-m-d H:i') : \Carbon\Carbon::parse($display)->format('Y-m-d H:i') }}
  @else
    —
  @endif
</td>


              <td>{{ optional($fx->venue)->name ?? '—' }}</td>

              <td class="text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                          id="actionsDropdown{{ $fx->id }}" data-bs-toggle="dropdown" aria-expanded="false">
                    Actions
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionsDropdown{{ $fx->id }}">
                    <li>
                      <a href="javascript:void(0);" class="dropdown-item edit-score-btn"
                         id="edit-btn-{{ $fx->id }}"
                         data-id="{{ $fx->id }}"
                         data-home="{{ $fx->team1->pluck('full_name')->implode(' + ') }}"
                         data-away="{{ $fx->team2->pluck('full_name')->implode(' + ') }}"
                         @foreach($fx->fixtureResults as $r)
                           data-set{{ $r->set_nr }}_home="{{ $r->registration1_score }}"
                           data-set{{ $r->set_nr }}_away="{{ $r->registration2_score }}"
                         @endforeach
                      >
                        <i class="ti ti-pencil me-1"></i> Edit score
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" class="dropdown-item text-warning delete-result-btn"
                         data-id="{{ $fx->id }}">
                        <i class="ti ti-eraser me-1"></i> Delete result
                      </a>
                    </li>
                    <li>
                      <a href="javascript:void(0);" 
                         class="dropdown-item edit-players-btn"
                         id="edit-players-{{ $fx->id }}"
                         data-id="{{ $fx->id }}"
                         data-home="{{ $fx->team1->pluck('full_name')->implode(' + ') }}"
                         data-away="{{ $fx->team2->pluck('full_name')->implode(' + ') }}">
                        <i class="ti ti-users me-1"></i> Edit players
                      </a>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="text-center text-muted py-4">No fixtures found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Score Modal -->
<div class="modal fade" id="editScoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editScoreForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title">Edit Score</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong id="fixtureTeams"></strong></p>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Set</th>
                  <th>Home</th>
                  <th>Away</th>
                </tr>
              </thead>
              <tbody>
                @for($i = 1; $i <= 3; $i++)
                  <tr>
                    <td>Set {{ $i }}</td>
                    <td><input type="number" class="form-control" name="set{{ $i }}_home" id="set{{ $i }}Home" min="0"></td>
                    <td><input type="number" class="form-control" name="set{{ $i }}_away" id="set{{ $i }}Away" min="0"></td>
                  </tr>
                @endfor
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Players Modal -->
<div class="modal fade" id="editPlayersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form id="editPlayersForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title">Edit Players</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><strong id="playersFixtureTeams"></strong></p>
          <div class="row">
<div class="col-md-6">
  <label class="form-label">Home Players</label>
  <select class="form-select select2" 
          name="home_players[]" 
          id="homePlayers" 
          data-fixture-type="{{ $team_fixture->fixture_type ?? 'singles' }}" 
          multiple>
    @foreach($allPlayers as $player)
      <option value="{{ $player->id }}">{{ $player->full_name }}</option>
    @endforeach
  </select>
</div>

<div class="col-md-6">
  <label class="form-label">Away Players</label>
  <select class="form-select select2" 
          name="away_players[]" 
          id="awayPlayers" 
          data-fixture-type="{{ $team_fixture->fixture_type ?? 'singles' }}" 
          multiple>
    @foreach($allPlayers as $player)
      <option value="{{ $player->id }}">{{ $player->full_name }}</option>
    @endforeach
  </select>
</div>


          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Players</button>
        </div>
      </form>
    </div>
  </div>
</div>


 <script>
$(function () {
  const $scoreModal   = $('#editScoreModal');
  const $scoreForm    = $('#editScoreForm');
  const $teams        = $('#fixtureTeams');

  const $playersModal = $('#editPlayersModal');
  const $playersForm  = $('#editPlayersForm');
  const $playersTeams = $('#playersFixtureTeams');

  // --- Helpers ---
  function ajaxErrorHandler(context, xhr) {
    console.error(`🔥 Error in ${context}:`, xhr);
    if (xhr.responseJSON && xhr.responseJSON.errors) {
      console.table(xhr.responseJSON.errors);
    }
    alert(`Error while processing ${context}. Check console for details.`);
  }

  function updateWinnerClasses($row, winner) {
    const $home = $row.find('.home-cell');
    const $away = $row.find('.away-cell');
    $home.removeClass('winner-home loser-home draw-cell');
    $away.removeClass('winner-home loser-home draw-cell');

    if (winner === 'home') {
      $home.addClass('winner-home');
      $away.addClass('loser-home');
    } else if (winner === 'away') {
      $home.addClass('loser-home');
      $away.addClass('winner-home');
    } else if (winner === 'draw') {
      $home.addClass('draw-cell');
      $away.addClass('draw-cell');
    }
  }

  // ============================================================
  // SCORE EDIT
  // ============================================================
  $('.edit-score-btn').on('click', function () {
    const fixtureId = $(this).data('id');
    const home = $(this).data('home') || 'Home';
    const away = $(this).data('away') || 'Away';

    console.log(`📢 Opening Edit Score modal for fixture ${fixtureId}`);

    $teams.text(`${home} vs ${away}`);
    $scoreForm.data('fixture-id', fixtureId);
    $scoreForm.attr(
      'action',
      "{{ route('backend.team-fixtures.update', ':id') }}".replace(':id', fixtureId)
    );

    for (let i = 1; i <= 3; i++) {
      $(`#set${i}Home`).val($(this).data(`set${i}_home`) || '');
      $(`#set${i}Away`).val($(this).data(`set${i}_away`) || '');
    }

    $scoreModal.modal('show');
  });

  $scoreForm.on('submit', function (e) {
    e.preventDefault();
    const action    = $scoreForm.attr('action');
    const fixtureId = $scoreForm.data('fixture-id');

    console.log(`🚀 Submitting score for fixture ${fixtureId}`);

    $.ajax({
      url: action,
      type: 'POST',
      data: $scoreForm.serialize(),
      success: function (data) {
        if (data.success) {
          $(`#result-col-${fixtureId}`).html(data.html);
          const $row = $(`#row-${fixtureId}`);
          updateWinnerClasses($row, data.winner);

          // refresh edit button data
          const $editBtn = $(`#edit-btn-${fixtureId}`);
          for (let i = 1; i <= 3; i++) {
            $editBtn.data(`set${i}_home`, data.scores[`set${i}_home`] || '');
            $editBtn.data(`set${i}_away`, data.scores[`set${i}_away`] || '');
          }

          $scoreModal.modal('hide');
          console.log(`✅ Score updated for fixture ${fixtureId}`);
        } else {
          alert('Save failed.');
        }
      },
      error: (xhr) => ajaxErrorHandler('saving score', xhr)
    });
  });

  // Delete Result
  $('.delete-result-btn').on('click', function () {
    if (!confirm('Delete the result for this fixture?')) return;
    const fixtureId = $(this).data('id');
    const url = "{{ route('backend.team-fixtures.destroyResult', ':id') }}".replace(':id', fixtureId);

    console.log(`🗑️ Deleting result for fixture ${fixtureId}`);

    $.ajax({
      url: url,
      type: 'POST',
      data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
      success: function (data) {
        if (data.success) {
          $(`#result-col-${fixtureId}`).html(data.html);
          $(`#row-${fixtureId}`).find('.home-cell, .away-cell').removeClass('winner-home loser-home draw-cell');
          console.log(`✅ Result deleted for fixture ${fixtureId}`);
        } else {
          alert('Delete failed.');
        }
      },
      error: (xhr) => ajaxErrorHandler('deleting result', xhr)
    });
  });

  // ============================================================
  // PLAYERS EDIT
  // ============================================================
  $('.edit-players-btn').on('click', function () {
    const fixtureId = $(this).data('id');
    const home = $(this).data('home') || 'Home';
    const away = $(this).data('away') || 'Away';

    console.log(`📢 Opening Edit Players modal for fixture ${fixtureId}`);

    $playersTeams.text(`${home} vs ${away}`);
    $playersForm.data('fixture-id', fixtureId);
    $playersForm.attr(
      'action',
      "{{ route('backend.team-fixtures.updatePlayers', ':id') }}".replace(':id', fixtureId)
    );

    $playersModal.modal('show');

    // Run once per modal open
    $playersModal.one('shown.bs.modal', function () {
      console.log('📢 Edit Players modal shown');

      // Initialize Select2 if needed
      $('#homePlayers, #awayPlayers').each(function () {
        console.log('🔍 Found select element:', this.id);
        $(this).select2({
          dropdownParent: $playersModal,
          width: '100%',
          placeholder: 'Select players',
          allowClear: true
        });
      
      });

      // Fetch current players for this fixture
    $.ajax({
  url: "{{ url('backend/team-fixtures') }}/" + fixtureId + "/json",
  type: "GET",
  dataType: "json",
  success: function (fixture) {
    console.log("📡 Loaded fixture players:", fixture);
    $('#homePlayers').val(fixture.team1_ids).trigger('change');
    $('#awayPlayers').val(fixture.team2_ids).trigger('change');
    console.log("   ✅ Applied players to selects");
  },
  error: function (xhr) {
    console.error("❌ Failed to load fixture players:", xhr.responseText);
    alert("Failed to load players. Please try again.");
  }
});

    });
  });

  $playersForm.on('submit', function (e) {
    e.preventDefault();
    const action    = $playersForm.attr('action');
    const fixtureId = $playersForm.data('fixture-id');

    console.log(`🚀 Submitting players for fixture ${fixtureId}`);

    $.ajax({
      url: action,
      type: 'POST',
      data: $playersForm.serialize(),
      success: function (data) {
        if (data.success) {
          $(`#row-${fixtureId} .home-cell`).html(data.homeHtml);
          $(`#row-${fixtureId} .away-cell`).html(data.awayHtml);
          $playersModal.modal('hide');
          console.log(`✅ Players updated for fixture ${fixtureId}`);
        } else {
          alert('Save failed.');
        }
      },
      error: (xhr) => ajaxErrorHandler('saving players', xhr)
    });
  });
});
</script>



@endsection
