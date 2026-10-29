<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Order of play</title>
<style>
@page { size: A4 landscape; margin: 12mm; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #172e45; }
h1 { font-size: 18pt; margin-bottom: 3mm; } p { margin: 2mm 0; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 5mm; }
th, td { border: .6pt solid #9aa7b3; padding: 2.5mm; overflow-wrap: anywhere; text-align: left; }
th { background: #edf3f6; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
.time { width: 18%; } .court { width: 7%; } .draw { width: 19%; } .result { width: 17%; }
</style></head><body>
<h1>{{ $event->name }} — Order of play</h1>
<p><strong>{{ $venue->name }}</strong> · {{ $scope['date'] ?? 'All saved days' }} · {{ count($rows) }} matches</p>
<p>{{ $scope['source'] === 'working' ? 'Working schedule — private staff copy' : 'Published schedule' }}</p>
<table><thead><tr><th class="time">Date / time</th><th class="court">Court</th><th class="draw">Draw / match</th><th>Players / qualification</th><th class="result">Result</th></tr></thead><tbody>
@foreach($rows as $row)<tr><td>{{ \Carbon\Carbon::parse($row['time'])->format('D j M Y H:i') }}</td><td>{{ $row['court'] }}</td><td>{{ $row['draw'] }}<br>Match {{ $row['match'] }}</td><td>{{ implode(' / ', $row['players']) }}</td><td class="result">{{ $row['result'] }}</td></tr>@endforeach
</tbody></table></body></html>
