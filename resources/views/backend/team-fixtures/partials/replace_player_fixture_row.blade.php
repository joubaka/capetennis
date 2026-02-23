<tr id="row-{{ $fx->id }}">
  <td>{{ $fx->id }}</td>
  <td>{{ optional($fx->draw)->drawName ?? '—' }}</td>
  <td>{{ $fx->round_nr ?? '—' }}</td>
  <td>{{ $fx->tie_nr ?? '—' }}</td>

  @php
    $homeNames = [];
    $awayNames = [];
    $homeRegionShort = $fx->region1Name?->short_name ?? null;
    $awayRegionShort = $fx->region2Name?->short_name ?? null;
  @endphp

  @foreach($fx->fixturePlayers as $fp)
    @if($fp->team1_id && $fp->player1)
      @php
        $n = $fp->player1->full_name ?? ($fp->player1->name ?? '');
        if ($homeRegionShort) $n .= " ({$homeRegionShort})";
        $homeNames[] = $n;
      @endphp
    @elseif($fp->team1_no_profile_id && $fp->noProfile1)
      @php
        $n = trim($fp->noProfile1->name . ' ' . $fp->noProfile1->surname);
        if ($homeRegionShort) $n .= " ({$homeRegionShort})";
        $homeNames[] = $n;
      @endphp
    @endif

    @if($fp->team2_id && $fp->player2)
      @php
        $n2 = $fp->player2->full_name ?? ($fp->player2->name ?? '');
        if ($awayRegionShort) $n2 .= " ({$awayRegionShort})";
        $awayNames[] = $n2;
      @endphp
    @elseif($fp->team2_no_profile_id && $fp->noProfile2)
      @php
        $n2 = trim($fp->noProfile2->name . ' ' . $fp->noProfile2->surname);
        if ($awayRegionShort) $n2 .= " ({$awayRegionShort})";
        $awayNames[] = $n2;
      @endphp
    @endif
  @endforeach

  @php
    $homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
    $awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';
  @endphp

  <td class="home-cell">{{ $homeLabel }}</td>
  <td class="away-cell">{{ $awayLabel }}</td>
  <td>{{ $fx->scheduled_at ? \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i') : '—' }}</td>
  <td>{{ optional($fx->venue)->name ?? '—' }}</td>
</tr>
