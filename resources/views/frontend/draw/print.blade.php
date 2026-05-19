@php
use App\Services\Draw\Brackets;

$bracketIdsWithFixtures = $draw->drawFixtures()
  ->distinct()
  ->pluck('bracket_id')
  ->map(fn ($id) => (int) $id)
  ->all();

$hasBracket = fn (int $bracketId) => in_array($bracketId, $bracketIdsWithFixtures, true);
$hasAnyFixtures = !empty($bracketIdsWithFixtures);
@endphp

<style>
  .draw-print-root { padding: 1rem; background: #fff; color: #111; }
  .draw-print-toolbar { margin-bottom: 1rem; }
  .draw-print-header { margin-bottom: 1rem; border-bottom: 1px solid #ddd; padding-bottom: .75rem; }
  .draw-print-header h2 { margin: 0 0 .25rem; font-size: 1.4rem; }
  .draw-print-meta { font-size: .9rem; color: #555; }
  .draw-print-note { font-size: .9rem; font-style: italic; margin-top: .35rem; }
  .draw-print-support { font-size: .85rem; color: #555; margin-top: .2rem; }
  .draw-print-section { margin-bottom: 1.25rem; }
  .draw-print-root svg { max-width: 100%; height: auto; display: block; }

  @media print {
    @page { size: A3 landscape; margin: 8mm; }
    .no-print { display: none !important; }
    .draw-print-root { padding: 0; }
    .draw-print-section { break-inside: avoid; page-break-inside: avoid; break-after: page; page-break-after: always; }
    .draw-print-section:last-of-type { break-after: auto; page-break-after: auto; }
  }
</style>

<div class="draw-print-root">
  <div class="draw-print-toolbar no-print">
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print Draw</button>
  </div>

  <div class="draw-print-header">
    <h2>{{ $draw->drawName ?? 'Draw' }}</h2>
    <div class="draw-print-meta">
      Event: {{ optional($draw->event)->name ?? 'N/A' }} · Printed: {{ now()->format('d M Y H:i') }}
    </div>
    <div class="draw-print-note">Designed for offline tournament progression: print and record winners/scores directly on this sheet.</div>
    <div class="draw-print-support">Support: support@capetennis.co.za</div>
  </div>

  @if(!$hasAnyFixtures)
    <div class="alert alert-warning mb-0">No fixtures found for this draw yet.</div>
  @else
    @if($hasBracket(1))
      <section class="draw-print-section">
        @php Brackets::get_bracket_plat($draw); @endphp
      </section>
    @endif

    @if($hasBracket(2))
      <section class="draw-print-section">
        @php Brackets::get_bracket_3_4($draw); @endphp
      </section>
    @endif

    @if($hasBracket(3))
      <section class="draw-print-section">
        @php Brackets::get_bracket_gold($draw, 3); @endphp
      </section>
    @endif

    @if($hasBracket(4))
      <section class="draw-print-section">
        @php Brackets::get_bracket_7_8($draw, 4); @endphp
      </section>
    @endif

    @if($hasBracket(5))
      <section class="draw-print-section">
        @php Brackets::get_bracket_9_12($draw, 5); @endphp
      </section>
    @endif

    @if($hasBracket(6))
      <section class="draw-print-section">
        @php Brackets::get_bracket_13_16($draw, 6); @endphp
      </section>
    @endif

    @if($hasBracket(7))
      <section class="draw-print-section">
        @php Brackets::get_bracket_17_24($draw, 7); @endphp
      </section>
    @endif

    @if($hasBracket(8))
      <section class="draw-print-section">
        @php Brackets::get_bracket_25_32($draw, 8); @endphp
      </section>
    @endif
  @endif
</div>
