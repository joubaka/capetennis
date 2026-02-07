{{-- resources/views/backend/series/rankings/index.blade.php --}}
@extends('layouts/layoutMaster')

@section('title', 'Rankings — ' . $series->name)

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
@endsection

@section('page-style')
<style>
  .rank-card .card-header {
    background:#e9ecef;
    border-bottom:1px solid #dee2e6
  }
  .rank-card .card {
    border-radius:.75rem;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.05)
  }
  .rank-card table th {
    text-transform:uppercase;
    letter-spacing:.04em;
    font-size:.75rem;
    color:#6c757d
  }
  .rank-card .total {font-weight:700}
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  {{-- Page heading --}}
  <h4 class="fw-bold py-2 mb-4">
    <span class="text-muted fw-light">Series /</span> Rankings — {{ $series->name }}
  </h4>

  {{-- Flash report --}}
  @if(session('calc_report'))
    @php $report = session('calc_report'); @endphp
    <div class="alert alert-info">
      <div class="fw-bold mb-2">Rankings recalculated</div>
      @foreach($report as $r)
        <div class="mb-2 p-2 border rounded">
          <div class="d-flex justify-content-between">
            <span><strong>{{ $r['list_name'] }}</strong></span>
            <span class="badge bg-label-{{ $r['status']==='ok' ? 'success' : 'secondary' }}">
              {{ $r['status'] }}
            </span>
          </div>
          <div class="small text-muted">
            Events in list: {{ $r['events_count'] }} • Players scored: {{ $r['players_scored'] }}
          </div>
          <div class="mt-1">
            @forelse($r['categories'] as $c)
              <div>{{ $c['event'] }} — {{ $c['category'] }}</div>
            @empty
              <em>No categories</em>
            @endforelse
          </div>
          @if(!empty($r['notes']))
            <div class="mt-1 text-warning small">
              @foreach($r['notes'] as $n) {{ $n }}<br> @endforeach
            </div>
          @endif
        </div>
      @endforeach
    </div>
  @endif

  <div class="row">
    {{-- Left: Create & Available --}}
    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Create Ranking List</h5></div>
        <div class="card-body">
          <form id="createListForm" action="{{ route('ranking.lists.store', $series->id) }}" method="POST">
            @csrf
            <div class="mb-2">
              <label class="form-label">List Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select" required>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>
            <button class="btn btn-primary w-100" type="submit">Create</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Available Category-Events</h5></div>
        <div class="card-body">
          <div id="availableCats" class="list-group">
            @foreach($series_categories as $event)
              <div class="mb-2 fw-bold">{{ $event->name }}</div>
              @foreach($event->categories as $catEvent)
                <a href="javascript:;" class="list-group-item list-group-item-action"
                   data-category-event-id="{{ $catEvent->pivot->id }}">
                  {{ $event->name }} — {{ $catEvent->name }}
                </a>
              @endforeach
            @endforeach
          </div>
          <small class="text-muted d-block mt-2">Drag onto a list →</small>
        </div>
      </div>
    </div>

    {{-- Middle: Ranking Lists --}}
    <div class="col-lg-6">
      @forelse($series->ranking_lists as $list)
        <div class="card mb-3" data-list-id="{{ $list->id }}">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div>
              <input class="form-control form-control-sm border-0 fw-semibold" style="width:auto"
                     value="{{ $list->name }}" data-rename-input="{{ $list->id }}">
              <small class="text-muted">{{ $list->category->name ?? '' }}</small>
            </div>
            <button class="btn btn-sm btn-outline-danger" data-delete-list="{{ $list->id }}">Delete</button>
          </div>
          <div class="card-body">
            <div class="list-group droppable" data-list-body="{{ $list->id }}">
              @foreach($list->rank_cats->sortBy('order') as $rc)
                <a href="javascript:;" class="list-group-item d-flex justify-content-between align-items-center"
                   data-category-event-id="{{ $rc->category_event_id }}">
                  {{ $rc->eventCategory->event->name }} — {{ $rc->eventCategory->category->name }}
                  <span class="badge bg-label-danger" data-remove-cat="{{ $list->id }}">&times;</span>
                </a>
              @endforeach
            </div>
          </div>
        </div>
      @empty
        <div class="alert alert-info">No ranking lists yet. Create one on the left.</div>
      @endforelse
    </div>

    {{-- Right: Settings --}}
    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Settings</h5></div>
        <div class="card-body">
          <form id="settingsForm" action="{{ route('ranking.settings.update', $series->id) }}" method="POST">
            @csrf
            <div class="mb-2">
              <label class="form-label">Best N scores</label>
              <input type="number" min="1" name="nums" class="form-control"
                     value="{{ $series->best_num_of_scores ?? 3 }}">
            </div>
            <div class="mb-2">
              <label class="form-label">Points (Position → Score)</label>
              <div id="pointsRepeater">
                @php
                  $points = $points ?? \App\Models\Point::where('series_id', $series->id)
                              ->orderBy('position')->get();
                @endphp
                @for($i=1; $i<=25; $i++)
                  <div class="d-flex gap-2 mb-1">
                    <input type="text" class="form-control form-control-sm" value="{{ $i }}" disabled>
                    <input type="number" name="position[{{ $i-1 }}]" class="form-control form-control-sm"
                           value="{{ optional($points->firstWhere('position',$i))->score ?? (26-$i)*100 }}">
                  </div>
                @endfor
              </div>
            </div>
            <input type="hidden" name="events" value="{{ $series->events->pluck('id')->implode(',') }}">
            <button class="btn btn-sm btn-primary w-100" type="submit">Save Settings</button>
          </form>
        </div>
      </div>

      <form id="calcForm" action="{{ route('ranking.calculate', $series->id) }}" method="POST" class="card">
        @csrf
        <div class="card-body">
          <button class="btn btn-info w-100" type="submit">Recalculate Rankings</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Rankings Results after Calculation --}}
  @if(!empty($report['lists']))
    <div class="container-xxl py-4">
      <h3 class="mb-4">Calculated Rankings</h3>

     @forelse($series->ranking_lists->chunk(2) as $chunk)
    <div class="row g-4">
      @foreach($chunk as $list)
        <div class="col-md-6">
          <div class="card rank-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4 class="mb-0">
                <span class="badge rounded-pill bg-primary">
                  {{ $list->name ?? ($list->category->name ?? 'Ranking List') }}
                </span>
              </h4>
              <small class="text-muted">{{ $list->category->name ?? 'No category' }}</small>
            </div>

            <div class="card-body p-0">
              @if($list->ranking_scores->count())
                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px">#</th>
                        <th>Player</th>
                        <th style="width:350px">Events & Points</th>
                        <th style="width:100px" class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      @php $rank = 1; @endphp
                      @foreach(($list->ranking_scores ?? collect())->sortByDesc('total_points') as $score)
                        <tr>
                          <td>{{ $rank++ }}</td>
                       <td>
  <a href="javascript:;" class="split-toggle"
     data-score-id="{{ $score->id }}"
     data-primary="{{ $score->primarySchool }}"
     data-high="{{ $score->highSchool }}">
    {{ $score->player?->fullName ?? 'Unknown' }} {{$score->player?->id}}
  </a>

  @if($score->primarySchool)
    <span class="badge bg-success ms-1 split-toggle"
          data-score-id="{{ $score->id }}"
          data-primary="1" data-high="0">U/13</span>
  @elseif($score->highSchool)
    <span class="badge bg-info ms-1 split-toggle"
          data-score-id="{{ $score->id }}"
          data-primary="0" data-high="1">U/14</span>
  @endif
</td>


                          <td>
                            @foreach($score->legs as $leg)
                              <span class="badge bg-label-primary me-1">
                                {{ $leg->event_name }}:
                                {{ $leg->points }}
                                <small class="text-muted">({{ $leg->position }})</small>
                              </span>
                            @endforeach
                          </td>
                          <td class="text-end total">{{ $score->total_points }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="p-3 text-muted">No scores yet.</div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @empty
    <div class="alert alert-info">No ranking lists yet.</div>
  @endforelse
    </div>
  @endif

  {{-- Debug accordion --}}
  <div class="accordion mt-4" id="debugAccordion">
    <div class="accordion-item">
      <h2 class="accordion-header" id="h1">
        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#c1">
          Debug Trace
        </button>
      </h2>
      <div id="c1" class="accordion-collapse collapse">
        <div class="accordion-body">
          <pre class="small mb-0">{{ json_encode($report['debug'] ?? [], JSON_PRETTY_PRINT) }}</pre>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')




@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).on('click', '.split-toggle', function () {
  const scoreId = $(this).data('score-id');
  const isPrimary = $(this).data('primary') == 1;
  const isHigh = $(this).data('high') == 1;

  Swal.fire({
    title: 'Assign player to group',
    input: 'select',
    inputOptions: {
      'primary': 'U/13 (Primary School)',
      'high': 'U/14 (High School)',
      'clear': 'Clear assignment'
    },
    inputValue: isPrimary ? 'primary' : (isHigh ? 'high' : 'clear'),
    showCancelButton: true
  }).then(result => {
    if (result.isConfirmed) {
      $.ajax({
        url: "{{ url('backend/ranking-scores') }}/" + scoreId + "/school",
        type: 'POST', // ✅ force POST
        data: {
          _token: '{{ csrf_token() }}',
          group: result.value
        },
        success: () => location.reload(),
        error: (xhr) => {
          console.error('❌ Error:', xhr.status, xhr.responseText);
        }
      });
    }
  });
});
</script>


@endsection
