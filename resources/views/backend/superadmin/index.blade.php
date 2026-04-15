@extends('layouts/layoutMaster')

@section('title', 'Super Admin Dashboard')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Page Header --}}
  <div class="row mb-4">
    <div class="col-12">
      <div class="card bg-primary text-white">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="text-white mb-1">
                <i class="ti ti-shield-check me-2"></i> Super Admin Dashboard
              </h4>
              <p class="mb-0">Manage Cape Tennis system settings, agreements, and users</p>
            </div>
            <div>
              <span class="badge bg-white text-primary fs-6">
                <i class="ti ti-user"></i> {{ auth()->user()->name }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Stats Cards --}}
  <div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="avatar avatar-md mx-auto mb-3">
            <span class="avatar-initial rounded-circle bg-label-primary">
              <i class="ti ti-users ti-md"></i>
            </span>
          </div>
          <h4 class="mb-1">{{ number_format($stats['total_users']) }}</h4>
          <small class="text-muted">Total Users</small>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="avatar avatar-md mx-auto mb-3">
            <span class="avatar-initial rounded-circle bg-label-success">
              <i class="ti ti-user-check ti-md"></i>
            </span>
          </div>
          <h4 class="mb-1">{{ number_format($stats['total_players']) }}</h4>
          <small class="text-muted">Total Players</small>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="avatar avatar-md mx-auto mb-3">
            <span class="avatar-initial rounded-circle bg-label-info">
              <i class="ti ti-calendar-event ti-md"></i>
            </span>
          </div>
          <h4 class="mb-1">{{ number_format($stats['total_events']) }}</h4>
          <small class="text-muted">Total Events</small>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="avatar avatar-md mx-auto mb-3">
            <span class="avatar-initial rounded-circle bg-label-warning">
              <i class="ti ti-calendar ti-md"></i>
            </span>
          </div>
          <h4 class="mb-1">{{ number_format($stats['active_events']) }}</h4>
          <small class="text-muted">Active Events</small>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="avatar avatar-md mx-auto mb-3">
            <span class="avatar-initial rounded-circle bg-label-danger">
              <i class="ti ti-ticket ti-md"></i>
            </span>
          </div>
          <h4 class="mb-1">{{ number_format($stats['total_registrations']) }}</h4>
          <small class="text-muted">Registrations</small>
        </div>
      </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <div class="avatar avatar-md mx-auto mb-3">
            <span class="avatar-initial rounded-circle bg-label-secondary">
              <i class="ti ti-file-check ti-md"></i>
            </span>
          </div>
          <h4 class="mb-1">{{ number_format($agreementStats['total_acceptances']) }}</h4>
          <small class="text-muted">CoC Accepted</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Player Profile Status Cards --}}
  <div class="row mb-4">
    <div class="col-12 mb-2">
      <h5 class="text-muted">
        <i class="ti ti-user-check me-1"></i> Player Profile Status
      </h5>
    </div>
    <div class="col-lg-3 col-md-6 col-6 mb-4">
      <div class="card h-100 border-start border-success border-3">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md me-3">
              <span class="avatar-initial rounded-circle bg-label-success">
                <i class="ti ti-circle-check ti-md"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0">{{ number_format($profileStats['up_to_date']) }}</h4>
              <small class="text-muted">Up to Date</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6 mb-4">
      <div class="card h-100 border-start border-warning border-3">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md me-3">
              <span class="avatar-initial rounded-circle bg-label-warning">
                <i class="ti ti-clock ti-md"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0">{{ number_format($profileStats['needs_update']) }}</h4>
              <small class="text-muted">Needs Update</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6 mb-4">
      <div class="card h-100 border-start border-danger border-3">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md me-3">
              <span class="avatar-initial rounded-circle bg-label-danger">
                <i class="ti ti-alert-circle ti-md"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0">{{ number_format($profileStats['incomplete']) }}</h4>
              <small class="text-muted">Incomplete</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 col-6 mb-4">
      <div class="card h-100 border-start border-secondary border-3">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md me-3">
              <span class="avatar-initial rounded-circle bg-label-secondary">
                <i class="ti ti-user-x ti-md"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0">{{ number_format($profileStats['never_updated']) }}</h4>
              <small class="text-muted">Never Updated</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Left Column --}}
    <div class="col-xl-8">

      {{-- Code of Conduct / Agreements Section --}}
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="ti ti-file-certificate me-2"></i> Code of Conduct Agreements
          </h5>
          <a href="{{ route('backend.agreements.create') }}" class="btn btn-primary btn-sm">
            <i class="ti ti-plus"></i> New Agreement
          </a>
        </div>

        {{-- Active Agreement Status --}}
        @if($agreementStats['active_agreement'])
          <div class="card-body border-bottom">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h6 class="text-primary mb-1">
                  <i class="ti ti-check-circle"></i> Active Agreement
                </h6>
                <p class="mb-0">
                  <strong>{{ $agreementStats['active_agreement']->title }}</strong>
                  <span class="badge bg-success ms-2">{{ $agreementStats['active_agreement']->version }}</span>
                </p>
              </div>
              <div class="col-md-6 text-md-end mt-2 mt-md-0">
                <span class="badge bg-label-success me-2">
                  <i class="ti ti-check"></i> {{ $agreementStats['total_acceptances'] }} Accepted
                </span>
                <span class="badge bg-label-warning">
                  <i class="ti ti-clock"></i> {{ $agreementStats['pending_players'] }} Pending
                </span>
              </div>
            </div>
          </div>
        @else
          <div class="card-body border-bottom">
            <div class="alert alert-warning mb-0">
              <i class="ti ti-alert-triangle me-2"></i>
              No active agreement. Players can register without accepting a Code of Conduct.
              <a href="{{ route('backend.agreements.create') }}" class="alert-link">Create one now</a>.
            </div>
          </div>
        @endif

        {{-- Agreements Table --}}
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Title</th>
                <th>Version</th>
                <th>Status</th>
                <th>Acceptances</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($agreements as $agreement)
                <tr>
                  <td>{{ $agreement->title }}</td>
                  <td><strong>{{ $agreement->version }}</strong></td>
                  <td>
                    @if($agreement->is_active)
                      <span class="badge bg-success">Active</span>
                    @else
                      <span class="badge bg-secondary">Inactive</span>
                    @endif
                  </td>
                  <td>{{ $agreement->playerAgreements()->count() }}</td>
                  <td>{{ $agreement->created_at->format('d M Y') }}</td>
                  <td>
                    <div class="d-flex gap-1">
                      <a href="{{ route('backend.agreements.show', $agreement) }}" class="btn btn-sm btn-icon btn-outline-primary" title="View">
                        <i class="ti ti-eye"></i>
                      </a>
                      @if(!$agreement->is_active)
                        <a href="{{ route('backend.agreements.edit', $agreement) }}" class="btn btn-sm btn-icon btn-outline-warning" title="Edit">
                          <i class="ti ti-pencil"></i>
                        </a>
                      @endif
                      <form action="{{ route('backend.agreements.duplicate', $agreement) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-icon btn-outline-info" title="Duplicate">
                          <i class="ti ti-copy"></i>
                        </button>
                      </form>
                      @if(!$agreement->is_active)
                        <form action="{{ route('backend.agreements.setActive', $agreement) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Set this agreement as active? All players will need to re-accept.');">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-outline-success" title="Activate">
                            <i class="ti ti-check"></i> Activate
                          </button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">
                    <i class="ti ti-file-off ti-lg mb-2 d-block"></i>
                    No agreements created yet.
                    <a href="{{ route('backend.agreements.create') }}">Create the first one</a>.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if($agreements->count() > 0)
          <div class="card-footer">
            <a href="{{ route('backend.agreements.index') }}" class="text-primary">
              View All Agreements <i class="ti ti-arrow-right"></i>
            </a>
          </div>
        @endif
      </div>

      {{-- Recent Code of Conduct Acceptances --}}
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="ti ti-check-circle me-2"></i> Recent Code of Conduct Acceptances
          </h5>
          <a href="{{ route('player.index') }}" class="btn btn-sm btn-outline-success">
            View All Players
          </a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Player</th>
                <th>Agreement</th>
                <th>Accepted By</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentAcceptances as $acceptance)
                <tr>
                  <td>
                    @if($acceptance->player)
                      <a href="{{ route('player.show', $acceptance->player->id) }}" class="fw-bold text-primary">
                        {{ $acceptance->player->name }} {{ $acceptance->player->surname }}
                      </a>
                    @else
                      <span class="text-muted">N/A</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-label-primary">{{ $acceptance->agreement->version ?? 'N/A' }}</span>
                  </td>
                  <td>
                    @if($acceptance->accepted_by_type === 'guardian')
                      <span class="badge bg-info">Guardian</span>
                      <small class="d-block text-muted">{{ $acceptance->guardian_name }}</small>
                    @else
                      <span class="badge bg-primary">Player</span>
                    @endif
                  </td>
                  <td>{{ $acceptance->accepted_at->format('d M Y H:i') }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-center py-4 text-muted">No acceptances yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- Players Needing Profile Attention --}}
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="ti ti-alert-triangle me-2 text-warning"></i> Players Needing Profile Update
          </h5>
          <a href="{{ route('player.index') }}" class="btn btn-sm btn-outline-warning">
            View All Players
          </a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Player</th>
                <th>Parent/Guardian</th>
                <th>Profile Status</th>
                <th>Last Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($playersNeedingAttention as $player)
                @php
                  $status = $player->getProfileStatus();
                @endphp
                <tr>
                  <td>
                    <a href="{{ route('player.show', $player->id) }}" class="fw-bold text-primary">
                      {{ $player->name }} {{ $player->surname }}
                    </a>
                    @if($player->isMinor())
                      <span class="badge bg-label-info ms-1" title="Minor">
                        <i class="ti ti-user-heart"></i>
                      </span>
                    @endif
                  </td>
                  <td>
                    @if($player->user)
                      <a href="{{ route('user.show', $player->user->id) }}" class="text-muted">
                        {{ $player->user->name }}
                      </a>
                      <small class="d-block text-muted">{{ $player->user->email }}</small>
                    @else
                      <span class="text-muted">N/A</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-{{ $status['badge'] }}">
                      <i class="ti {{ $status['icon'] }} me-1"></i>
                      {{ $status['message'] }}
                    </span>
                  </td>
                  <td>
                    @if($player->profile_updated_at)
                      {{ $player->profile_updated_at->format('d M Y') }}
                      <small class="d-block text-muted">{{ $player->profile_updated_at->diffForHumans() }}</small>
                    @else
                      <span class="text-danger">Never</span>
                    @endif
                  </td>
                  <td>
                    <a href="{{ route('player.edit', $player->id) }}" class="btn btn-sm btn-icon btn-outline-primary" title="Edit Player">
                      <i class="ti ti-pencil"></i>
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center py-4">
                    <i class="ti ti-check-circle ti-lg text-success d-block mb-2"></i>
                    <span class="text-muted">All player profiles are up to date!</span>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($playersNeedingAttention->count() >= 10)
          <div class="card-footer text-center">
            <a href="{{ route('player.index') }}" class="text-warning">
              <i class="ti ti-alert-triangle me-1"></i>
              More players may need attention - View All Players
            </a>
          </div>
        @endif
      </div>

    </div>

    {{-- Right Column --}}
    <div class="col-xl-4">

      {{-- Quick Actions --}}
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="ti ti-bolt me-2"></i> Quick Actions
          </h5>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="{{ route('backend.agreements.create') }}" class="btn btn-outline-primary">
              <i class="ti ti-file-plus me-2"></i> Create New Agreement
            </a>
            <a href="{{ route('backend.agreements.index') }}" class="btn btn-outline-secondary">
              <i class="ti ti-files me-2"></i> Manage Agreements
            </a>
            <hr>
            <a href="{{ route('user.index') }}" class="btn btn-outline-info">
              <i class="ti ti-users me-2"></i> Manage Users
            </a>
            <a href="{{ route('player.index') }}" class="btn btn-outline-success">
              <i class="ti ti-user-check me-2"></i> Manage Players
            </a>
            <a href="{{ route('settings.index') }}" class="btn btn-outline-warning">
              <i class="ti ti-settings me-2"></i> Site Settings
            </a>
          </div>
        </div>
      </div>

      {{-- Recent Users --}}
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="ti ti-user-plus me-2"></i> Recent Users
          </h5>
          <a href="{{ route('user.index') }}" class="btn btn-sm btn-outline-primary">
            View All
          </a>
        </div>
        <ul class="list-group list-group-flush">
          @forelse($recentUsers as $user)
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <a href="{{ route('user.show', $user) }}" class="fw-bold text-primary">{{ $user->name }}</a>
                <small class="d-block text-muted">{{ $user->email }}</small>
              </div>
              <small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
            </li>
          @empty
            <li class="list-group-item text-center text-muted py-4">No users yet.</li>
          @endforelse
        </ul>
      </div>

      {{-- Support Info --}}
      <div class="card">
        <div class="card-body">
          <h6><i class="ti ti-headset me-2"></i> Support</h6>
          <p class="mb-2">For technical support or questions:</p>
          <a href="mailto:support@capetennis.co.za" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-mail me-1"></i> support@capetennis.co.za
          </a>
        </div>
      </div>

    </div>
  </div>

</div>

@if(session('success'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof toastr !== 'undefined') {
        toastr.success('{{ session('success') }}');
      }
    });
  </script>
@endif

@if(session('error'))
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof toastr !== 'undefined') {
        toastr.error('{{ session('error') }}');
      }
    });
  </script>
@endif

@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Initialize DataTables if needed
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            responsive: true,
            pageLength: 10
        });
    }
});
</script>
@endsection
