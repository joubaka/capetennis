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
  // Ajax search with Select2
  $('#player-search').select2({
    placeholder: 'Search your existing profiles',
    ajax: {
      url: '{{ route("player.search") }}',
      dataType: 'json',
      delay: 250,
      data: function (params) {
        return { q: params.term, scope: 'owned' };
      },
      processResults: function (data, params) {
        let results = data.map(function(player) {
          return {
            id: player.id,
            text: player.name + ' ' + player.surname + (player.dateOfBirth ? ' · born ' + player.dateOfBirth.substring(0, 10) : '')
          };
        });

        // Always add "Create new" option at the bottom
        results.push({
          id: 'create_new',
          text: '➕ Create new player "' + (params.term || '') + '"'
        });

        return { results: results };
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
      let term = $('#player-search').data('select2').dropdown.$search.val();
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
    <p class="text-muted small">Search profiles already linked to your account. If this player used Cape Tennis before but is not linked to your account, ask the tournament administrator to verify the profile instead of creating a duplicate.</p>
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
  <button type="submit" class="btn btn-success">Attach Player</button>
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

  <button type="submit" class="btn btn-primary mt-3">Create Player</button>
</form>

@endsection
