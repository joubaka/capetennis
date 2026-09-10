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
      // Confirm the selected profile before reviewing its current details.
      $('#attach-player-id').val(data.id);
      $('#selected-player-summary').text(data.text);
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
    <p class="text-muted small">Search every Cape Tennis profile before creating a new one. Results show the player name, profile number, and a masked email address to help identify the correct profile.</p>
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
    <div class="card-header">Confirm selected profile</div>
    <div class="card-body">
      <p class="fw-semibold mb-2" id="selected-player-summary">Selected Cape Tennis player profile</p>
      <p class="text-muted small">Confirm that this is the correct player. On the next screen you must review and update the player’s full profile before continuing to payment.</p>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="1" id="confirmed-profile" name="confirmed_profile" required>
        <label class="form-check-label" for="confirmed-profile">Yes, this is the correct player profile.</label>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-success">Confirm Profile and Review Details</button>
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
