@extends('layouts/layoutMaster')

@section('title', 'Update Player Profiles')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row justify-content-center">
    <div class="col-xl-10 col-lg-12">

      {{-- Header --}}
      <div class="card bg-primary text-white mb-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <h4 class="text-white mb-1">
                <i class="ti ti-users me-2"></i> Update Player Profiles
              </h4>
              <p class="mb-0">Please review and update all player profiles before continuing to event registration.</p>
            </div>
            <div class="text-end">
              <span class="badge bg-white text-primary fs-5" id="pendingBadge">
                <span id="pendingCount">{{ $pendingCount }}</span> pending
              </span>
            </div>
          </div>
        </div>
      </div>

      {{-- Alert Messages --}}
      @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
          <i class="ti ti-alert-triangle me-1"></i> {{ session('warning') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      <div id="alertContainer"></div>

      {{-- Progress Bar --}}
      <div class="card mb-4" id="progressCard" style="{{ $pendingCount > 0 ? '' : 'display:none;' }}">
        <div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted">Profile Update Progress</span>
            <span class="fw-bold" id="progressText">0 of {{ count($playersData) }} completed</span>
          </div>
          <div class="progress" style="height: 8px;">
            <div class="progress-bar bg-success" role="progressbar" id="progressBar" 
                 style="width: {{ $pendingCount > 0 ? (count($playersData) - $pendingCount) / count($playersData) * 100 : 100 }}%"></div>
          </div>
        </div>
      </div>

      {{-- Player Cards --}}
      <div id="playerCards">
        @foreach($playersData as $player)
          <div class="card mb-3 player-card {{ $player['needs_update'] ? 'border-warning' : 'border-success' }}" 
               data-player-id="{{ $player['id'] }}"
               data-needs-update="{{ $player['needs_update'] ? 'true' : 'false' }}">
            <div class="card-header d-flex justify-content-between align-items-center py-3"
                 style="cursor: pointer;"
                 data-bs-toggle="collapse" 
                 data-bs-target="#playerForm{{ $player['id'] }}">
              <div class="d-flex align-items-center">
                <div class="avatar avatar-md me-3">
                  <span class="avatar-initial rounded-circle {{ $player['needs_update'] ? 'bg-label-warning' : 'bg-label-success' }}">
                    <i class="ti {{ $player['needs_update'] ? 'ti-clock' : 'ti-check' }} ti-md"></i>
                  </span>
                </div>
                <div>
                  <h5 class="mb-0">{{ $player['full_name'] }}</h5>
                  <small class="text-muted">
                    @if($player['age'])
                      Age: {{ $player['age'] }} years
                      @if($player['is_minor'])
                        <span class="badge bg-info ms-1">Minor</span>
                      @endif
                    @endif
                  </small>
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-{{ $player['status']['badge'] }} status-badge">
                  <i class="ti {{ $player['status']['icon'] }} me-1"></i>
                  <span class="status-text">{{ $player['status']['message'] }}</span>
                </span>
                <i class="ti ti-chevron-down collapse-icon"></i>
              </div>
            </div>
            
            <div class="collapse {{ $player['needs_update'] ? 'show' : '' }}" id="playerForm{{ $player['id'] }}">
              <div class="card-body border-top">
                <form class="player-form" data-player-id="{{ $player['id'] }}">
                  @csrf
                  <input type="hidden" name="_method" value="PUT">
                  
                  <div class="row">
                    <div class="col-md-6 mb-3">
                      <label class="form-label">First Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="name" value="{{ $player['name'] }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Surname <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="surname" value="{{ $player['surname'] }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                      <input type="text" class="form-control flatpickr-date" name="dateOfBirth" 
                             value="{{ $player['dateOfBirth'] }}" placeholder="YYYY-MM-DD" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Gender <span class="text-danger">*</span></label>
                      <select class="form-select" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" {{ $player['gender'] == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ $player['gender'] == 'Female' ? 'selected' : '' }}>Female</option>
                      </select>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Cell Number <span class="text-danger">*</span></label>
                      <input type="tel" class="form-control" name="cellNr" value="{{ $player['cellNr'] }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                      <label class="form-label">Email</label>
                      <input type="email" class="form-control" name="email" value="{{ $player['email'] }}">
                    </div>
                  </div>
                  
                  @if($player['profile_updated_at'])
                    <small class="text-muted d-block mb-3">
                      <i class="ti ti-clock me-1"></i> Last updated: {{ $player['profile_updated_at'] }}
                    </small>
                  @endif
                  
                  <div class="d-flex gap-2 justify-content-end">
                    @if(!$player['needs_update'] || ($player['gender'] && $player['cellNr'] && $player['dateOfBirth']))
                      <button type="button" class="btn btn-outline-success btn-confirm" data-player-id="{{ $player['id'] }}">
                        <i class="ti ti-check me-1"></i> Confirm Current
                      </button>
                    @endif
                    <button type="submit" class="btn btn-primary btn-save">
                      <i class="ti ti-device-floppy me-1"></i> Save Changes
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Continue Button --}}
      <div class="card mt-4" id="continueCard" style="{{ $pendingCount == 0 ? '' : 'display:none;' }}">
        <div class="card-body text-center py-4">
          <i class="ti ti-circle-check text-success" style="font-size: 3rem;"></i>
          <h4 class="mt-3 mb-2">All Profiles Updated!</h4>
          <p class="text-muted mb-4">All player profiles are now up to date. You can continue with your registration.</p>
          <button type="button" class="btn btn-success btn-lg" id="continueBtn">
            <i class="ti ti-arrow-right me-2"></i> Continue to Registration
          </button>
        </div>
      </div>

      {{-- Help Card --}}
      <div class="card mt-4">
        <div class="card-body">
          <h6><i class="ti ti-info-circle me-2"></i> Why Update Profiles?</h6>
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

    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
  var totalPlayers = {{ count($playersData) }};
  var pendingCount = {{ $pendingCount }};
  var intendedUrl = '{{ $intendedUrl }}';
  var csrfToken = '{{ csrf_token() }}';
  var baseUrl = '{{ url('/') }}';

  console.log('Intended URL:', intendedUrl); // Debug

  // Initialize Flatpickr
  $('.flatpickr-date').flatpickr({
    altInput: true,
    altFormat: 'j F Y',
    dateFormat: 'Y-m-d',
    maxDate: 'today',
    allowInput: true
  });

  // Toggle collapse icon
  $('.card-header[data-bs-toggle="collapse"]').on('click', function() {
    $(this).find('.collapse-icon').toggleClass('ti-chevron-down ti-chevron-up');
  });
  
  // Update progress
  function updateProgress() {
    var completed = totalPlayers - pendingCount;
    var percent = totalPlayers > 0 ? (completed / totalPlayers * 100) : 100;
    
    $('#progressBar').css('width', percent + '%');
    $('#progressText').text(completed + ' of ' + totalPlayers + ' completed');
    $('#pendingCount').text(pendingCount);
    
    if (pendingCount === 0) {
      $('#pendingBadge').removeClass('bg-white text-primary').addClass('bg-success text-white');
      $('#progressCard').slideUp();
      $('#continueCard').slideDown();
    }
  }
  
  // Mark player card as complete
  function markPlayerComplete($card, playerData) {
    $card.attr('data-needs-update', 'false');
    $card.removeClass('border-warning').addClass('border-success');

    var $avatar = $card.find('.avatar-initial');
    $avatar.removeClass('bg-label-warning bg-label-danger').addClass('bg-label-success');
    $avatar.find('i').removeClass('ti-clock ti-alert-circle').addClass('ti-check');

    var $badge = $card.find('.status-badge');
    $badge.removeClass('bg-warning bg-danger').addClass('bg-success');
    $badge.find('i').removeClass('ti-clock ti-alert-circle').addClass('ti-circle-check');
    $badge.find('.status-text').text('Up to date');

    // Show confirm button if not present
    var playerId = $card.attr('data-player-id');
    if ($card.find('.btn-confirm').length === 0) {
      $card.find('.d-flex.gap-2.justify-content-end').prepend(
        '<button type="button" class="btn btn-outline-success btn-confirm" data-player-id="' + playerId + '">' +
        '<i class="ti ti-check me-1"></i> Confirm Current</button>'
      );
    }

    // Collapse the card
    $card.find('.collapse').collapse('hide');

    pendingCount--;
    updateProgress();
  }
  
  // Show alert
  function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
    $('#alertContainer').html(alertHtml);
    $('html, body').animate({ scrollTop: 0 }, 300);
    
    // Auto dismiss success alerts
    if (type === 'success') {
      setTimeout(function() {
        $('#alertContainer .alert').alert('close');
      }, 3000);
    }
  }

  // Handle Save Changes
  $(document).on('submit', '.player-form', function(e) {
    e.preventDefault();

    var $form = $(this);
    var playerId = $form.attr('data-player-id');
    var $card = $form.closest('.player-card');
    var $saveBtn = $form.find('.btn-save');
    var originalHtml = $saveBtn.html();

    // Validate required fields
    var isValid = true;
    $form.find('[required]').each(function() {
      if (!$(this).val()) {
        $(this).addClass('is-invalid');
        isValid = false;
      } else {
        $(this).removeClass('is-invalid');
      }
    });

    if (!isValid) {
      showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> Please fill in all required fields.');
      return;
    }

    $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

    $.ajax({
      url: baseUrl + '/player/' + playerId + '/profile',
      type: 'POST',
      data: $form.serialize(),
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      success: function(response) {
        $saveBtn.prop('disabled', false).html(originalHtml);

        if (response.success) {
          showAlert('success', '<i class="ti ti-check me-1"></i> ' + response.message);
          markPlayerComplete($card, response.player);
        }
      },
      error: function(xhr) {
        $saveBtn.prop('disabled', false).html(originalHtml);
        
        if (xhr.status === 419) {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> Session expired. Refreshing page...');
          setTimeout(function() { window.location.reload(); }, 1500);
        } else if (xhr.status === 422 && xhr.responseJSON?.errors) {
          var errors = [];
          $.each(xhr.responseJSON.errors, function(field, messages) {
            errors.push(messages[0]);
            $form.find('[name="' + field + '"]').addClass('is-invalid');
          });
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> ' + errors.join('<br>'));
        } else {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> An error occurred. Please try again.');
        }
      }
    });
  });
  
  // Handle Confirm Current
  $(document).on('click', '.btn-confirm', function(e) {
    e.preventDefault();
    e.stopPropagation();

    var $btn = $(this);
    var playerId = $btn.attr('data-player-id');
    var $card = $btn.closest('.player-card');
    var originalHtml = $btn.html();

    console.log('Confirming player ID:', playerId); // Debug

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Confirming...');

    $.ajax({
      url: baseUrl + '/player/' + playerId + '/profile/confirm',
      type: 'POST',
      data: { _token: csrfToken },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      success: function(response) {
        $btn.prop('disabled', false).html(originalHtml);

        if (response.success) {
          showAlert('success', '<i class="ti ti-check me-1"></i> ' + response.message);
          markPlayerComplete($card, response.player);
        }
      },
      error: function(xhr) {
        $btn.prop('disabled', false).html(originalHtml);

        if (xhr.status === 419) {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> Session expired. Refreshing page...');
          setTimeout(function() { window.location.reload(); }, 1500);
        } else if (xhr.status === 422) {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> ' + (xhr.responseJSON?.message || 'Profile is incomplete. Please fill in all required fields.'));
        } else {
          showAlert('danger', '<i class="ti ti-alert-circle me-1"></i> An error occurred. Please try again.');
        }
      }
    });
  });
  
  // Clear validation on input
  $(document).on('input change', '.player-form input, .player-form select', function() {
    $(this).removeClass('is-invalid');
  });
  
  // Handle Continue button
  $('#continueBtn').on('click', function() {
    var $btn = $(this);
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Redirecting...');

    // Redirect to intended URL
    window.location.href = intendedUrl;
  });

  // Initial progress update
  updateProgress();
});
</script>
@endsection
