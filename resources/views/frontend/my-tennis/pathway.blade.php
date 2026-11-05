<section id="player-development-pathway" class="ctr-pathway mt-3" aria-labelledby="ctr-pathway-heading" data-player-pathway>
  <style>
    .ctr-pathway{border:1px solid #dbe4ef;border-radius:14px;padding:1rem;background:#f7f9fc;overflow-wrap:anywhere;scroll-margin-top:6rem}
    .ctr-pathway .pathway-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:1.25rem;margin-top:1rem}
    .ctr-pathway .pathway-action{grid-column:1/-1;border-top:1px solid #dbe4ef;padding-top:1rem}
    .ctr-pathway .pathway-map{list-style:none;padding:0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1.1rem;margin:1.25rem 0}
    .ctr-pathway .pathway-map > li{position:relative;border:1px solid #dbe4ef;border-top:4px solid var(--stage-ink);border-radius:14px;padding:1rem .8rem;background:linear-gradient(150deg,#fff,#f1f5fa);min-width:0;box-shadow:0 5px 14px #172e4510}
    .ctr-pathway .pathway-step-number{display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:50%;background:var(--stage-ink);color:#fff;font-weight:700;margin-bottom:.75rem}
    .ctr-pathway .pathway-map [aria-current="step"]{background:#fff0e1;box-shadow:0 6px 20px #172e4520}
    .ctr-pathway .pathway-map > li + li::before{content:'→';position:absolute;left:-1rem;top:1rem;color:#52677c}
    .ctr-pathway .pathway-map [aria-current="step"]{outline:2px solid #233d50;outline-offset:2px}
    .ctr-pathway .pathway-map small{display:block;margin-top:.35rem}
    .ctr-pathway .pathway-score{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
    .ctr-pathway .pathway-summary{display:grid;grid-template-columns:minmax(0,1fr);gap:.75rem;margin-top:1rem}
    .ctr-pathway .pathway-next-step,.ctr-pathway .pathway-current-level{padding:1rem;background:#fff;border:1px solid #dbe4ef;border-radius:10px}
    .ctr-pathway .pathway-next-step{border-inline-start:3px solid #004177}
    @media(min-width:768px){.ctr-pathway .pathway-summary{grid-template-columns:minmax(0,3fr) minmax(0,2fr);align-items:start}}
    .ctr-pathway .pathway-list{padding-left:1.2rem;margin:0}.ctr-pathway .pathway-list li{margin-bottom:.65rem}
    .ctr-pathway .pathway-met{font-weight:700;color:#17684b}
    .ctr-pathway .pathway-stages{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;margin-top:1rem}
    .ctr-pathway .pathway-stage{padding:.75rem;border:1px solid #dbe4ef;border-top:3px solid var(--stage-ink);border-radius:8px;background:white}
    .ctr-pathway .pathway-admin{border-top:1px solid #dbe4ef;padding-top:.75rem;margin-top:1rem}
    .ctr-pathway summary{cursor:pointer;min-height:44px;align-content:center}
    .ctr-pathway .pathway-admin form{display:flex;flex-wrap:wrap;gap:.75rem;align-items:end;margin-top:.75rem}
    .ctr-pathway .pathway-admin label{font-size:.8rem}.ctr-pathway .pathway-admin select,.ctr-pathway button,.ctr-pathway .pathway-browse{min-height:44px}
    .ctr-pathway [data-pathway-status]{white-space:pre-line}
    @media(max-width:991.98px){.ctr-pathway .pathway-grid{grid-template-columns:1fr}.ctr-pathway .pathway-admin form>div{width:100%}.ctr-pathway .pathway-map{grid-template-columns:1fr}.ctr-pathway .pathway-map > li + li::before{content:'↓';left:50%;top:-1.25rem}}
    @media(max-width:575.98px){.ctr-pathway .pathway-stages,.ctr-pathway .pathway-map{grid-template-columns:1fr}.ctr-pathway .pathway-map > li + li::before{content:'↓';left:50%;top:-1.25rem}}
  </style>
  <div class="ctr-heading"><h2 class="h5 mb-0" id="ctr-pathway-heading">Tournament options</h2></div>
  <div class="pathway-summary">
    <div class="pathway-next-step">
      <h3 class="h6 mb-1">Suggested next step</h3>
      @if($pathway['current_stage'] === 'standard' && !$pathway['suggestedCompetition'])
        <p class="small mb-1" role="status"><strong>Competition recommendation uncertain</strong></p>
        @php($standardRating = $pathway['metrics']['stage_ratings']['standard'] ?? [])
        <p class="small text-muted mb-2">
          @if($standardRating['local'] ?? false)
            We only know how you've played against a small group. We need more results to suggest which tournaments would suit you.
          @elseif(!($standardRating['comparable'] ?? false))
            We don't have enough recent results to tell which tournaments would suit you.
          @elseif(!$pathway['assessment_available'])
            The saved rating needs an update before we can suggest a competition level.
          @else
            A competition benchmark is unavailable.
          @endif
        </p>
      @else
        <p class="small text-muted mb-2" role="status">{{ $pathway['assessment_status'] }}</p>
      @endif
      <ul class="small ps-3 mb-3">
        @foreach($pathway['next_steps'] as $step)
          <li>{{ $step }}</li>
        @endforeach
      </ul>
      <a class="btn btn-sm btn-outline-primary pathway-browse" href="{{ route('home') }}">Browse events</a>
    </div>
    <div class="pathway-current-level">
      <h3 class="h6">Ball level: {{ $pathway['current_stage'] === 'standard' ? 'Green/yellow ball' : $pathway['current_stage_label'] }}</h3>
      <div class="pathway-score" data-pathway-top-badge><x-ctr-badge :stage="$pathway['badge']['stage']" :label="$pathway['badge']['stage'] === 'standard' ? 'Green/yellow ball' : null" :score="$pathway['badge']['score']" :local="$pathway['badge']['local']" /></div>
      <p class="small text-muted mt-2 mb-0">@if($pathway['metrics']['date'] ?? null) Latest saved ball-level rating · {{ $pathway['metrics']['date'] }} @else No saved ball-level rating available. @endif</p>
      @if($pathway['metrics']['stale'] ?? false)<p class="small text-muted mt-2 mb-0">@if($pathway['assessment_available'] ?? false) Assessment uses the latest saved rating shown above while the nightly update is due. @else Saved rating awaiting an update; progress is not assessed. @endif</p>@endif
    </div>
  </div>
  <section class="mt-3" aria-labelledby="pathway-levels-heading">
    <h3 class="h6" id="pathway-levels-heading">All levels and targets</h3>
    <p class="small text-muted mb-0">{{ $pathway['reason'] }}</p>
    <ol class="pathway-map" aria-label="Development pathway snapshot" data-pathway-snapshot>
    @foreach(['red' => ['Red ball', '#ed2029', 0], 'orange' => ['Orange ball', '#ed7100', 1]] as $ball => [$label, $ink, $benchmarkIndex])
      <li style="--stage-ink:{{ $ink }}" @if($pathway['current_stage'] === $ball) aria-current="step" @endif>
        <span class="pathway-step-number" aria-hidden="true">{{ $loop->iteration }}</span>
        <strong>{{ $label }}</strong>
        @if($pathway['current_stage'] === $ball)<small class="fw-semibold">You are here · latest badge</small>@endif
        <ul class="pathway-events small ps-3 mt-2 mb-2">
        @foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$ball] ?? [] as $eventOption)<li>@if(str_contains($eventOption, 'TSA'))<a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">{{ $eventOption }} <span class="visually-hidden">(opens in a new tab)</span></a>@else{{ $eventOption }}@endif</li>@endforeach
        </ul>
          <small>@if($pathway['benchmarks'][$benchmarkIndex]['target']) CTR {{ number_format($pathway['benchmarks'][$benchmarkIndex]['target']['ctr'], 0) }} + {{ $pathway['benchmarks'][$benchmarkIndex]['target']['events'] }} {{ $label }} events @else benchmark awaiting configuration @endif</small>
        @include('frontend.my-tennis.pathway-info-button', ['infoStage' => $ball, 'infoLabel' => $label])
      </li>
    @endforeach
    @foreach(['Wilson B' => ['#12765a', 3], 'Wilson A' => ['#145c8d', 4], 'TSA tournaments' => ['#6654a3', 5]] as $competition => [$ink, $step])
      <li style="--stage-ink:{{ $ink }}" data-pathway-competition="{{ $competition }}" @if(($pathway['currentCompetition'] ?? null) === $competition) aria-current="step" @endif>
        <span class="pathway-step-number" aria-hidden="true">{{ $step }}</span>
        <strong>{{ $competition === 'TSA tournaments' ? 'TSA' : $competition }}</strong>
        @if(($pathway['currentCompetition'] ?? null) === $competition)<small class="fw-semibold">You are here · latest saved badge</small>@endif
        @if($competition === 'Wilson B')
          <small>After Orange</small><small>@if($pathway['wilsonCutoff'] !== null) CTR {{ number_format($pathway['wilsonCutoff'], 0) }} or below @else Recommendation awaiting configuration @endif</small>
        @elseif($competition === 'Wilson A')
          <small>@if($pathway['wilsonCutoff'] !== null) CTR above {{ number_format($pathway['wilsonCutoff'], 0) }}@if($pathway['tsaCutoff'] !== null) through {{ number_format($pathway['tsaCutoff'], 0) }}@endif @else Recommendation awaiting configuration @endif</small>
        @else
          <small>@if($pathway['tsaCutoff'] !== null) CTR above {{ number_format($pathway['tsaCutoff'], 0) }} @else Recommendation awaiting configuration @endif</small>
          <a class="small d-inline-flex align-items-center" style="min-height:44px" href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
        @endif
        @include('frontend.my-tennis.pathway-info-button', ['infoStage' => ['Wilson B' => 'wilson-b', 'Wilson A' => 'wilson-a', 'TSA tournaments' => 'tsa'][$competition], 'infoLabel' => $competition])
      </li>
    @endforeach
    </ol>
  </section>
</section>
