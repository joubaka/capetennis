<div>
  <h5>Entries for {{ $event->name }}</h5>

  @foreach ($event->eventCategories as $category)
    <div class="mb-4">
      <h6 class="text-primary">{{ $category->category->name ?? 'Unnamed Category' }}</h6>
      <table class="table table-bordered table-sm text-center align-middle">
        <thead class="table-light">
          <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 30%;">Player</th>
            <th style="width: 35%;">Email</th>
            <th style="width: 30%;">Phone</th>
          </tr>
        </thead>
        <tbody>
          @php $row = 1; @endphp
          @forelse ($category->registrations as $registration)
            @forelse ($registration->players as $player)
              <tr>
                <td>{{ $row++ }}</td>
                <td>{{ $player->full_name ?? '—' }}</td>
                <td>{{ $player->email ?? '—' }}</td>
                <td>{{ $player->cellNr ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-muted">No players linked to this registration.</td>
              </tr>
            @endforelse
          @empty
            <tr>
              <td colspan="4" class="text-muted">No registrations found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  @endforeach
</div>
