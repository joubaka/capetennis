<div class="col-12">
  <div class="card-header mb-0">
    <h5 class="m-0 me-2 m-4">{{ $team->name }} (ID: {{ $team->id }})</h5>
  </div>

  <div class="card-body">
    @if((int)$team->published !== 1)
      <div class="mt-4 alert alert-danger" role="alert">
        Team not yet published!
      </div>
    @endif

    @php
      $pivotMembers    = $team->team_players_no_profile; // pivot entries
      $profiledPlayers = $team->players->values();       // ordered real players
    @endphp

    @if($pivotMembers->count() > 0 || $profiledPlayers->count() > 0)
      <div class="table-responsive mt-3">
        <table class="table">
          <thead>
            <tr>
              <th>Nr</th>
              <th>Profile Name</th>
              <th>No-Profile Name</th>
              <th>Email</th>
              <th>Cell</th>
            </tr>
          </thead>
          <tbody>
            @foreach(max($pivotMembers->count(), $profiledPlayers->count()) ? range(0, max($pivotMembers->count(), $profiledPlayers->count()) - 1) : [] as $i)
              @php
                $pivot   = $pivotMembers[$i] ?? null;
                $profile = $pivot?->profile;

                // If no profile relation, but we have a real player at that slot
                if (!$profile && isset($profiledPlayers[$i])) {
                    $profile = $profiledPlayers[$i];
                }
              @endphp
              <tr>
                <td>{{ $i + 1 }}</td>

                {{-- Profile name (from relation or fallback) --}}
                <td>
                  {{ $profile ? $profile->name . ' ' . $profile->surname : '—' }}
                </td>

                {{-- Manual no-profile entry --}}
                <td class="no-profile-name" data-id="{{ $pivot->id }}">
                  {{ $pivot?->name ?? '' }} {{ $pivot?->surname ?? '' }}
                </td>

                {{-- Email --}}
                <td>
                  {{ $profile->email ?? $pivot?->email ?? '' }}
                </td>

                {{-- Cell --}}
                <td>
                  {{ $profile->cellNr ?? $pivot?->cellNr ?? '' }}
                </td>
                {{-- Manual no-profile entry --}}
{{-- No-Profile Name column --}}
<td>
  @if($pivot)
   

    @if(!$pivot->profile)
      <button 
        type="button"
        class="btn btn-sm btn-outline-warning ms-2 edit-noprofile-btn"
        data-id="{{ $pivot->id }}"
        data-name="{{ $pivot->name }}"
        data-surname="{{ $pivot->surname }}"
      >
        Change Dummy Sheet
      </button>
    @endif
  @endif
</td>



              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="alert alert-light m-0 mt-3">
        No players in this team yet.
      </div>
    @endif
  </div>
</div>


