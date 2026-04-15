@extends('layouts/layoutMaster')

@section('title', 'Update Player Profile')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">

      {{-- Alert Banner --}}
      @if($profileStatus['status'] !== 'current')
        <div class="alert alert-{{ $profileStatus['badge'] }} alert-dismissible mb-4" role="alert">
          <div class="d-flex align-items-center">
            <i class="ti {{ $profileStatus['icon'] }} ti-lg me-3"></i>
            <div>
              <h6 class="alert-heading mb-1">Profile Update Required</h6>
              <p class="mb-0">{{ $profileStatus['message'] }}. Please review and update the information below.</p>
            </div>
          </div>
        </div>
      @endif

      @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <i class="ti ti-alert-triangle me-1"></i> {{ session('warning') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="ti ti-check me-1"></i> {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="ti ti-alert-circle me-1"></i> {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="ti ti-alert-circle me-1"></i>
          <strong>Please fix the following errors:</strong>
          <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      {{-- Profile Card --}}
      <div class="card">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-1">
                <i class="ti ti-user-edit me-2"></i> Update Player Profile
              </h5>
              <p class="text-muted mb-0">{{ $player->full_name }}</p>
            </div>
            <span class="badge bg-{{ $profileStatus['badge'] }}">
              <i class="ti {{ $profileStatus['icon'] }} me-1"></i>
              {{ ucfirst($profileStatus['status']) }}
            </span>
          </div>
        </div>

        <div class="card-body">
          <form action="{{ route('player.profile.update', $player) }}" method="POST" id="profileForm">
            @csrf
            @method('PUT')

            <div class="row">
              {{-- Name --}}
              <div class="col-md-6 mb-3">
                <label class="form-label" for="name">First Name <span class="text-danger">*</span></label>
                <input type="text" 
                       class="form-control @error('name') is-invalid @enderror" 
                       id="name" 
                       name="name" 
                       value="{{ old('name', $player->name) }}" 
                       required>
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Surname --}}
              <div class="col-md-6 mb-3">
                <label class="form-label" for="surname">Surname <span class="text-danger">*</span></label>
                <input type="text" 
                       class="form-control @error('surname') is-invalid @enderror" 
                       id="surname" 
                       name="surname" 
                       value="{{ old('surname', $player->surname) }}" 
                       required>
                @error('surname')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Date of Birth --}}
              <div class="col-md-6 mb-3">
                <label class="form-label" for="dateOfBirth">Date of Birth <span class="text-danger">*</span></label>
                <input type="text" 
                       class="form-control flatpickr-date @error('dateOfBirth') is-invalid @enderror" 
                       id="dateOfBirth" 
                       name="dateOfBirth" 
                       value="{{ old('dateOfBirth', $player->dateOfBirth ? \Carbon\Carbon::parse($player->dateOfBirth)->format('Y-m-d') : '') }}" 
                       placeholder="YYYY-MM-DD"
                       required>
                @error('dateOfBirth')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @if($player->dateOfBirth)
                  <small class="text-muted">
                    Age: {{ \Carbon\Carbon::parse($player->dateOfBirth)->age }} years
                    @if(\Carbon\Carbon::parse($player->dateOfBirth)->age < 18)
                      <span class="badge bg-info ms-1">Minor</span>
                    @endif
                  </small>
                @endif
              </div>

              {{-- Gender --}}
              <div class="col-md-6 mb-3">
                <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
                <select class="form-select @error('gender') is-invalid @enderror" 
                        id="gender" 
                        name="gender" 
                        required>
                  <option value="">Select Gender</option>
                  <option value="Male" {{ old('gender', $player->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                  <option value="Female" {{ old('gender', $player->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('gender')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Cell Number --}}
              <div class="col-md-6 mb-3">
                <label class="form-label" for="cellNr">Cell Number <span class="text-danger">*</span></label>
                <input type="tel" 
                       class="form-control @error('cellNr') is-invalid @enderror" 
                       id="cellNr" 
                       name="cellNr" 
                       value="{{ old('cellNr', $player->cellNr) }}" 
                       placeholder="e.g. 082 123 4567"
                       required>
                @error('cellNr')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>

              {{-- Email --}}
              <div class="col-md-6 mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" 
                       class="form-control @error('email') is-invalid @enderror" 
                       id="email" 
                       name="email" 
                       value="{{ old('email', $player->email) }}" 
                       placeholder="player@example.com">
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            {{-- Last Updated Info --}}
            @if($player->profile_updated_at)
              <div class="alert alert-light mt-3 mb-4">
                <small>
                  <i class="ti ti-clock me-1"></i>
                  Last updated: {{ $player->profile_updated_at->format('d M Y H:i') }}
                  ({{ $player->profile_updated_at->diffForHumans() }})
                </small>
              </div>
            @endif

            {{-- Actions --}}
            <div class="d-flex gap-2 justify-content-between">
              <div>
                @if($player->isProfileComplete() && !$player->needsProfileUpdate())
                  <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i> Back
                  </a>
                @endif
              </div>
              <div class="d-flex gap-2">
                @if($player->isProfileComplete())
                  <button type="button" class="btn btn-outline-success" id="confirmBtn">
                    <i class="ti ti-check me-1"></i> Confirm Current Info
                  </button>
                @endif
                <button type="submit" class="btn btn-primary" id="saveBtn">
                  <i class="ti ti-device-floppy me-1"></i> Save Changes
                </button>
              </div>
            </div>

          </form>

          {{-- Hidden confirm form --}}
          <form id="confirmForm" action="{{ route('player.profile.confirm', $player) }}" method="POST" style="display:none;">
            @csrf
          </form>
        </div>
      </div>

      {{-- Help Card --}}
      <div class="card mt-4">
        <div class="card-body">
          <h6><i class="ti ti-info-circle me-2"></i> Why Update Your Profile?</h6>
          <p class="mb-2">Cape Tennis requires all player profiles to be reviewed annually to ensure:</p>
          <ul class="mb-0">
            <li>Accurate contact information for event communications</li>
            <li>Correct age group placement for competitions</li>
            <li>Emergency contact details are current</li>
          </ul>
          <hr>
          <p class="mb-0">
            <small>Need help? Contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a></small>
          </p>
        </div>
      </div>

      {{-- Remove Player Card --}}
      <div class="card mt-4 border-danger">
        <div class="card-header bg-danger bg-opacity-10">
          <h6 class="mb-0 text-danger">
            <i class="ti ti-user-minus me-2"></i> Remove Player from Account
          </h6>
        </div>
        <div class="card-body">
          <p class="text-muted mb-3">
            If this player should no longer be linked to your account, you can remove them. 
            This will not delete the player's records or history, only unlink them from your account.
          </p>
          <button type="button" class="btn btn-outline-danger" id="removePlayerBtn">
            <i class="ti ti-user-minus me-1"></i> Remove {{ $player->name }} from My Account
          </button>
        </div>
      </div>

    </div>
  </div>

</div>

{{-- Remove Player Modal --}}
<div class="modal fade" id="removePlayerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="ti ti-alert-triangle me-2"></i> Confirm Player Removal
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to remove <strong>{{ $player->name }} {{ $player->surname }}</strong> from your account?</p>
        <div class="alert alert-warning">
          <i class="ti ti-info-circle me-1"></i>
          <strong>What this means:</strong>
          <ul class="mb-0 mt-2">
            <li>You will no longer be able to register this player for events</li>
            <li>The player's historical records will be preserved</li>
            <li>To re-link this player, contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a></li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmRemoveBtn">
          <i class="ti ti-user-minus me-1"></i> Yes, Remove Player
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
  // Initialize Flatpickr for date
  $('.flatpickr-date').flatpickr({
    dateFormat: 'Y-m-d',
    maxDate: 'today',
    allowInput: true
  });

  var $saveBtn = $('#saveBtn');
  var $confirmBtn = $('#confirmBtn');
  var saveOriginalHtml = $saveBtn.html();
  var confirmOriginalHtml = $confirmBtn.length ? $confirmBtn.html() : '';

  // Handle Save Changes
  $('#profileForm').on('submit', function(e) {
    e.preventDefault();

    // Disable button and show loading
    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    var formData = $(this).serialize();

    $.ajax({
      url: $(this).attr('action'),
      type: 'POST',
      data: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      success: function(response) {
        // Show success message
        showAlert('success', '<i class="ti ti-check me-1"></i> ' + (response.message || 'Profile updated successfully!'));

        // Update the status badge if present
        if (response.player) {
          $('.badge.bg-warning, .badge.bg-danger').removeClass('bg-warning bg-danger').addClass('bg-success');
          $('.badge .ti-clock, .badge .ti-alert-circle').removeClass('ti-clock ti-alert-circle').addClass('ti-circle-check');
        }

        // Redirect after delay or stay on page
        setTimeout(function() {
          window.location.href = '{{ session()->pull('url.intended', route('home')) }}';
        }, 1500);
      },
      error: function(xhr) {
        $saveBtn.prop('disabled', false).html(saveOriginalHtml);

        if (xhr.status === 419) {
          // CSRF token expired
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> Session expired. Please refresh the page and try again.');
          setTimeout(function() {
            window.location.reload();
          }, 2000);
        } else if (xhr.status === 422) {
          // Validation errors
          var errors = xhr.responseJSON.errors;
          var errorMessages = [];
          $.each(errors, function(field, messages) {
            errorMessages.push(messages[0]);
            $('#' + field).addClass('is-invalid');
          });
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> ' + errorMessages.join('<br>'));
        } else {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> An error occurred. Please try again.');
        }
      }
    });
  });

  // Handle Confirm Current Info
  $('#confirmBtn').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Confirming...');

    $.ajax({
      url: '{{ route('player.profile.confirm', $player) }}',
      type: 'POST',
      data: {
        _token: '{{ csrf_token() }}'
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      success: function(response) {
        showAlert('success', '<i class="ti ti-check me-1"></i> ' + (response.message || 'Profile confirmed as current!'));

        setTimeout(function() {
          window.location.href = '{{ session()->pull('url.intended', route('home')) }}';
        }, 1500);
      },
      error: function(xhr) {
        $btn.prop('disabled', false).html(confirmOriginalHtml);

        if (xhr.status === 419) {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> Session expired. Please refresh the page and try again.');
          setTimeout(function() {
            window.location.reload();
          }, 2000);
        } else {
          var message = xhr.responseJSON?.error || xhr.responseJSON?.message || 'An error occurred. Please try again.';
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> ' + message);
        }
      }
    });
  });

  // Clear validation errors on input
  $('input, select').on('input change', function() {
    $(this).removeClass('is-invalid');
  });

  // Helper function to show alerts
  function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';

    // Remove existing alerts
    $('.card-body > .alert-dismissible').remove();

    // Add new alert at top of card body
    $('.card-body').first().prepend(alertHtml);

    // Scroll to top of form
    $('html, body').animate({ scrollTop: $('.card').offset().top - 100 }, 300);
  }

  // Handle Remove Player button
  var removeModal = new bootstrap.Modal(document.getElementById('removePlayerModal'));

  $('#removePlayerBtn').on('click', function() {
    removeModal.show();
  });

  $('#confirmRemoveBtn').on('click', function() {
    var $btn = $(this);
    var originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Removing...');

    $.ajax({
      url: '{{ route('player.profile.remove', $player) }}',
      type: 'POST',
      data: {
        _token: '{{ csrf_token() }}',
        _method: 'DELETE'
      },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      success: function(response) {
        removeModal.hide();
        showAlert('success', '<i class="ti ti-check me-1"></i> ' + (response.message || 'Player removed from your account.'));

        setTimeout(function() {
          window.location.href = '{{ route('home') }}';
        }, 1500);
      },
      error: function(xhr) {
        $btn.prop('disabled', false).html(originalHtml);
        removeModal.hide();

        if (xhr.status === 419) {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> Session expired. Please refresh the page and try again.');
          setTimeout(function() {
            window.location.reload();
          }, 2000);
        } else {
          var message = xhr.responseJSON?.error || xhr.responseJSON?.message || 'An error occurred. Please try again.';
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> ' + message);
        }
      }
    });
  });
});
</script>
@endsection
