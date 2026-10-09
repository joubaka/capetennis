<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="row g-3">

  @if($event->isInterprovincialTrials() && auth()->check() && (auth()->user()->hasRole('super-user') || (auth()->user()->hasRole('admin') && auth()->user()->is_event_admin($event->id))))
  <div class="col-12">
    <div class="card border border-primary h-100">
      <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
          <h5 class="mb-1"><i class="ti ti-user-check me-1 text-primary"></i>Nominations &amp; invitations</h5>
          <p class="text-muted mb-0">Find existing Cape Tennis players, nominate them per trials category, and prepare the reviewed invitation list.</p>
        </div>
        <a class="btn btn-primary flex-shrink-0" href="{{ route('backend.interprovincial-trials.invitations.index', $event) }}">Manage nominations &amp; invitations</a>
      </div>
    </div>
  </div>
  @endif

  {{-- CONTEXTUAL MUTATION — navigation lives in the shared event header. --}}
  <div class="col-xl-4 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-world ti-md text-primary"></i>
        <h5 class="mb-0">Results publication</h5>
      </div>
      <div class="card-body d-grid gap-2">
        <p class="text-muted mb-2">{{ $event->results_published == 1 ? 'Final positions are currently visible to the public.' : 'Final positions remain private until you publish them.' }}</p>
        <form method="POST" action="{{ route('result.publish', $event->id) }}" class="d-inline"
              onsubmit="return confirm('Are you sure you want to {{ $event->results_published == 1 ? 'unpublish' : 'publish' }} the results?')">
          @csrf
          <button type="submit" class="btn btn-{{ $event->results_published == 1 ? 'danger' : 'success' }}">
            <i class="ti ti-{{ $event->results_published == 1 ? 'eye-off' : 'eye' }} me-1"></i>
            {{ $event->results_published == 1 ? 'Unpublish Results' : 'Publish Results' }}
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- QUICK STATS --}}
  <div class="col-xl-8 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-chart-bar ti-md text-info"></i>
        <h5 class="mb-0">Quick Stats</h5>
      </div>

      <div class="card-body">
        <ul class="list-unstyled mb-0 d-grid gap-1">
          <li>
            Categories:
            <span class="fw-semibold float-end">{{ $stats['categories'] }}</span>
          </li>
          <li>
            Entries:
            <span class="fw-semibold float-end">{{ $stats['entries'] }}</span>
          </li>
          <li>
            Matches:
            <span class="fw-semibold float-end">
              {{ $stats['matchesPlayed'] }} / {{ $stats['matchesTotal'] }}
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>

</div>

</div>
