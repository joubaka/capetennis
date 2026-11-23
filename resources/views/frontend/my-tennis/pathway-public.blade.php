<section id="pathway" class="pathway-public" aria-labelledby="public-pathway-title" data-public-pathway>
  <p class="pathway-eyebrow">Cape Tennis · Opportunities for every stage</p>
  <h2 id="public-pathway-title">Find your place. Discover what comes next.</h2>
  <p>Explore the pathway with your player or coach. Published event information stays free; Player Plus adds your personal rating context, progress and matching tournament options where evidence is available.</p>
  @include('frontend.my-tennis.pathway-journey', ['journeyPublic' => true, 'publicPathwayTargets' => config('pathway.targets', [])])
  <aside class="pathway-demo" aria-label="Fictional Player Plus pathway example">
    <span class="pathway-eyebrow">Fictional example · no actual player data</span>
    <h3 class="h4 mt-2">Your game. Your next challenge.</h3>
    <p>A personal Plus pathway puts your saved ball-level rating in context, shows your current guidance stage when evidence supports it, and brings relevant published opportunities together.</p>
    @php
      $sampleLower = config('pathway.wilson_a_above');
      $sampleUpper = config('pathway.tsa_above');
      $sampleBandsValid = is_numeric($sampleLower) && is_numeric($sampleUpper) && is_finite((float) $sampleLower) && is_finite((float) $sampleUpper) && $sampleLower >= 0 && $sampleUpper <= 100 && $sampleUpper > $sampleLower;
    @endphp
    @if($sampleBandsValid)
      @php($sampleScore = ($sampleLower + $sampleUpper) / 2)
      <div class="pathway-sample-card"><span class="pathway-eyebrow">Example personal view</span><h4 class="h5">Wilson A · You are here</h4><p>Fictional Green/Yellow CTR: <strong>{{ number_format($sampleScore, 1) }}</strong></p>
        <div class="pathway-band" role="img" aria-label="Fictional Green/Yellow CTR {{ number_format($sampleScore, 1) }}, within the Wilson A guidance band."><span class="pathway-band-marker" style="left:{{ $sampleScore }}%">{{ number_format($sampleScore, 1) }} ↓</span><div class="pathway-band-track" aria-hidden="true"><span style="width:{{ $sampleLower }}%"></span><span style="width:{{ $sampleUpper - $sampleLower }}%"></span><span style="width:{{ 100 - $sampleUpper }}%"></span></div><div class="pathway-band-labels" aria-hidden="true"><span>Wilson B</span><span>Wilson A</span><span>TSA</span></div></div>
        <p class="small mb-0">Next step: explore Wilson A opportunities with your coach and check event requirements.</p></div>
    @else
      <p>Sample rating bands are unavailable while guidance configuration is reviewed.</p>
    @endif
    <p class="small">Sample only. Your view depends on your own comparable published evidence. No rating or event availability is guaranteed.</p>
    <a href="#ctr-offer-title">Explore Player Plus <span aria-hidden="true">→</span></a>
  </aside>
  <section id="pathway-events" aria-labelledby="public-pathway-events-title">
    <h3 class="h4" id="public-pathway-events-title">Explore published opportunities</h3>
    <nav class="pathway-filter" aria-label="Choose an event stage">
      @foreach(['red' => 'Red ball', 'orange' => 'Orange ball', 'Wilson B' => 'Wilson B', 'Wilson A' => 'Wilson A', 'TSA tournaments' => 'TSA'] as $stageKey => $stageLabel)
        @if($stageKey === 'TSA tournaments')
          <a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">TSA · external <span class="visually-hidden">(opens in a new tab)</span></a>
        @else
          <a href="{{ route('ctr.index', ['stage' => $stageKey]) }}#pathway-events" @if($publicPathwayStage === $stageKey) aria-current="page" @endif>{{ $stageLabel }}</a>
        @endif
      @endforeach
    </nav>
    <p class="small">{{ $publicPathwayStage }} opportunities · check age, division and entry requirements. This public view is not a personal eligibility assessment. TSA is an external circuit, not a Cape Tennis hosted classification.</p>
    <div class="pathway-public-events">
    @forelse($publicPathwayEvents['events'] as $publicEvent)
      <article class="pathway-public-event"><span class="badge bg-label-primary">{{ $publicPathwayStage }}</span><h4 class="h6 mt-2"><a href="{{ $publicEvent['url'] }}">{{ $publicEvent['name'] }}</a></h4><p class="small">{{ $publicEvent['date'] }} · {{ $publicEvent['venue'] }}</p><p class="small">{{ implode(', ', $publicEvent['categories']) }}</p><strong class="small">{{ $publicEvent['status'] }}</strong><p class="mt-2 mb-0"><a href="{{ $publicEvent['url'] }}">Check entry requirements →</a></p></article>
    @empty
      <p>No upcoming published events with confirmed matching stage details are listed here yet. <a href="{{ route('home') }}">Browse all events</a> or discuss other opportunities with your coach.</p>
    @endforelse
    </div>
    @if($publicPathwayEvents['limited'])<p class="small mt-2">This is a limited selection. <a href="{{ route('home') }}">Browse all events</a>.</p>@endif
    @if($publicPathwayStage === 'TSA tournaments')<p class="mt-3"><a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">Explore the TSA website <span class="visually-hidden">(opens in a new tab)</span></a></p>@endif
  </section>
</section>
<section id="pathway-coach-kit" class="pathway-public" aria-labelledby="pathway-coach-title" data-pathway-share>
  <img class="pathway-print-poster" src="{{ asset('assets/img/pathway-coach-poster.svg') }}" alt="Cape Tennis pathway court poster with the public guide QR code" width="1080" height="1350">
  <p class="pathway-eyebrow">For coaches, players and parents</p><h2 id="pathway-coach-title">Share a bigger picture of their tennis.</h2>
  <p>Start a conversation about their next tournament. Share this public guide with your academy or family group.</p>
  <div class="pathway-share-grid"><div>
    <label class="fw-semibold mb-2" for="pathway-share-message">Ready-to-share message</label>
    <textarea id="pathway-share-message" readonly>Not sure which tournament to enter next? Explore the Cape Tennis pathway, from Red and Orange ball through Wilson and TSA opportunities. See how it works, then explore Player Plus for your player's personal pathway. https://capetennis.co.za/ctr#pathway</textarea>
    <div class="pathway-share-actions"><button type="button" class="btn btn-primary" data-pathway-copy>Copy message</button><a class="btn btn-outline-primary" href="https://wa.me/?text={{ rawurlencode('Explore the Cape Tennis pathway with your player: https://capetennis.co.za/ctr#pathway — discover tournament opportunities and explore Player Plus for your personal view.') }}" target="_blank" rel="noopener noreferrer">Share on WhatsApp <span class="visually-hidden">(opens in a new tab)</span></a><button type="button" class="btn btn-outline-primary" data-pathway-print>Print court poster</button></div>
    <p class="small mt-2" role="status" data-pathway-share-status></p>
    <p class="small">Public guide: <a href="https://capetennis.co.za/ctr#pathway">capetennis.co.za/ctr#pathway</a>. Sharing does not expose any player’s private ratings or send a message automatically.</p>
  </div><div><img class="pathway-share-qr" src="{{ asset('assets/img/pathway-share-qr.svg') }}" alt="QR code opening the public Cape Tennis pathway guide" width="180" height="180" loading="lazy"><p><a href="{{ asset('assets/img/pathway-share-qr.svg') }}" download="cape-tennis-pathway-qr.svg">Download QR code</a></p><p><a href="{{ asset('assets/img/pathway-coach-poster.svg') }}" download="cape-tennis-pathway-poster.svg">Download coach poster</a></p></div></div>
</section>
