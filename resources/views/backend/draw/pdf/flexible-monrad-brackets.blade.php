<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $event->name }} - Flexible Monrad Brackets</title>
  <style>
    @page { size: A4 landscape; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; }
    .draw-page { page-break-after: always; }
    .draw-page:last-child { page-break-after: auto; }
    .header { display: table; width: 100%; margin-bottom: 2mm; border-bottom: 1.5pt solid #163a64; }
    .header > div { display: table-cell; vertical-align: bottom; padding-bottom: 2mm; }
    .meta { color: #657184; font-size: 8pt; text-align: right; }
    .kicker { margin: 0 0 1mm; color: #237f69; font-size: 7pt; font-weight: 700; letter-spacing: 1.2pt; text-transform: uppercase; }
    h1 { margin: 0; color: #163a64; font-size: 16pt; }
    .board { display: block; width: 100%; height: 166mm; object-fit: contain; object-position: left top; }
  </style>
</head>
<body>
@foreach($draws as $draw)
  <section class="draw-page">
    <header class="header">
      <div><p class="kicker">Flexible Monrad bracket</p><h1>{{ $draw['name'] }}</h1></div>
      <div class="meta">{{ $event->name }}<br>All brackets and final positions</div>
    </header>
    <img class="board" src="data:image/svg+xml;base64,{{ base64_encode($draw['svg']) }}" alt="{{ $draw['name'] }} Flexible Monrad bracket">
  </section>
@endforeach
</body>
</html>
