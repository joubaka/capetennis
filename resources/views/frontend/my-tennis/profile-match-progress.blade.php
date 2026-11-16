@php
  $progressRows = array_reverse(array_slice($profileMatchProgress['rows'] ?? [], 0, 5));
  $progressReason = $profileMatchProgress['reason'] ?? null;
  $progressUrl = route('my.tennis.rating', $selectedPlayer).'#ctr-match-progress-heading';
  $hasPair = static fn (array $row): bool => isset($row['before_score'], $row['after_score'], $row['delta'])
      && is_numeric($row['before_score']) && is_numeric($row['after_score']) && is_numeric($row['delta'])
      && is_finite((float) $row['before_score']) && is_finite((float) $row['after_score']) && is_finite((float) $row['delta'])
      && $row['before_score'] >= 0 && $row['before_score'] <= 100 && $row['after_score'] >= 0 && $row['after_score'] <= 100
      && ($row['reason'] ?? null) === null && abs(round((float) $row['after_score'] - (float) $row['before_score'], 1) - (float) $row['delta']) < .00001;
  $timeLabel = static fn (array $row): ?string => empty($row['time']) ? null : \Carbon\CarbonImmutable::parse($row['time'], 'Africa/Johannesburg')->format('H:i');
  $hasProgressValues = collect($progressRows)->contains($hasPair);
@endphp
<section class="profile-match-progress" aria-labelledby="profile-match-progress-heading" data-profile-match-progress>
  <style>
    .profile-match-progress{color:#172e45;border:1px solid #dbe4ef;border-top:4px solid #ff9f43;background:#fff;border-radius:16px;margin:0 0 1.25rem;overflow-wrap:anywhere;min-width:0;box-shadow:0 6px 20px #142d5009}
    .profile-match-progress *{min-width:0}.profile-match-progress .pm-heading{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem 1.5rem;padding:clamp(1rem,2vw,1.5rem);background:#142d50;border-radius:12px 12px 0 0;color:#fff}
    .profile-match-progress .pm-eyebrow{color:#ffc182;font-size:.7rem;letter-spacing:.09em;text-transform:uppercase;font-weight:700;display:block;margin-bottom:.4rem}
    .profile-match-progress .pm-heading h2{color:#fff;font-size:clamp(1.2rem,2.7vw,1.55rem);font-weight:700;margin:0}
    .profile-match-progress .pm-heading a{color:#fff;display:inline-flex;align-items:center;gap:.4rem;min-height:44px;font-size:.82rem;text-underline-offset:4px;font-weight:600}
    .profile-match-progress .pm-body{padding:clamp(1rem,2vw,1.5rem)}
    .profile-match-progress .pm-basis{margin:0 0 .35rem;font-size:.82rem;color:#233d58}
    .profile-match-progress .pm-note{color:#52677b;font-size:.78rem;line-height:1.6;margin:0 0 .6rem}
    .profile-match-progress .pm-scroll{max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;border:1px solid #e6edf4;border-radius:12px;background:#fbfcfe}
    .profile-match-progress .pm-scroll svg{width:100%;min-width:640px;max-width:65rem;display:block;margin-inline:auto}
    .profile-match-progress .pm-caption{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.3rem 1rem;margin:.5rem 0 0;font-size:.75rem;color:#52677b}
    .profile-match-progress .pm-values{border-top:1px solid #e4ebf3;margin-top:.85rem}
    .profile-match-progress .pm-values summary{cursor:pointer;min-height:44px;align-content:center;font-size:.82rem;font-weight:600;color:#004177}
    .profile-match-progress .pm-value-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.4rem 1rem;padding:.8rem 0;border-top:1px solid #e4ebf3}
    .profile-match-progress .pm-value-row h3{font-size:.85rem;margin:0 0 .25rem;color:#142d50}
    .profile-match-progress .pm-value-row p{margin:0;font-size:.76rem;color:#52677b}
    .profile-match-progress .pm-numbers{display:flex;flex-wrap:wrap;align-items:center;gap:.35rem 1rem;margin:0;font-variant-numeric:tabular-nums}
    .profile-match-progress .pm-numbers dt{font-size:.68rem;font-weight:500;color:#52677b;margin-bottom:.15rem}.profile-match-progress .pm-numbers dd{font-size:1rem;font-weight:700;color:#145c8d;margin:0}
    .profile-match-progress .pm-numbers .pm-decrease{color:#9e3b10}.profile-match-progress .pm-unavailable{align-self:center;max-width:26rem;font-size:.76rem;color:#52677b}
    .profile-match-progress .pm-scroll:focus-visible,.profile-match-progress a:focus-visible,.profile-match-progress summary:focus-visible{outline:3px solid #b85b00;outline-offset:3px}
    .profile-match-progress .pm-scroll-cue{display:none}.profile-match-progress .pm-pending{padding:1rem;border-radius:10px;background:#f1f5fa;color:#233d58;font-size:.85rem;line-height:1.6}
    @media(max-width:650px){.profile-match-progress .pm-scroll-cue{display:block;color:#52677b;font-size:.75rem;margin:.6rem 0}.profile-match-progress .pm-value-row{grid-template-columns:1fr}.profile-match-progress .pm-heading{align-items:start}.profile-match-progress .pm-numbers{gap:1.5rem}}
  </style>
  <div class="pm-heading">
    <div><span class="pm-eyebrow">Your last five matches</span><h2 id="profile-match-progress-heading">CTR before &amp; after each match</h2></div>
    <a data-my-tennis-link href="{{ $progressUrl }}">Explore match explanations <span aria-hidden="true">→</span></a>
  </div>
  <div class="pm-body">
    <p class="pm-basis"><strong>Reconstructed using scheduled match order.</strong></p>
    <p class="pm-note">CTR estimates, not archived ratings or verified completion times. Each pair uses strictly earlier scheduled results, then adds that match; other simultaneous results are excluded.@if($profileMatchProgress['as_of'] ?? null) Saved through {{ $profileMatchProgress['as_of'] }}.@endif</p>
    @if($progressReason !== null)
      <p class="pm-pending mb-0" role="status" data-profile-match-pending>{{ $progressReason }}</p>
    @elseif(!$progressRows)
      <p class="pm-pending mb-0" role="status" data-profile-match-pending>No eligible included matches with available details yet. Saved match reconstructions will appear after an overnight update.</p>
    @else
      @if($hasProgressValues)
      <p class="pm-scroll-cue">Swipe or scroll across to see every available match. Exact values are also listed below.</p>
      <figure class="mb-0">
        <div class="pm-scroll" tabindex="0" role="region" aria-label="Reconstructed match ratings; scroll horizontally on a narrow screen" data-profile-match-chart>
          <svg viewBox="0 0 660 352" role="group" aria-labelledby="profile-match-chart-title" aria-describedby="profile-match-chart-description">
            <title id="profile-match-chart-title">Latest five eligible matches, oldest to newest, on a fixed 0 to 100 CTR scale</title>
            <desc id="profile-match-chart-description">Each separate arrow compares reconstructed CTR immediately before and after that match. Gaps are deliberate: no continuous rating trend between matches is implied. Exact values, results and unavailable reasons are in the match values list below.</desc>
            <defs><marker id="profile-match-pair-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="context-stroke" /></marker></defs>
            @foreach([100, 75, 50, 25, 0] as $tick)
              @php $tickY = 190 - 1.6 * $tick; @endphp
              <line x1="43" y1="{{ $tickY }}" x2="640" y2="{{ $tickY }}" stroke="#e0e8f1" />
              <text x="33" y="{{ $tickY + 4 }}" text-anchor="end" font-size="12" fill="#52677b">{{ $tick }}</text>
            @endforeach
            @foreach($progressRows as $index => $row)
              @php $beforeX = 70 + $index * 116; @endphp
              @php $afterX = $beforeX + 50; @endphp
              @if($hasPair($row))
                @php
                  $beforeY = 190 - 1.6 * $row['before_score'];
                  $afterY = 190 - 1.6 * $row['after_score'];
                  $ink = $row['delta'] < 0 ? '#b44914' : '#145c8d';
                @endphp
                <g data-profile-match-pair data-before="{{ $row['before_score'] }}" data-after="{{ $row['after_score'] }}" data-delta="{{ $row['delta'] }}" role="img" aria-label="{{ $row['date'] }} vs {{ $row['opponent'] }}: {{ $row['won'] ? 'Won' : 'Lost' }} {{ $row['score'] }}. Reconstructed before {{ number_format($row['before_score'], 1) }}, after {{ number_format($row['after_score'], 1) }}, change {{ sprintf('%+.1f', $row['delta']) }} CTR.">
                  <line x1="{{ $beforeX }}" y1="{{ $beforeY }}" x2="{{ $afterX }}" y2="{{ $afterY }}" stroke="{{ $ink }}" stroke-width="3.5" marker-end="url(#profile-match-pair-arrow)" />
                  <circle cx="{{ $beforeX }}" cy="{{ $beforeY }}" r="4" fill="{{ $ink }}"/><circle cx="{{ $afterX }}" cy="{{ $afterY }}" r="4" fill="{{ $ink }}"/>
                  <text x="{{ $beforeX }}" y="{{ max(17, $beforeY - 11) }}" text-anchor="middle" font-size="12" font-weight="600" fill="#142d50">{{ number_format($row['before_score'], 1) }}</text>
                  <text x="{{ $afterX }}" y="{{ max(17, $afterY - 11) }}" text-anchor="middle" font-size="12" font-weight="600" fill="#142d50">{{ number_format($row['after_score'], 1) }}</text>
                  <text x="{{ $beforeX }}" y="215" text-anchor="middle" font-size="11" fill="#52677b">Before</text><text x="{{ $afterX }}" y="215" text-anchor="middle" font-size="11" fill="#52677b">After</text>
                  <rect x="{{ $beforeX - 17 }}" y="231" width="84" height="26" rx="12" fill="{{ $row['delta'] < 0 ? '#fff0e5' : '#eaf2fb' }}"/>
                  <text x="{{ $beforeX + 25 }}" y="249" text-anchor="middle" font-size="14" font-weight="700" fill="{{ $ink }}">{{ $row['delta'] > 0 ? '↑' : ($row['delta'] < 0 ? '↓' : '→') }} {{ sprintf('%+.1f', $row['delta']) }}</text>
                </g>
              @else
                <text x="{{ $beforeX + 25 }}" y="115" text-anchor="middle" font-size="12" fill="#52677b"><title>{{ $row['reason'] ?? 'Comparable before/after pair unavailable.' }}</title>Unavailable</text>
              @endif
              <text x="{{ $beforeX + 25 }}" y="281" text-anchor="middle" font-size="12" fill="#142d50"><title>{{ $row['opponent'] }}</title>{{ \Illuminate\Support\Str::limit($row['opponent'], 17) }}</text>
              <text x="{{ $beforeX + 25 }}" y="303" text-anchor="middle" font-size="12" fill="#52677b">{{ \Carbon\CarbonImmutable::parse($row['date'])->format('j M') }}{{ $timeLabel($row) ? ' · '.$timeLabel($row) : '' }}</text>
              <text x="{{ $beforeX + 25 }}" y="326" text-anchor="middle" font-size="12" fill="#142d50">{{ $row['won'] ? 'Won' : 'Lost' }} {{ \Illuminate\Support\Str::limit($row['score'], 16) }}</text>
            @endforeach
          </svg>
        </div>
        <figcaption class="pm-caption"><span>Oldest → newest · fixed 0–100 scale</span><span>Each arrow is one match’s before/after pair.</span></figcaption>
      </figure>
      @else
        <p class="pm-pending" role="status" data-profile-match-unavailable>Your match results are saved. Before/after CTR is unavailable for these matches; the reason for each is shown below.</p>
      @endif
      <details class="pm-values" @if(!$hasProgressValues) open @endif>
        <summary>{{ $hasProgressValues ? 'Exact values and match results' : 'Match results and availability' }}</summary>
        @foreach($progressRows as $row)
          <article class="pm-value-row" data-profile-match-row>
            <div><h3>{{ $row['opponent'] }}</h3><p>{{ $row['date'] }}{{ $timeLabel($row) ? ' · '.$timeLabel($row) : '' }} · {{ $row['won'] ? 'Won' : 'Lost' }} {{ $row['score'] }}</p></div>
            @if($hasPair($row))
              <dl class="pm-numbers"><div><dt>Before</dt><dd>{{ number_format($row['before_score'], 1) }}</dd></div><div><dt>After</dt><dd>{{ number_format($row['after_score'], 1) }}</dd></div><div><dt>Change</dt><dd @class(['pm-decrease' => $row['delta'] < 0])>{{ sprintf('%+.1f', $row['delta']) }} CTR</dd></div></dl>
            @else
              <p class="pm-unavailable">{{ $row['reason'] ?? 'Comparable before/after pair unavailable.' }}</p>
            @endif
            @if(($row['hidden_matches'] ?? 0) > 0)<p class="pm-note">{{ $row['hidden_matches'] }} evidence details are unavailable, restricted or beyond the display limit. See the full explanation for coverage.</p>@endif
          </article>
        @endforeach
      </details>
      @if(($profileMatchProgress['restricted'] ?? 0) > 0)<p class="pm-note mt-2">Some match details are restricted and are not shown here.</p>@endif
      <p class="pm-note mt-2 mb-0">Change is after minus before, not points awarded for a result. Different matches can use different comparison contexts; changes do not add up to a monthly movement.</p>
    @endif
  </div>
</section>
