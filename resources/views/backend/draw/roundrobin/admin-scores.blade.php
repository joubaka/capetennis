@extends('layouts/contentNavbarLayout')

@section('title', 'RR Score Entry — ' . ($draw->name ?? 'Draw'))

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container py-4">

  <h3 class="mb-1">Round Robin — {{ $draw->name }}</h3>
  <small class="text-muted">{{ $draw->category->name ?? '' }} @ {{ $draw->event->name ?? '' }}</small>

  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0">Score Entry</h5>
    </div>

    <div class="card-body p-0">
      <table class="table table-sm table-hover mb-0" id="rr-score-table">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Player 1</th>
            <th class="text-center">VS</th>
            <th>Player 2</th>
            <th class="text-center">Round</th>
            <th class="text-center">Score</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($fixtures as $fx)
       @php
    // Winner/loser IDs
    $winner = $fx->winner_id;
    $loser  = $fx->loser_id;

    // Safe registration objects
    $reg1 = $fx->registration1;
    $reg2 = $fx->registration2;

    // Safe player names (+ fallback)
    $p1 = $reg1?->players?->first()?->full_name ?? 'TBD';
    $p2 = $reg2?->players?->first()?->full_name ?? 'TBD';

    // Row colours
    $cls1 = $winner === $fx->registration1_id ? 'bg-success text-white' :
            ($loser === $fx->registration1_id ? 'bg-danger text-white' : '');

    $cls2 = $winner === $fx->registration2_id ? 'bg-success text-white' :
            ($loser === $fx->registration2_id ? 'bg-danger text-white' : '');
@endphp


<tr>
    <td>{{ $fx->id }}</td>

    <td class="{{ $cls1 }}">{{ $p1 }}</td>
    <td class="text-center">vs</td>
    <td class="{{ $cls2 }}">{{ $p2 }}</td>

    <td class="text-center">{{ $fx->round_nr ?? '-' }}</td>

    <td class="text-center fw-bold">
        {{ $fx->score ?? '' }}
    </td>

    <td class="text-center">
        <button class="btn btn-sm btn-primary rr-open-modal"
                data-id="{{ $fx->id }}"
                data-home="{{ $p1 }}"
                data-away="{{ $p2 }}">
            Enter
        </button>
    </td>
</tr>
          @endforeach
        </tbody>
      </table>
    </div>

  </div>

</div>

<!-- MODAL -->
<div class="modal fade" id="rrScoreModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="rr-score-modal-form" class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Enter Score</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="rr-fixture-id">

        <div class="mb-2 fw-bold" id="rr-match-label"></div>

        <!-- SETS -->
        @for($i = 1; $i <= 3; $i++)
          <div class="row g-2 mb-2">
            <div class="col-12 fw-bold">Set {{ $i }}</div>
            <div class="col-6">
              <label class="form-label">
                <span class="rr-p1-label">Player 1</span>
              </label>
              <input type="number" min="0" class="form-control rr-s{{ $i }}-p1">
            </div>
            <div class="col-6">
              <label class="form-label">
                <span class="rr-p2-label">Player 2</span>
              </label>
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

@endsection

@section('page-script')

<script>
  window.RR_SAVE_SCORE_URL =
    "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}";
</script>
  <script>
    $.ajaxSetup({
      headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
  });
  </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="{{ asset('assets/js/roundrobin-admin-scores.js') }}"></script>

@endsection

            <th class="text-center">Score</th>
           
        </tr>
    </thead>
    <tbody></tbody>
</table>

        </div>
      </div>
        </div>
     
    </div>
