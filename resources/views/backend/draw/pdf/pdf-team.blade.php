<style>
  body { font-family:DejaVu Sans,sans-serif; color:#172e45; font-size:9pt; }
  h1 { font-size:12pt; margin:0 0 2mm; }
  .sheet-subtitle { font-size:9pt; margin:0 0 3mm; }
  .team-fixture-sheet { width:100%; border-collapse:collapse; table-layout:fixed; font-size:9pt; }
  .team-fixture-sheet th, .team-fixture-sheet td { border:1px solid #d9dee3; padding:1.2mm 1.5mm; vertical-align:top; line-height:1.2; overflow-wrap:anywhere; }
  .team-fixture-sheet th { background:#eef3fa; text-align:left; }
  .team-fixture-sheet .sheet-round-heading th { background:#e5edf5; padding:1.5mm; }
  .sheet-team { font-weight:bold; }
  .sheet-time { white-space:nowrap; }
  .team-fixture-sheet thead { display:table-header-group; }
  .team-fixture-sheet tr { page-break-inside:avoid; break-inside:avoid; }
</style>
<h1>{{ $name }}</h1>
<p class="sheet-subtitle">{{ !empty($selectedDate) ? \Carbon\Carbon::parse($selectedDate)->format('l j M Y') : 'All days' }} · {{ $fixtures->count() }} matches</p>
@include('backend.draw.pdf.partials.team-fixture-sheet')
