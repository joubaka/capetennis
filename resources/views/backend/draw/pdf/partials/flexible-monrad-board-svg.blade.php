<svg xmlns="http://www.w3.org/2000/svg" width="{{ $layout['width'] }}" height="{{ $layout['height'] }}" viewBox="0 0 {{ $layout['width'] }} {{ $layout['height'] }}" preserveAspectRatio="xMinYMin meet">
  <rect width="100%" height="100%" fill="#ffffff"/>
  @foreach($layout['boards'] as $board)
    <text x="0" y="{{ $board['sectionTop'] + 18 }}" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="15" font-weight="700">{{ $board['section'] }}</text>
    @foreach($board['roundHeadings'] as $heading)
      <text x="{{ $heading['x'] }}" y="{{ $heading['y'] }}" fill="#405064" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" font-weight="700">{{ strtoupper($heading['label']) }}</text>
    @endforeach
    @foreach($board['connections'] as $line)
      @php($mid = ($line['x1'] + $line['x2']) / 2)
      <path d="M {{ $line['x1'] }} {{ $line['y1'] }} H {{ $mid }} V {{ $line['y2'] }} H {{ $line['x2'] }}" fill="none" stroke="#526172" stroke-width="1.2"/>
    @endforeach
    @foreach($board['cards'] as $card)
      <rect x="{{ $card['x'] }}" y="{{ $card['y'] }}" width="{{ $card['width'] }}" height="{{ $card['height'] }}" rx="2" fill="#ffffff" stroke="#526172" stroke-width="1"/>
      <line x1="{{ $card['x'] }}" y1="{{ $card['y'] + 30 }}" x2="{{ $card['x'] + $card['width'] }}" y2="{{ $card['y'] + 30 }}" stroke="#c9d2dc" stroke-width="0.7"/>
      <text x="{{ $card['x'] + $card['width'] - 5 }}" y="{{ $card['y'] + 9 }}" text-anchor="end" fill="#237f69" font-family="DejaVu Sans, Arial, sans-serif" font-size="8" font-weight="700">M{{ $card['number'] }}</text>
      @php($firstWinner = ($card['player_ids'][0] ?? null) && ($card['player_ids'][0] == $card['winner']))
      @php($secondWinner = ($card['player_ids'][1] ?? null) && ($card['player_ids'][1] == $card['winner']))
      <text x="{{ $card['x'] + 7 }}" y="{{ $card['y'] + 21 }}" fill="{{ $firstWinner ? '#155f50' : '#172033' }}" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="{{ $firstWinner ? '700' : '400' }}">{{ $card['players'][0] }}</text>
      <text x="{{ $card['x'] + $card['width'] - 7 }}" y="{{ $card['y'] + 21 }}" text-anchor="end" fill="#155f50" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700">{{ $card['scores'][0] }}</text>
      <text x="{{ $card['x'] + 7 }}" y="{{ $card['y'] + $card['height'] - 9 }}" fill="{{ $secondWinner ? '#155f50' : '#172033' }}" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="{{ $secondWinner ? '700' : '400' }}">{{ $card['players'][1] }}</text>
      <text x="{{ $card['x'] + $card['width'] - 7 }}" y="{{ $card['y'] + $card['height'] - 9 }}" text-anchor="end" fill="#155f50" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700">{{ $card['scores'][1] }}</text>
      @if($card['note'])
        <text x="{{ $card['x'] + 7 }}" y="{{ $card['y'] + ($card['height'] / 2) + 3 }}" fill="#657184" font-family="DejaVu Sans, Arial, sans-serif" font-size="7">{{ $card['note'] }}</text>
      @endif
    @endforeach
    @foreach($board['endpoints'] as $endpoint)
      <text x="{{ $endpoint['x'] }}" y="{{ $endpoint['y'] }}" fill="#657184" font-family="DejaVu Sans, Arial, sans-serif" font-size="7" font-weight="700">{{ strtoupper($endpoint['label']) }}</text>
      <text x="{{ $endpoint['x'] }}" y="{{ $endpoint['y'] + 13 }}" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700">{{ $endpoint['name'] }}</text>
    @endforeach
  @endforeach
  @if($layout['positions'])
    <text x="0" y="{{ $layout['positions_y'] }}" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="12" font-weight="700">Final positions</text>
    @foreach($layout['positions'] as $index => $position)
      @php($x = ($index % 8) * 125)
      @php($y = $layout['positions_y'] + 12 + intdiv($index, 8) * 34)
      <rect x="{{ $x }}" y="{{ $y }}" width="118" height="27" rx="2" fill="#f7faf8" stroke="#b7cbc0" stroke-width="0.8"/>
      <text x="{{ $x + 6 }}" y="{{ $y + 11 }}" fill="#155f50" font-family="DejaVu Sans, Arial, sans-serif" font-size="8" font-weight="700">{{ $position['position'] }}</text>
      <text x="{{ $x + 22 }}" y="{{ $y + 18 }}" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="8">{{ \Illuminate\Support\Str::limit($position['name'], 19) }}</text>
    @endforeach
  @endif
</svg>
