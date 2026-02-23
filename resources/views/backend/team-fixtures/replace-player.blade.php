@extends('layouts/layoutMaster')
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection
@section('content')
<div class="container-xxl">
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Replace Player in Remaining Fixtures</h5>

      <form id="replacePlayerForm" method="POST" action="{{ route('backend.team-fixtures.replacePlayer') }}">
        @csrf

        {{-- Event select (auto selected if $event provided) --}}
        <div class="mb-3">
          <label class="form-label">Event</label>
          <select name="event_id" id="eventSelect" class="form-select" required>
            <option value="">Select event</option>
            @foreach($events as $ev)
              <option value="{{ $ev->id }}" @if(isset($event) && $event->id == $ev->id) selected @endif>{{ $ev->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Old selection: grouped by region -> team -> players (optgroup per region; show region + team) --}}
        <div class="mb-3">
          <label class="form-label">Replace (existing)</label>
          <select id="oldSelect" name="old_id" class="form-select" style="width:100%;" required>
            <option value="">Select player or no-profile</option>

            @if(isset($event) && $event->regions && $event->regions->count())
              @foreach($event->regions as $region)
                <optgroup label="{{ $region->name }}">
                  @foreach($teams->where('region_id', $region->id) as $team)
                    {{-- registered team players --}}
                    @if($team->team_players && $team->team_players->count())
                      @foreach($team->team_players as $tp)
                        @isset($tp->player)
                          @php
                            $rankVal = $tp->rank ?? ($tp->player->rank ?? '');
                          @endphp
                          <option value="p_{{ $tp->player->id }}"
                                  data-team="{{ $team->id }}"
                                  data-region="{{ $region->short_name ?? $region->name }}"
                                  data-rank="{{ $rankVal }}">
                            {{ $tp->player->full_name }} — {{ $region->short_name ?? $region->name }} / {{ $team->name }}
                          </option>
                        @endisset
                      @endforeach
                    @endif

                    {{-- no-profile players on team --}}
                    @foreach($noProfiles->where('team_id', $team->id) as $np)
                      <option value="np_{{ $np->id }}"
                              data-team="{{ $team->id }}"
                              data-region="{{ $region->short_name ?? $region->name }}"
                              data-rank="{{ $np->rank ?? '' }}">
                        NP: {{ trim($np->name.' '.$np->surname) }} — {{ $region->short_name ?? $region->name }} / {{ $team->name }}
                      </option>
                    @endforeach
                  @endforeach
                </optgroup>
              @endforeach
            @else
              {{-- fallback: no event scope --}}
              <optgroup label="Registered players">
                @foreach($players as $p)
                  <option value="p_{{ $p->id }}" data-team="" data-region="" data-rank="{{ $p->rank ?? '' }}">
                    {{ $p->name }} {{ $p->surname }}
                  </option>
                @endforeach
              </optgroup>
              <optgroup label="No-profile players">
                @foreach($noProfiles as $np)
                  <option value="np_{{ $np->id }}" data-team="{{ $np->team_id }}" data-region="" data-rank="{{ $np->rank ?? '' }}">
                    NP: {{ trim($np->name.' '.$np->surname) }}
                  </option>
                @endforeach
              </optgroup>
            @endif
          </select>
          <div class="form-text">Choose the existing player/no-profile entry to replace. Selecting a player will show all remaining fixtures they appear in below.</div>
        </div>

        {{-- Fixtures where selected player appears (populated by AJAX) --}}
        <div class="mb-3">
          <label class="form-label">Fixtures (remaining / no result)</label>
          <div id="playerFixturesContainer" class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Draw</th>
                  <th>Round</th>
                  <th>Tie</th>
                  <th>Home</th>
                  <th>Away</th>
                  <th>Scheduled</th>
                  <th>Venue</th>
                </tr>
              </thead>
              <tbody id="playerFixturesBody">
                <tr><td colspan="8" class="text-muted small">Select a player to list fixtures.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        {{-- New selection & add NP --}}
        <div class="mb-3">
          <label class="form-label">Replacement (new)</label>
          <div class="input-group">
            <select id="newSelect" name="new_id" class="form-select" style="width:100%;" required>
              <option value="">Select replacement</option>
              <optgroup label="Registered players">
                @foreach($players as $p)
                  <option value="p_{{ $p->id }}" data-team="" data-region="" data-rank="{{ $p->rank ?? '' }}">{{ $p->name }} {{ $p->surname }}</option>
                @endforeach
              </optgroup>
              <optgroup label="No-profile players">
                @foreach($noProfiles as $np)
                  <option value="np_{{ $np->id }}" data-team="{{ $np->team_id }}" data-rank="{{ $np->rank ?? '' }}">
                    NP: {{ trim($np->name.' '.$np->surname) }} @if($np->team) ({{ $np->team->name }}) @endif
                  </option>
                @endforeach
              </optgroup>
            </select>
            <button type="button" id="addNoProfileBtn" class="btn btn-outline-secondary" title="Add no-profile player">Add NP</button>
          </div>
          <div class="form-text">Replacement may be a registered player or a no-profile entry.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Side</label>
          <select name="side" class="form-select">
            <option value="both">Both</option>
            <option value="home">Home only</option>
            <option value="away">Away only</option>
          </select>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary">Apply Replacement</button>
          <a href="{{ route('headOffice.show', $event->id ?? (optional($events->first())->id) ) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal: create no-profile quickly --}}
<div class="modal fade" id="addNpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="addNpForm">
        @csrf
        <input type="hidden" id="npRank" name="rank" value="">
        <div class="modal-header">
          <h5 class="modal-title">Add No-Profile Player</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Team</label>
            <select id="npTeam" name="team_id" class="form-select">
              <option value="">None</option>
              @if(isset($teams))
                @foreach($teams as $t)
                  <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
              @endif
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">First name</label>
            <input type="text" id="npName" name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Surname</label>
            <input type="text" id="npSurname" name="surname" class="form-control">
          </div>
          <div class="mb-2">
            <small class="text-muted">Rank will be set to the replaced player's rank by default.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

@section('page-script')
<script>
  (function ($) {
    const $replaceForm = $('#replacePlayerForm');
    const $addNpModal = $('#addNpModal');

    // Initialize Select2 on all selects in the main form
    $replaceForm.find('select').each(function () {
      if (this.id === 'npTeam') return;
      $(this).select2({
        dropdownParent: $replaceForm,
        width: '100%',
        allowClear: true,
        placeholder: 'Select...',
        minimumResultsForSearch: 0
      });
    });

    // Initialize Select2 in modal
    $('#npTeam').select2({
      dropdownParent: $('#addNpModal'),
      width: '100%',
      allowClear: true,
      placeholder: 'Select team (optional)',
      minimumResultsForSearch: 0
    });

    // If event changed, reload page with event query
    $('#eventSelect').on('change', function () {
      const v = $(this).val();
      const url = new URL(window.location.href);
      if (v) url.searchParams.set('event', v); else url.searchParams.delete('event');
      window.location.href = url.toString();
    });

    // When a player is selected, fetch fixtures for this player + event
    $('#oldSelect').on('change', function () {
      const player = $(this).val();
      const eventId = $('#eventSelect').val();
      const $body = $('#playerFixturesBody');
      $body.html('<tr><td colspan="8" class="text-muted small">Loading fixtures…</td></tr>');

      // Prefill modal fields: team and rank
      const $selected = $('#oldSelect option:selected');
      const presetTeam = $selected.data('team') || '';
      const presetRank = $selected.data('rank') || '';

      $('#npTeam').val(presetTeam).trigger('change');
      $('#npRank').val(presetRank);

      if (!player || !eventId) {
        $body.html('<tr><td colspan="8" class="text-muted small">Select an event and player to list fixtures.</td></tr>');
        return;
      }

      $.ajax({
        url: '{{ route("backend.team-fixtures.playerFixtures") }}',
        method: 'GET',
        data: { event_id: eventId, player: player },
        success: function (res) {
          if (!res.success) {
            $body.html('<tr><td colspan="8" class="text-danger small">Error loading fixtures.</td></tr>');
            return;
          }
          const fixtures = res.fixtures || [];
          if (!fixtures.length) {
            $body.html('<tr><td colspan="8" class="text-muted small">No remaining fixtures found for this player.</td></tr>');
            return;
          }
          let rows = '';
          fixtures.forEach(f => {
            rows += `<tr>
              <td>${f.id}</td>
              <td>${f.draw || '—'}</td>
              <td>${f.round ?? ''}</td>
              <td>${f.tie ?? ''}</td>
              <td>${f.home}</td>
              <td>${f.away}</td>
              <td>${f.scheduled ?? '—'}</td>
              <td>${f.venue ?? '—'}</td>
            </tr>`;
          });
          $body.html(rows);
        },
        error: function (xhr) {
          console.error(xhr);
          $body.html('<tr><td colspan="8" class="text-danger small">Error fetching fixtures — check console.</td></tr>');
        }
      });
    });

    // Open modal and preset fields when Add NP button clicked
    $('#addNoProfileBtn').on('click', function () {
      const $sel = $('#oldSelect option:selected');
      const presetTeam = $sel.data('team') || '';
      const presetRank = $sel.data('rank') || '';
      if (presetTeam) $('#npTeam').val(presetTeam).trigger('change');
      $('#npRank').val(presetRank);
      const modal = new bootstrap.Modal(document.getElementById('addNpModal'));
      modal.show();
    });

    // Submit create no-profile via AJAX
    $('#addNpForm').on('submit', function (e) {
      e.preventDefault();
      const teamId = $('#npTeam').val() || null;
      const name = $('#npName').val().trim();
      const surname = $('#npSurname').val().trim();
      const rank = $('#npRank').val() || null;

      if (!name) {
        toastr.warning('Name is required', 'Validation');
        return;
      }

      $.ajax({
        url: '{{ route("backend.team-fixtures.noProfile.create") }}',
        method: 'POST',
        data: {
          _token: '{{ csrf_token() }}',
          team_id: teamId,
          name: name,
          surname: surname,
          rank: rank
        },
        success: function (res) {
          if (!res.success) {
            toastr.error('Failed to create no-profile player', 'Error');
            return;
          }
          const value = 'np_' + res.id;
          const label = res.label + (res.team_id ? ' (' + ($('#npTeam option:selected').text()) + ')' : '');

          // Add to replacement select
          const $newOpt = $('<option/>', { value: value, text: label, selected: true })
            .attr('data-team', res.team_id || '')
            .attr('data-rank', res.rank || '');
          $('#newSelect').append($newOpt).trigger('change');

          const $oldOpt = $('<option/>', { value: value, text: label, selected: false })
            .attr('data-team', res.team_id || '')
            .attr('data-rank', res.rank || '');
          $('#oldSelect').append($oldOpt).trigger('change');

          // Close modal and reset
          const modalEl = document.getElementById('addNpModal');
          bootstrap.Modal.getInstance(modalEl).hide();
          $('#npName').val(''); $('#npSurname').val(''); $('#npTeam').val('').trigger('change'); $('#npRank').val('');

          toastr.success('No-profile player created: ' + res.label, 'Success');
        },
        error: function (xhr) {
          console.error(xhr);
          toastr.error('Error creating no-profile player. See console.', 'Error');
        }
      });
    });

    // Form submit — standard POST (not AJAX) so session flash works on redirect
    // The controller will redirect to headOffice.show with success message
  })(jQuery);
</script>
@endsection

@endsection
