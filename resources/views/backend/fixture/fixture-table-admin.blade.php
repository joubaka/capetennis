<?php
use App\Helpers\Fixtures;
?>

@php
    // 🎨 Region color mapping
    $regionColors = [
        'Wine' => 'bg-label-success',   // green
        'Drak' => 'bg-label-secondary', // grey
        'Eden' => 'bg-label-info',      // blue
        'Over' => 'bg-label-warning',   // yellow
        'Cape' => 'bg-label-primary',   // blue/purple
        'default' => 'bg-label-light',    // fallback
    ];
@endphp

<h3>
    {{ $fixtures[0]->draw->drawName }} {{ $fixtures[0]->draw->age }}
    <a class="btn btn-danger btn-sm ms-2" href="{{ url()->previous() }}">Back</a>
    <a class="btn btn-primary btn-sm" href="{{ route('fixture.create.pdf', ['fixtures' => $fixtures[0]->draw_id]) }}">Create PDF</a>
</h3>

<div class="table-responsive">
  <table class="table" id="schedule">
    <thead class="table-dark">
      <tr>
        <th width="2%">#</th>
        <th width="5%">Team</th>
        <th>vs</th>
        <th width="5%">Team</th>
        <th>Not Before</th>
        <th>Venue</th>
        <th>Score</th>
      </tr>
    </thead>
    <tbody class="table-border-bottom-0">

      @foreach ($fixtures as $key => $fixture)
        @php
            $color1 = $regionColors[$fixture->region1Name->short_name] ?? 'bg-label-light';
            $color2 = $regionColors[$fixture->region2Name->short_name] ?? 'bg-label-light';
        @endphp

        @if ($fixture->rank_nr == 1)
          <tr class="m-4">
            <td colspan="8">
              <h4>
                {{ $fixture->region1Name->region_name }}
                <span class="badge {{ $color1 }}">{{ $fixture->region1Name->short_name }}</span>
                vs
                {{ $fixture->region2Name->region_name }}
                <span class="badge {{ $color2 }}">{{ $fixture->region2Name->short_name }}</span>
              </h4>
            </td>
          </tr>
        @endif

        <tr id='{{ $fixture->id }}'>
          <td>{{ $fixture->rank_nr }}</td>

          {{-- 🧩 TEAM DISPLAY --}}
          @php
              $winner = Fixtures::getWinner($fixture->id);
          @endphp

          {{-- === TEAM 1 === --}}
          <td class="{{ $winner == 1 ? 'bg-label-success border border-2 border-success' : '' }}">
            <span class="badge {{ $color1 }}">
              {{ $fixture->team1[0]->getFullNameAttribute() ?? Fixtures::getNoProfileTeam($fixture, 1, $fixture->rank_nr) }}
              ({{ $fixture->region1Name->short_name }})
            </span>
          </td>

          <td>vs</td>

          {{-- === TEAM 2 === --}}
          <td class="{{ $winner == 2 ? 'bg-label-success border border-2 border-success' : '' }}">
            <span class="badge {{ $color2 }}">
              {{ $fixture->team2[0]->getFullNameAttribute() ?? Fixtures::getNoProfileTeam($fixture, 2, $fixture->rank_nr) }}
              ({{ $fixture->region2Name->short_name }})
            </span>
          </td>

          {{-- === SCHEDULE === --}}
          <td>
            <span class="time {{ $fixture->schedule ? 'badge bg-label-warning' : 'badge bg-label-danger' }}">
              {{ $fixture->schedule ? date('D d M @ H:i', strtotime($fixture->schedule->time)) : '' }}
            </span>
          </td>

          {{-- === VENUE === --}}
          <td>
            <span class="venue {{ $fixture->schedule ? 'badge bg-label-secondary' : 'badge bg-label-danger' }}">
              {{ $fixture->schedule ? $fixture->schedule->venue->name : '' }}
            </span>
          </td>

          {{-- === RESULT === --}}
          <td class="resultTd">
            @if ($fixture->teamResults->count() > 0)
              <span>
                @foreach ($fixture->teamResults as $key => $result)
                  {{ $result->team1_score }} - {{ $result->team2_score }}
                  @if ($key < count($fixture->teamResults) - 1), @endif
                @endforeach
              </span>
            @else
              <button type="button"
                      data-id="{{ $fixture }}"
                      data-reg1="{{ $fixture->team1->count() > 1 ? $fixture->team1[0]->getFullNameAttribute() . '/' . $fixture->team1[1]->getFullNameAttribute() : $fixture->team1[0]->getFullNameAttribute() }}"
                      data-reg2="{{ $fixture->team2->count() > 1 ? $fixture->team2[0]->getFullNameAttribute() . '/' . $fixture->team2[1]->getFullNameAttribute() : $fixture->team2[0]->getFullNameAttribute() }}"
                      class="btn btn-sm btn-secondary insertResult"
                      data-bs-toggle="modal"
                      data-bs-target="#tennisResultModal">Insert Score</button>
            @endif
          </td>

          {{-- === ACTIONS === --}}
          <td>
            <div class="dropdown">
              <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                <i class="ti ti-dots-vertical"></i>
              </button>
              <div class="dropdown-menu">
                <form action="{{ route('draw.delete.result', $fixture->id) }}" method="post">
                  @csrf
                  <button type="submit" class="dropdown-item"><i class="ti ti-trash me-1"></i>Delete</button>
                </form>

                <button data-bs-toggle="modal" data-bs-target="#change-schedule-modal"
                        type="button" data-id="{{ $fixture }}"
                        class="dropdown-item change-schedule">
                  <i class="ti ti-pencil me-1"></i>Change Schedule
                </button>

                <button data-bs-toggle="modal" data-bs-target="#change-player-modal"
                        type="button" data-id="{{ $fixture }}"
                        class="dropdown-item change-players">
                  <i class="ti ti-pencil me-1"></i>Change Players
                </button>
              </div>
            </div>
          </td>
        </tr>
      @endforeach

    </tbody>
  </table>
</div>

<!-- 🔧 Change Player Modal -->
<div class="modal fade" id="change-player-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Change Players</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="{{ route('fixture.update.players') }}">
          <div class="mb-3">
            <label for="player1" class="form-label">Player 1</label>
            <select name="player1" id="player1" class="form-select">
              <option>Default select</option>
              @foreach ($players as $player)
                <option value="{{ $player->id }}">{{ $player->name }} {{ $player->surname }}</option>
              @endforeach
            </select>
          </div>

          <div class="text-center fw-bold my-2">vs</div>

          <div class="mb-3">
            <label for="player2" class="form-label">Player 2</label>
            <select name="player2" id="player2" class="form-select">
              <option>Default select</option>
              @foreach ($players as $player)
                <option value="{{ $player->id }}">{{ $player->name }} {{ $player->surname }}</option>
              @endforeach
            </select>
          </div>

          <input type="hidden" name="fixture" id="fixutureValue">

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary save-fixture-names">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
