<table>
  <thead>
    <tr>
      <th>Region</th>
      <th>Team</th>
      <th>Team Published</th>
      <th>Rank</th>
      <th>Player Type</th>
      <th>Player Name</th>
      <th>Email</th>
      <th>Cell</th>
      <th>Pay Status</th>
    </tr>
  </thead>
  <tbody>
    @foreach($event->regions as $region)
      @foreach($region->teams as $team)
        {{-- ✅ Profile Players --}}
        @foreach($team->players as $player)
          <tr>
            <td>{{ $region->region_name }}</td>
            <td>{{ $team->name }}</td>
            <td>{{ $team->published ? 'Published' : 'Not Published' }}</td>
            <td>{{ $player->pivot->rank ?? '—' }}</td>
            <td>Profile</td>
            <td>{{ $player->name }} {{ $player->surname }}</td>
            <td>{{ $player->email ?? '—' }}</td>
            <td>{{ $player->cellNr ?? '—' }}</td>
            <td>{{ $player->pivot->pay_status ? 'Paid' : 'Not Paid' }}</td>
          </tr>
        @endforeach

        {{-- ✅ No-Profile Players --}}
        @foreach($team->team_players_no_profile as $np)
          <tr>
            <td>{{ $region->region_name }}</td>
            <td>{{ $team->name }}</td>
            <td>{{ $team->published ? 'Published' : 'Not Published' }}</td>
            <td>{{ $np->rank ?? '—' }}</td>
            <td>No-Profile</td>
            <td>{{ $np->name }} {{ $np->surname }}</td>
            <td>{{ $np->email ?? '—' }}</td>
            <td>{{ $np->cellNr ?? '—' }}</td>
            <td>{{ $np->pay_status ? 'Paid' : 'Not Paid' }}</td>
          </tr>
        @endforeach
      @endforeach
    @endforeach
  </tbody>
</table>
