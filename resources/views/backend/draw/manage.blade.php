<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#settings">Settings</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#players">Players</a></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="settings">
    <form method="POST" action="{{ route('draw.updateSettings', $draw->id) }}">
      @csrf
      <div class="mb-3">
        <label>Draw Name</label>
        <input type="text" class="form-control" name="name" value="{{ $draw->drawName }}">
      </div>
      <div class="mb-3">
        <label>Draw Type</label>
        <select class="form-select" name="draw_type">
          <option value="1">Knockout</option>
          <option value="2">Feed-In</option>
          <option value="3">Round Robin</option>
        </select>
      </div>
      <div class="mb-3">
        <label>Sets</label>
        <input type="number" name="num_sets" value="3" class="form-control">
      </div>
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
  </div>

  <div class="tab-pane fade" id="players">
    <div class="row mt-3">
      <div class="col-md-6">
        <h5>Eligible Players</h5>
        <ul id="eligible-players" class="list-group dropzone bg-light p-2 border rounded">
          @foreach ($eligibleRegistrations as $reg)
            <li class="list-group-item draggable-player" data-player-id="{{ $reg->id }}">
              {{ $reg->players[0]->full_name ?? '-' }}
            </li>
          @endforeach
        </ul>
      </div>
      <div class="col-md-6">
        <h5>Assigned to Draw</h5>
        <ul id="assigned-players" class="list-group dropzone bg-light p-2 border rounded">
          @foreach ($draw->registrations as $reg)
            <li class="list-group-item draggable-player" data-player-id="{{ $reg->id }}">
              {{ $reg->players[0]->full_name ?? '-' }}
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</div>
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
  $(function () {
    const csrf = $('meta[name="csrf-token"]').attr('content');

    $(".dropzone").sortable({
      connectWith: ".dropzone",
      placeholder: "bg-light",
      update: function () {
        const players = [];
        $("#assigned-players .draggable-player").each(function () {
          players.push($(this).data("player-id"));
        });

        $.post("{{ route('draws.players.update', $draw->id) }}", {
          _token: csrf,
          players: players
        }).done(res => {
          console.log(res.message);
        }).fail(err => {
          alert("Failed to update players.");
        });
      }
    }).disableSelection();
  });
</script>
