@extends('layouts/layoutMaster')

@section('title', 'Player Profile')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
  let currentSearchTerm = '';

  // Ajax search with Select2
  $('#player-search').select2({
    placeholder: 'Search all Cape Tennis player profiles',
    ajax: {
      url: '{{ route("player.search") }}',
      dataType: 'json',
      delay: 250,
      data: function (params) {
        currentSearchTerm = params.term || '';
        return { q: currentSearchTerm, page: params.page || 1, format: 'select2' };
      },
      processResults: function (data, params) {
        let results = data.results || [];

        // Offer creation after every existing matching profile has been reviewed.
        if (!data.pagination?.more) {
          results.push({
            id: 'create_new',
            text: '➕ Create new player "' + currentSearchTerm + '"'
          });
        }

        return { results: results, pagination: data.pagination || { more: false } };
      }
    },
    minimumInputLength: 2
  });

  // Handle selection
  $('#player-search').on('select2:select', function(e) {
    let data = e.params.data;

    if (data.id === 'create_new') {
      // Show Create Form
      $('#create-player-form').removeClass('d-none');
      $('#attach-player-form').addClass('d-none');

      // Pre-fill name/surname from search term
      let term = currentSearchTerm;
      if (term) {
        let parts = term.split(' ');
        $('input[name="player_name"]').val(parts.shift() || '');
        $('input[name="player_surname"]').val(parts.join(' '));
      }
    } else {
      // Attach existing player
      $('#attach-player-id').val(data.id);
      $('#attach-player-form').removeClass('d-none');
      $('#create-player-form').addClass('d-none');
    }
  });
});
</script>
@endsection

@section('content')

@if($errors->any())
  <div class="alert alert-danger" role="alert">
    <strong>We could not link this roster position.</strong>
    <ul class="mb-0 mt-2">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

{{-- 🔹 Search Existing Player --}}
<div class="card mb-4">
  <div class="card-header">Link {{ $name ?? 'this player' }} {{ $surname ?? '' }}</div>
  <div class="card-body">
    <p class="text-muted small">Search every Cape Tennis profile before creating a new one. Search results show only a name and profile number; private contact details and dates of birth are never displayed.</p>
    <select id="player-search" style="width:100%"></select>
  </div>
</div>

{{-- 🔹 Attach Existing Player --}}
<form id="attach-player-form" class="d-none" method="POST" action="{{ route('player.attach') }}">
  @csrf
  <input type="hidden" name="player_id" id="attach-player-id">
  <input type="hidden" name="team" value="{{ $team ?? '' }}">
  <input type="hidden" name="event" value="{{ $event ?? '' }}">
  <input type="hidden" name="noProfile" value="{{ $noProfileId ?? '' }}">
  <div class="card mb-3">
    <div class="card-header">Verify existing profile</div>
    <div class="card-body">
      <p class="text-muted small">If this profile is not already linked to your account, enter the player’s date of birth and the email address or mobile number already recorded on the profile.</p>
      <div class="row g-3">
        <div class="col-12 col-md-5">
          <label for="existing-player-dob" class="form-label">Date of birth</label>
          <input id="existing-player-dob" type="date" name="date_of_birth" class="form-control">
        </div>
        <div class="col-12 col-md-7">
          <label for="existing-player-contact" class="form-label">Recorded email or mobile number</label>
          <input id="existing-player-contact" type="text" name="contact" class="form-control" maxlength="190" autocomplete="off">
        </div>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-success">Link Profile and Continue to Payment</button>
</form>

{{-- 🔹 Create New Player --}}
<form id="create-player-form" class="{{ old('player_name') ? '' : 'd-none' }}" method="POST" action="{{ route('player.store') }}">
  @csrf
  <input type="hidden" name="type" value="noProfile">
  <input type="hidden" name="team" value="{{ $team ?? '' }}">
  <input type="hidden" name="event" value="{{ $event ?? '' }}">
  <input type="hidden" name="noProfile" value="{{ $noProfileId ?? '' }}">

  <div class="card">
    <h5 class="card-header">Create Player</h5>
    <div class="card-body">

      <div class="mb-3">
        <label>Player Name</label>
        <input type="text" name="player_name" class="form-control" value="{{ old('player_name', $name ?? '') }}" required>
      </div>

      <div class="mb-3">
        <label>Player Surname</label>
        <input type="text" name="player_surname" class="form-control" value="{{ old('player_surname', $surname ?? '') }}" required>
      </div>

      <div class="mb-3">
        <label>Date of Birth</label>
        <input type="date" name="dob" class="form-control" value="{{ old('dob', $dob ?? '') }}" required>
      </div>

      <div class="mb-3">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $email ?? '') }}">
      </div>

      <div class="mb-3">
        <label>Cell No.</label>
        <input type="text" name="cell_nr" class="form-control" value="{{ old('cell_nr', $cell_nr ?? '') }}">
      </div>

      <div class="mb-3">
        <label>Gender</label>
        <select name="gender" class="form-select" required>
          <option value="">Select Gender</option>
          <option value="1">Male</option>
          <option value="2">Female</option>
        </select>
      </div>

      <div class="mb-3">
        <label>Coach</label>
        <input type="text" name="coach" class="form-control">
      </div>

    </div>
  </div>

  <button type="submit" class="btn btn-primary mt-3">Create Profile and Continue to Payment</button>
</form>

@endsection
