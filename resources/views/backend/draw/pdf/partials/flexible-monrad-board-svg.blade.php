<svg xmlns="http://www.w3.org/2000/svg" width="{{ $layout['width'] }}" height="{{ $layout['height'] }}" viewBox="0 0 {{ $layout['width'] }} {{ $layout['height'] }}" preserveAspectRatio="xMinYMin meet">
  <rect width="100%" height="100%" fill="#ffffff"/>
  @foreach($layout['boards'] as $board)
    <text x="0" y="{{ $board['sectionTop'] + 18 }}" fill="#172033" font-family="DejaVu Sans, Arial, sans-serif" font-size="15" font-weight="700">{{ $board['section'] }}</text>
    @foreach($board['roundHeadings'] as $heading)
      <text x="{{ $heading['x'] }}" y="{{ $heading['y'] }}" fill="#405064" font-family="DejaVu Sans, Arial, sans-serif" font-size="10" font-weight="700">{{ strtoupper($heading['label']) }}</text>
    @endforeach
    @foreach($board['connections'] as $line)
      @php($mid = ($line['x1'] + $line['x2']) / 2)
      <path data-ct-edge="" d="M {{ $line['x1'] }} {{ $line['y1'] }} H {{ $mid }} V {{ $line['y2'] }} H {{ $line['x2'] }}" fill="none" stroke="#111111" stroke-width="1"/>
    @endforeach
    @foreach($board['cards'] as $card)
      <path data-ct-edge="" d="M {{ $card['x'] }} {{ $card['top'] }} H {{ $card['x'] + $card['width'] }} V {{ $card['bottom'] }} H {{ $card['x'] }}" fill="none" stroke="#111111" stroke-width="1"/>
      @foreach($card['participants'] as $slot => $participant)
        @php($rowY = ($slot ? $card['bottom'] : $card['top']) - 28)
        @php($palette = match($participant['style']) {
          'winner' => ['fill' => '#f1f3f3', 'stroke' => '#c7cdd0', 'text' => '#111111'],
          'withdrawn' => ['fill' => '#fff0ee', 'stroke' => '#f1a7a1', 'text' => '#b42318'],
          'source' => ['fill' => '#fff4d6', 'stroke' => '#fff4d6', 'text' => '#765e2a'],
          default => ['fill' => '#eaf5fc', 'stroke' => '#b9d8ee', 'text' => '#155d91'],
        })
        <rect x="{{ $card['x'] + 6 }}" y="{{ $rowY + 3 }}" width="{{ $participant['width'] }}" height="22" rx="5" fill="{{ $palette['fill'] }}" stroke="{{ $palette['stroke'] }}" stroke-width="1"/>
        <text x="{{ $card['x'] + 12 }}" y="{{ $rowY + 18 }}" fill="{{ $palette['text'] }}" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700">{{ $participant['label'] }}</text>
        @if($card['scores'][$slot] !== '')
          <text x="{{ $card['x'] + $card['width'] - 7 }}" y="{{ $rowY + 18 }}" text-anchor="end" fill="#176448" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700">{{ $card['scores'][$slot] }}</text>
        @endif
      @endforeach
      <rect x="{{ $card['x'] + $card['width'] - 38 }}" y="{{ $card['middle'] - 25 }}" width="38" height="14" fill="#ffffff"/>
      <text x="{{ $card['x'] + $card['width'] - 5 }}" y="{{ $card['middle'] - 15 }}" text-anchor="end" fill="#64748b" font-family="DejaVu Sans, Arial, sans-serif" font-size="8">Match {{ $card['number'] }}</text>
      @if($card['schedule'])
        <rect x="{{ $card['x'] + 52 }}" y="{{ $card['middle'] - 10 }}" width="{{ $card['width'] - 58 }}" height="13" fill="#ffffff"/>
        <text x="{{ $card['x'] + $card['width'] - 5 }}" y="{{ $card['middle'] }}" text-anchor="end" fill="#176448" font-family="DejaVu Sans, Arial, sans-serif" font-size="7" font-weight="700">{{ $card['schedule'] }}</text>
      @endif
      @if($card['note'])
        <text x="{{ $card['x'] + 7 }}" y="{{ $card['bottom'] + 11 }}" fill="#64748b" font-family="DejaVu Sans, Arial, sans-serif" font-size="7">{{ $card['note'] }}</text>
      @endif
    @endforeach
    @foreach($board['endpoints'] as $endpoint)
      <rect x="{{ $endpoint['x'] - 4 }}" y="{{ $endpoint['y'] }}" width="200" height="32" fill="#ffffff"/>
      <text x="{{ $endpoint['x'] }}" y="{{ $endpoint['y'] + 11 }}" fill="#607968" font-family="DejaVu Sans, Arial, sans-serif" font-size="7" font-weight="700">{{ strtoupper($endpoint['label']) }}</text>
      <text x="{{ $endpoint['x'] }}" y="{{ $endpoint['y'] + 26 }}" fill="#111111" font-family="DejaVu Sans, Arial, sans-serif" font-size="9" font-weight="700">{{ $endpoint['name'] }}</text>
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
