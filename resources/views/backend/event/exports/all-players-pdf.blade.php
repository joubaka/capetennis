<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $event->name }} – Players by Region</title>
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 12px;
      color: #000;
    }
    h2, h3, h4 {
      margin-bottom: 4px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 25px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 6px;
      text-align: left;
    }
    th {
      background-color: #f2f2f2;
    }
    .region-header {
      background-color: #0056b3;
      color: white;
      padding: 8px;
      font-size: 15px;
      margin-top: 25px;
    }
    .team-header {
      background-color: #f8f9fa;
      border: 1px solid #ddd;
      padding: 6px;
      font-weight: bold;
      margin-top: 10px;
    }
    .badge {
      display: inline-block;
      padding: 3px 6px;
      border-radius: 4px;
      font-size: 11px;
    }
    .bg-success { background: #d4edda; color: #155724; }
    .bg-danger { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>

  <h2>{{ $event->name }} — Players by Region</h2>
  <p><strong>Date:</strong> {{ now()->format('d M Y') }}</p>

  @foreach($event->regions as $region)
    <div class="region-header">{{ $region->region_name }}</div>

    @foreach($region->teams as $team)
      <div class="team-header">
        {{ $team->name }} 
        (Team ID: {{ $team->id }})
        @if($team->published)
          <span class="badge bg-success">Published</span>
        @else
          <span class="badge bg-danger">Not Published</span>
        @endif
      </div>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Player Name</th>
            <th>Email</th>
            <th>Cell</th>
            <th>Pay Status</th>
          </tr>
        </thead>
        <tbody>
          @php
            $profiles = $team->players()->withPivot('rank','pay_status')->orderBy('team_players.rank')->get();
            $noProfiles = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->get() : collect();
            $max = max($profiles->count(), $noProfiles->count());
          @endphp

          @for($i = 0; $i < $max; $i++)
            @php
              $profile = $profiles[$i] ?? null;
              $noProfile = $team->noProfile ? ($noProfiles[$i] ?? null) : null;
              $payStatus = $profile?->pivot?->pay_status ?? 0;
            @endphp
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>
                @if($profile)
                  {{ $profile->name }} {{ $profile->surname }}
                @elseif($noProfile)
                  {{ $noProfile->name }} {{ $noProfile->surname }}
                @else
                  —
                @endif
              </td>
              <td>{{ $profile?->email ?? $noProfile?->email ?? '—' }}</td>
              <td>{{ $profile?->cellNr ?? $noProfile?->cellNr ?? '—' }}</td>
              <td>
                <span class="badge {{ $payStatus ? 'bg-success' : 'bg-danger' }}">
                  {{ $payStatus ? 'Paid' : 'Not Paid' }}
                </span>
              </td>
            </tr>
          @endfor
        </tbody>
      </table>
    @endforeach
  @endforeach

</body>
</html>
