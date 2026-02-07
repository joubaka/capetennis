@extends('layouts/layoutMaster')

@section('title', $venue->name . ' – Fixtures')

{{-- Vendor CSS --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

{{-- Vendor JS --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<style>
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

  @media (max-width: 768px) {
    th:nth-child(1),
    td:nth-child(1), /* Hide Fixture ID column */
    th:nth-child(6),
    td:nth-child(6) { /* Hide Venue column */
      display: none !important;
    }
    @media (max-width: 768px) {
      .btn-sm {
        padding: 0.15rem 0.3rem !important;
      }

      .badge {
        font-size: 0.7rem !important;
      }
    }

  }
</style>

@php
if (!function_exists('region_badge_class')) {
    function region_badge_class(?string $short): string {
        if (!$short) return 'bg-label-secondary';

        $map = [
            'Plat' => 'bg-label-primary',
            'Wine' => 'bg-label-info',
            'Drak' => 'bg-label-success',
            'Eden' => 'bg-label-warning',
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

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h3 class="mb-0">
      {{ $event->name }} – Fixtures at {{ $venue->name }}
    </h3>
    @php $today = now()->toDateString(); @endphp
    <div class="d-flex gap-2">
      <a href="{{ route('fixtures.order', ['eventId' => $event->id, 'venueId' => $venue->id, 'date' => $today]) }}"
         class="btn btn-sm btn-success">
        📅 Today’s Order of Play
      </a>
      <a href="{{ route('events.show', $event->id) }}" class="btn btn-sm btn-danger">Back to Event</a>
    </div>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Team 1</th>
            <th>Team 2</th>
            <th>Score</th>
            <th>Time</th>
            <th>Venue</th>
          </tr>
        </thead>
        <tbody>
          @forelse($fixtures as $fx)
            @php
              $homeClass = $awayClass = '';
              if ($fx->fixtureResults && $fx->fixtureResults->count()) {
                  $lastSet = $fx->fixtureResults->last();
                  if ($lastSet->team1_score > $lastSet->team2_score) {
                      $homeClass = 'winner-home'; $awayClass = 'loser-home';
                  } elseif ($lastSet->team2_score > $lastSet->team1_score) {
                      $homeClass = 'loser-home'; $awayClass = 'winner-home';
                  } else {
                      $homeClass = $awayClass = 'draw-cell';
                  }
              }
            @endphp

            <tr id="row-{{ $fx->id }}">
              <td>{{ $fx->id }} ({{ $fx->match_nr }})</td>

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

           <td id="result-col-{{ $fx->id }}" class="text-center">
  @if(Auth::check() && in_array(Auth::id(), [584, 1764]))
    {{-- 🔹 Admin view --}}
    @if($fx->fixtureResults->isEmpty())
      {{-- No score yet --}}
      <button type="button"
              class="btn btn-sm btn-outline-secondary edit-score-btn p-1 px-2"
              id="edit-btn-{{ $fx->id }}"
              data-id="{{ $fx->id }}"
              data-home="{{ $fx->team1->pluck('full_name')->implode(' + ') }}"
              data-away="{{ $fx->team2->pluck('full_name')->implode(' + ') }}"
              title="Enter Score"
              style="font-size: 0.7rem;">
        <i class="ti ti-pencil" style="font-size: 0.8rem;"></i>
      </button>
    @else
      {{-- Show results + edit button --}}
      @foreach($fx->fixtureResults as $i => $r)
        <span class="badge bg-info text-dark me-1" style="font-size: 0.75rem;">
          {{ $r->team1_score }} - {{ $r->team2_score }}
        </span>
      @endforeach

      <button type="button"
              class="btn btn-sm btn-outline-primary edit-score-btn p-1 px-2"
              id="edit-btn-{{ $fx->id }}"
              data-id="{{ $fx->id }}"
              data-home="{{ $fx->team1->pluck('full_name')->implode(' + ') }}"
              data-away="{{ $fx->team2->pluck('full_name')->implode(' + ') }}"
              @foreach($fx->fixtureResults as $i => $r)
                data-set{{ $i+1 }}_home="{{ $r->team1_score }}"
                data-set{{ $i+1 }}_away="{{ $r->team2_score }}"
              @endforeach
              title="Edit Score"
              style="font-size: 0.7rem;">
        <i class="ti ti-pencil" style="font-size: 0.8rem;"></i>
      </button>
    @endif
  @else
    {{-- 🔸 Public view --}}
    @forelse($fx->fixtureResults as $r)
      <span class="badge bg-info text-dark me-1" style="font-size: 0.75rem;">
        {{ $r->team1_score }} - {{ $r->team2_score }}
      </span>
    @empty
      <span class="text-muted">No score</span>
    @endforelse
  @endif
</td>


            <td>
  @if($fx->scheduled_at)
    {{ \Carbon\Carbon::parse($fx->scheduled_at)->format('D H:i') }}
  @else
    <span class="text-muted">—</span>
  @endif
</td>


              <td>{{ optional($fx->venue)->name ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No fixtures scheduled for this venue.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Score Modal -->
<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="scoreForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Enter Score (Best of 3 Sets)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p><strong id="fixtureTeams"></strong></p>
          <input type="hidden" name="fixture_id" id="fixture_id">

        <table class="table table-sm">
  <thead>
    <tr>
      <th>Set</th>
      <th id="modalPlayer1">Home</th>
      <th id="modalPlayer2">Away</th>
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
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(function () {
  const $scoreModal = $('#scoreModal');
  const $scoreForm  = $('#scoreForm');
  const $teams      = $('#fixtureTeams');

  function updateWinnerClasses($row, winner) {
    $row.find('.home-cell, .away-cell').removeClass('winner-home loser-home draw-cell');
    if (winner === 'home') {
      $row.find('.home-cell').addClass('winner-home');
      $row.find('.away-cell').addClass('loser-home');
    } else if (winner === 'away') {
      $row.find('.home-cell').addClass('loser-home');
      $row.find('.away-cell').addClass('winner-home');
    } else if (winner === 'draw') {
      $row.find('.home-cell, .away-cell').addClass('draw-cell');
    }
  }

 $(document).on('click', '.edit-score-btn', function () {
  const fixtureId = $(this).data('id');
  const home = $(this).data('home') || 'Home';
  const away = $(this).data('away') || 'Away';
  
  // 🟢 Update the modal labels
  $teams.text(`${home} vs ${away}`);
  $('#modalPlayer1').text(home);
  $('#modalPlayer2').text(away);

  $scoreForm.data('fixture-id', fixtureId);

  for (let i = 1; i <= 3; i++) {
    $(`#set${i}Home`).val($(this).data(`set${i}_home`) || '');
    $(`#set${i}Away`).val($(this).data(`set${i}_away`) || '');
  }

  $scoreModal.modal('show');
});

  $scoreForm.on('submit', function (e) {
    e.preventDefault();
    const fixtureId = $scoreForm.data('fixture-id');
    const url = "{{ route('frontend.fixtures.saveScore', ':id') }}".replace(':id', fixtureId);

    $.ajax({
      url: url,
      type: 'POST',
      data: $scoreForm.serialize(),
      success: function (data) {
        if (data.success) {
          $(`#result-col-${fixtureId}`).html(data.html);
          updateWinnerClasses($(`#row-${fixtureId}`), data.winner);
          const $btn = $(`#edit-btn-${fixtureId}`);
          for (let i = 1; i <= 3; i++) {
            $btn.data(`set${i}_home`, data.scores[`set${i}_home`] || '');
            $btn.data(`set${i}_away`, data.scores[`set${i}_away`] || '');
          }
          $scoreModal.modal('hide');
          toastr.success('✅ Score saved!');
        } else {
          toastr.error('❌ Save failed.');
        }
      },
      error: function (xhr) {
        console.error('Error saving score:', xhr.responseText);
        toastr.error('❌ Server error.');
      }
    });
  });
});
</script>
@endsection
