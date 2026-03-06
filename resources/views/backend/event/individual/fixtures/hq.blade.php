@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Fixtures HQ')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
@endsection

@section('page-style')
<style>
  .fixture-card {
    border-left: 3px solid #696cff;
  }
  .fixture-row:hover {
    background-color: rgba(105,108,255,.05);
  }
  .badge-completed {
    background-color: #28c76f;
  }
  .badge-pending {
    background-color: #ff9f43;
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  @include('backend.event.partials.header', ['event' => $event])

  {{-- STATS ROW --}}
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar avatar-md bg-label-primary rounded">
            <i class="ti ti-list-check ti-md"></i>
          </div>
          <div>
            <h6 class="mb-0">Total Fixtures</h6>
            <span class="fw-semibold fs-5">{{ $stats['totalFixtures'] }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar avatar-md bg-label-success rounded">
            <i class="ti ti-check ti-md"></i>
          </div>
          <div>
            <h6 class="mb-0">Completed</h6>
            <span class="fw-semibold fs-5 text-success">{{ $stats['completedFixtures'] }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar avatar-md bg-label-warning rounded">
            <i class="ti ti-clock ti-md"></i>
          </div>
          <div>
            <h6 class="mb-0">Pending</h6>
            <span class="fw-semibold fs-5 text-warning">{{ $stats['pendingFixtures'] }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- DRAWS LIST --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 class="mb-0">
        <i class="ti ti-tournament me-2"></i>
        Draws & Fixtures
      </h5>
    </div>

    <div class="card-body">
      @forelse($draws as $draw)
        <div class="card fixture-card mb-3">
          <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
              <h6 class="mb-0">
                {{ $draw->drawName }}
                @if($draw->categoryEvent?->category)
                  <span class="badge bg-label-primary ms-2">{{ $draw->categoryEvent->category->name }}</span>
                @endif
              </h6>
              <small class="text-muted">
                {{ $draw->draw_fixtures_count }} fixtures
                @if($draw->groups->count() > 0)
                  • {{ $draw->groups->count() }} groups
                @endif
              </small>
            </div>
            <div class="d-flex gap-2">
              @if($draw->locked)
                <span class="badge bg-label-success">
                  <i class="ti ti-lock me-1"></i> Locked
                </span>
              @endif
              <a href="{{ route('frontend.fixtures.index', $draw) }}" 
                 class="btn btn-sm btn-outline-primary"
                 target="_blank">
                <i class="ti ti-eye me-1"></i> View
              </a>
            </div>
          </div>

          @if($draw->drawFixtures->count() > 0)
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 50px;">#</th>
                    <th>Player 1</th>
                    <th>Player 2</th>
                    <th style="width: 120px;">Score</th>
                    <th style="width: 100px;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($draw->drawFixtures->take(5) as $fixture)
                    <tr class="fixture-row">
                      <td class="text-muted">{{ $fixture->match_nr ?? $loop->iteration }}</td>
                      <td>
                        @if($fixture->registration1?->players->first())
                          {{ $fixture->registration1->players->first()->full_name ?? $fixture->registration1->players->first()->name }}
                        @else
                          <span class="text-muted">TBD</span>
                        @endif
                      </td>
                      <td>
                        @if($fixture->registration2?->players->first())
                          {{ $fixture->registration2->players->first()->full_name ?? $fixture->registration2->players->first()->name }}
                        @else
                          <span class="text-muted">TBD</span>
                        @endif
                      </td>
                      <td>
                        @if($fixture->fixtureResults->count() > 0)
                          <span class="fw-semibold">{{ $fixture->score }}</span>
                        @else
                          <span class="text-muted">–</span>
                        @endif
                      </td>
                      <td>
                        @if($fixture->winner_registration)
                          <span class="badge badge-completed text-white">Completed</span>
                        @else
                          <span class="badge badge-pending text-white">Pending</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            @if($draw->drawFixtures->count() > 5)
              <div class="card-footer text-center py-2">
                <a href="{{ route('frontend.fixtures.index', $draw) }}" class="text-primary">
                  View all {{ $draw->drawFixtures->count() }} fixtures
                  <i class="ti ti-arrow-right ms-1"></i>
                </a>
              </div>
            @endif
          @else
            <div class="card-body text-muted text-center py-4">
              <i class="ti ti-calendar-off ti-lg mb-2 d-block"></i>
              No fixtures generated yet
            </div>
          @endif
        </div>
      @empty
        <div class="text-center py-5 text-muted">
          <i class="ti ti-tournament ti-xl mb-3 d-block"></i>
          <h6>No Draws Created</h6>
          <p class="mb-3">Create draws from the Categories & Entries page to generate fixtures.</p>
          <a href="{{ route('admin.events.entries.new', $event) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Manage Categories & Entries
          </a>
        </div>
      @endforelse
    </div>
  </div>

</div>
@endsection
