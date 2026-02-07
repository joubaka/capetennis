@extends('layouts/layoutMaster')

@section('title', 'Scoreboard: ' . $event->name)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endsection

@section('content')
<style>
  .scoreboard-layout {
    display: grid;
    grid-template-columns: 2fr 0.7fr;
    gap: 1.5rem;
  }
  .global-sticky-header {
    position: sticky;
    top: 65px;
    z-index: 1040;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
  }
  .scoreboard-table {
    width: 100%;
    table-layout: fixed;
  }
  .ranking-sidebar {
    position: sticky;
    top: 80px;
    height: calc(100vh - 100px);
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
  }
  .ranking-item {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 8px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 6px;
    cursor: grab;
  }
  .ranking-item.dragging {
    background: #e0f7ff;
    border-color: #00aaff;
  }
  .ranking-index {
    width: 22px;
    text-align: right;
    font-weight: 600;
    color: #6c757d;
  }
  .ranking-badge {
    margin-left: 4px;
    font-size: 0.7rem;
  }
  .save-rank-btn {
    width: 100%;
    margin-top: 0.5rem;
  }
  .col-pair { width: 90px; }
  .col-player { width: 250px; }
  .col-wins, .col-losses { width: 160px; }
  .col-sets { width: 120px; }
  .col-diff { width: 100px; }
  .col-points { width: 100px; }
</style>

<div class="container py-4">

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">{{ $event->name }} — Scoreboard</h4>

    <button id="toggleExcludeBtn" class="btn btn-sm btn-primary">
      {{ $excluded ? 'Show All Regions' : 'Exclude ZF' }}
    </button>
  </div>

  @if($excluded)
  <div class="alert alert-warning">
    Showing results <strong>without</strong> region: <b>{{ strtoupper($excluded) }}</b>
    <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary ms-3">Reset Filter</a>
  </div>
  @endif

  <div class="scoreboard-layout">

    {{-- === LEFT COLUMN (TABLES) === --}}
    <div class="scoreboard-main">
      <div class="global-sticky-header">
        <table class="table table-sm table-bordered mb-0 align-middle text-center">
          <thead class="table-light">
            <tr>
              <th class="col-pair">Pair</th>
              <th class="col-player">Player (Region)</th>
              <th class="col-wins">Wins</th>
              <th class="col-losses">Losses</th>
              <th class="col-sets">Sets (W/L)</th>
              <th class="col-diff">Set Diff</th>
              <th class="col-points">Points</th>
            </tr>
          </thead>
        </table>
      </div>

      @foreach($playerStats as $groupName => $players)
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-primary text-white">
            <strong>{{ $groupName }}</strong>
          </div>

          <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0 align-middle scoreboard-table text-center">
              <tbody>
                @php $lastPair = null; @endphp
                @foreach($players as $p)
                  @php
                    $pairIndex = ceil(($p['rank'] ?? 99) / 2);
                    $pairLabel = (($pairIndex * 2) - 1) . '/' . ($pairIndex * 2);
                    $setDiff = ($p['sets_won'] ?? 0) - ($p['sets_lost'] ?? 0);
                    $regionShort = $p['region_short'] ?? ($p['regions']['short_name'] ?? null);
                  @endphp
                  <tr>
                    <td class="col-pair fw-bold">
                      @if($lastPair !== $pairIndex)
                        {{ $pairLabel }}
                        @php $lastPair = $pairIndex; @endphp
                      @endif
                    </td>

                    <td class="col-player text-start">
                      {{ $p['name'] }}
                      @if($regionShort)
                        <span class="badge bg-label-info ranking-badge">{{ $regionShort }}</span>
                      @endif
                      ({{$p['rank']}})
                    </td>

                    <td class="col-wins">
                      {{ $p['wins'] ?? 0 }}
                    @foreach($p['won_against'] as $opponent)
  <div>
    {{ is_array($opponent) ? $opponent['name'] : $opponent }}
    @if(is_array($opponent) && !empty($opponent['score']))
      <span class="text-muted">({{ $opponent['score'] }})</span>
    @endif
  </div>
@endforeach

                    </td>

                    <td class="col-losses">
                      {{ $p['losses'] ?? 0 }}
                     @if(!empty($p['lost_to']))
  <div class="small text-danger mt-1">
  @foreach($p['lost_to'] as $opponent)
  <div>
    {{ is_array($opponent) ? $opponent['name'] : $opponent }}
    @if(is_array($opponent) && !empty($opponent['score']))
      <span class="text-muted">({{ $opponent['score'] }})</span>
    @endif
  </div>
@endforeach

  </div>
@endif

                    </td>

                    <td class="col-sets fw-bold">{{ $p['sets_won'] ?? 0 }}–{{ $p['sets_lost'] ?? 0 }}</td>

                    <td class="col-diff fw-bold">
                      @if($setDiff > 0)
                        <span class="text-success">+{{ $setDiff }}</span>
                      @elseif($setDiff < 0)
                        <span class="text-danger">{{ $setDiff }}</span>
                      @else
                        <span class="text-muted">{{ $setDiff }}</span>
                      @endif
                    </td>

                    <td class="col-points fw-bold">{{ $p['points'] ?? 0 }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endforeach
    </div>

    {{-- === RIGHT COLUMN (DRAGGABLE LISTS) === --}}
    <div class="ranking-sidebar">
      <h5 class="fw-bold mb-3">Draggable Rankings</h5>

      @foreach($flatPlayersByGroup as $groupKey => $players)
        <div class="ranking-block">
          <h6 class="fw-bold mb-2">{{ $groupKey }}</h6>
          <div id="rankingList_{{ Str::slug($groupKey) }}" class="ranking-list">
            @foreach($players as $index => $p)
              @php $regionShort = $p['region_short'] ?? ($p['region']['short_name'] ?? null); @endphp
              <div class="ranking-item" data-id="{{ $p['id'] }}">
                <div class="ranking-index">{{ $index + 1 }}.</div>
                <div>
                  {{ $p['name'] }}
                  @if($regionShort)
                    <span class="badge bg-label-info ranking-badge">{{ $regionShort }}</span>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
          <button class="btn btn-success btn-sm save-rank-btn mt-2" data-group="{{ Str::slug($groupKey) }}">
            💾 Save {{ $groupKey }}
          </button>
        </div>
      @endforeach
    </div>

  </div>
</div>

<script>
$(function () {
  const btn = $('#toggleExcludeBtn');
  const regionShort = '{{ strtolower($event->regions->firstWhere("region_name", "like", "%ZF Mcawu%")?->short_name ?? "zfm") }}';

  btn.on('click', function () {
    const currentUrl = new URL(window.location.href);
    const exclude = currentUrl.searchParams.get('exclude');
    if (exclude === regionShort) {
      currentUrl.searchParams.delete('exclude');
      toastr.info('Showing all teams again');
    } else {
      currentUrl.searchParams.set('exclude', regionShort);
      toastr.info('Excluding ' + regionShort.toUpperCase() + ' teams');
    }
    window.location.href = currentUrl.toString();
  });

  // Renumber after drag
  function updateNumbers(listEl) {
    $(listEl).children('.ranking-item').each(function (i) {
      $(this).find('.ranking-index').text((i + 1) + '.');
    });
  }

  $('.ranking-list').each(function () {
    const list = this;
    new Sortable(list, {
      animation: 150,
      onStart: e => e.item.classList.add('dragging'),
      onEnd: e => {
        e.item.classList.remove('dragging');
        updateNumbers(list);
      }
    });
  });

  $('.save-rank-btn').on('click', function () {
    const group = $(this).data('group');
    const order = [];
    $('#rankingList_' + group + ' .ranking-item').each(function () {
      order.push($(this).data('id'));
    });

    console.log('Saving ranking for group:', group, order);
    toastr.success('Ranking order for ' + group + ' saved (demo)');
  });
});
</script>
@endsection
