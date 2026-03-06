@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">

@endsection

<!-- Page -->
@section('page-style')

@endsection

@section('vendor-style')
<style>
  .ranking-card { border: 1px solid var(--bs-border-color); border-radius: .5rem; }
  .ranking-card .card-header { background: linear-gradient(90deg, rgba(0,123,255,0.06), rgba(13,110,253,0.02)); }
  .rank-pos { font-weight:700; width:64px; }
  .medal-1 { color: #ffd700; }
  .medal-2 { color: #c0c0c0; }
  .medal-3 { color: #cd7f32; }
  .player-name { font-weight:600; }
  .points { font-weight:700; }
  .legs-badges .badge { margin-right:.25rem; margin-bottom:.25rem; }
</style>
@endsection


@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>

@endsection

@section('page-script')


@endsection

@section('content')

<div class="col-12">
    <div class="card mb-4">
        <div class="card-header">
            <h5>Rankings</h5>
        </div>
        <div class="row g-3">
            @foreach($categories as $category)
            @php
              $rows = $rankings->where('category_id', $category->id)->sortBy('rank_position')->values();
            @endphp

            @if($rows->isNotEmpty())
            <div class=" col-sm-12 col-lg-6">
                <div class="card ranking-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                          <strong>{{ $category->name }}</strong>
                          <div class="text-muted small">{{ $rows->count() }} players</div>
                        </div>
                        <div>
                          <span class="badge bg-primary">{{ $series->name }} {{ $series->year ?? '' }}</span>
                        </div>
                    </div>
                    <div class="card-body p-0">




                  
                        <table class="table table-striped table-hover table-sm mb-0">
                            <thead>
                                <th>Rank</th>
                                <th>Player</th>
                                <th class="text-end">Points</th>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                <tr>
                                    <td class="rank-pos">
                                 
                                        #{{ $row->rank_position }}
                                    
                                    </td>
                                    <td>
                                      <div class="player-name">{{ $row->player->full_name ?? ($row->player->name ?? 'Unknown Player') }}</div>
                                      @if(!empty($row->meta_json['legs']))
                                        <div class="legs-badges mt-1">
                                          @foreach($row->meta_json['legs'] as $leg)
                                            <span class="badge bg-light text-dark">P{{ $leg['position'] ?? '-' }} (E{{ $leg['event_id'] ?? '?' }})</span>
                                          @endforeach
                                        </div>
                                      @endif
                                    </td>
                                    <td class="text-end points">{{ $row->total_points }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>



                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</div>


@endsection
