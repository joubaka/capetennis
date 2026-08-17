@extends('layouts/layoutMaster')

@section('title', 'Rankings')

@section('page-style')
<style>
  .rankings-shell { max-width: 1280px; margin: 0 auto; }
  .rankings-hero {
    align-items: center;
    background: linear-gradient(135deg, #004177 0%, #07568f 68%, #7565ef 145%);
    border-radius: 1rem;
    color: #fff;
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 1fr) auto;
    overflow: hidden;
    padding: clamp(1.35rem, 3vw, 2.35rem);
    position: relative;
  }
  .rankings-hero::after {
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 50%;
    content: '';
    height: 13rem;
    position: absolute;
    right: -4.5rem;
    top: -7rem;
    width: 13rem;
  }
  .rankings-hero__content { max-width: 44rem; position: relative; z-index: 1; }
  .rankings-hero__eyebrow {
    color: rgba(255, 255, 255, .76);
    display: block;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .07em;
    margin-bottom: .65rem;
    text-transform: uppercase;
  }
  .rankings-hero p { color: rgba(255, 255, 255, .8); font-size: .95rem; }
  .rankings-hero__icon {
    align-items: center;
    background: rgba(255, 255, 255, .13);
    border: 1px solid rgba(255, 255, 255, .18);
    border-radius: 1rem;
    display: flex;
    flex: 0 0 auto;
    font-size: 2rem;
    height: 5rem;
    justify-content: center;
    position: relative;
    width: 5rem;
    z-index: 1;
  }
  .rankings-section-heading { gap: 1rem; }
  .rankings-count {
    background: rgba(115, 103, 240, .12);
    border-radius: 999px;
    color: #6658dd;
    font-size: .75rem;
    font-weight: 700;
    padding: .35rem .65rem;
    white-space: nowrap;
  }
  .ranking-card {
    background: var(--bs-card-bg, #fff);
    border: 1px solid rgba(75, 70, 92, .12);
    border-radius: .9rem;
    box-shadow: 0 .25rem 1.1rem rgba(75, 70, 92, .07);
    color: inherit;
    display: flex;
    flex-direction: column;
    min-height: 12.5rem;
    overflow: hidden;
    padding: 1.35rem;
    position: relative;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
  }
  .ranking-card::before {
    background: linear-gradient(90deg, #004177, #7367f0);
    content: '';
    height: .28rem;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
  }
  .ranking-card:hover {
    border-color: rgba(115, 103, 240, .42);
    box-shadow: 0 .65rem 1.65rem rgba(47, 43, 61, .12);
    color: inherit;
    transform: translateY(-3px);
  }
  .ranking-card:focus-visible { outline: 3px solid rgba(115, 103, 240, .35); outline-offset: 3px; }
  .ranking-card__top { align-items: center; display: flex; justify-content: space-between; margin-bottom: 1.35rem; }
  .ranking-card__icon {
    align-items: center;
    background: rgba(115, 103, 240, .12);
    border-radius: .7rem;
    color: #7367f0;
    display: flex;
    flex: 0 0 2.75rem;
    font-size: 1.2rem;
    height: 2.75rem;
    justify-content: center;
  }
  .ranking-card__year {
    background: #f4f4f7;
    border-radius: 999px;
    color: #6d6979;
    font-size: .75rem;
    font-weight: 700;
    padding: .3rem .6rem;
  }
  .ranking-card__name { color: var(--bs-heading-color); font-size: 1rem; line-height: 1.45; overflow-wrap: anywhere; }
  .ranking-card__action { align-items: center; color: #6658dd; display: flex; font-size: .82rem; font-weight: 700; gap: .35rem; margin-top: auto; padding-top: 1.25rem; }
  .ranking-card__action .ti { transition: transform .2s ease; }
  .ranking-card:hover .ranking-card__action .ti { transform: translateX(3px); }
  .rankings-empty {
    background: var(--bs-card-bg, #fff);
    border: 1px dashed rgba(75, 70, 92, .22);
    border-radius: .9rem;
    padding: clamp(2.5rem, 7vw, 4.5rem) 1.25rem;
    text-align: center;
  }
  .rankings-empty__icon {
    align-items: center;
    background: rgba(115, 103, 240, .1);
    border-radius: 50%;
    color: #7367f0;
    display: inline-flex;
    font-size: 1.6rem;
    height: 3.75rem;
    justify-content: center;
    margin-bottom: 1rem;
    width: 3.75rem;
  }

  @media (prefers-reduced-motion: reduce) {
    .ranking-card, .ranking-card__action .ti { transition: none; }
  }

  @media (max-width: 575.98px) {
    .rankings-hero { grid-template-columns: 1fr; }
    .rankings-hero h1 { font-size: 1.55rem; }
    .rankings-hero__icon { display: none; }
    .rankings-section-heading { align-items: flex-start !important; flex-direction: column; gap: .45rem; }
    .ranking-card { min-height: 10.75rem; padding: 1.15rem; }
  }
</style>
@endsection

@section('content')
<main class="rankings-shell">
  <section class="rankings-hero mb-4" aria-labelledby="rankings-title">
    <div class="rankings-hero__content">
      <span class="rankings-hero__eyebrow">Cape Tennis leaderboards</span>
      <h1 class="text-white mb-2" id="rankings-title">Published series rankings</h1>
      <p class="mb-0">Choose a series to view player positions, total points and the event scores that count towards each ranking.</p>
    </div>
    <span class="rankings-hero__icon" aria-hidden="true"><i class="ti ti-trophy"></i></span>
  </section>

  <section aria-labelledby="published-series-heading">
    <div class="rankings-section-heading d-flex align-items-center justify-content-between mb-3 px-1">
      <div>
        <h2 class="h5 mb-1" id="published-series-heading">Select a series</h2>
        <p class="text-muted small mb-0">Only currently published leaderboards are listed.</p>
      </div>
      @if($series->isNotEmpty())
        <span class="rankings-count">{{ $series->count() }} {{ Str::plural('series', $series->count()) }}</span>
      @endif
    </div>

    @if($series->isEmpty())
      <div class="rankings-empty">
        <span class="rankings-empty__icon" aria-hidden="true"><i class="ti ti-trophy-off"></i></span>
        <h3 class="h5 mb-2">No rankings published yet</h3>
        <p class="text-muted mb-0">Published series leaderboards will appear here when they are ready.</p>
      </div>
    @else
      <div class="row g-3 g-lg-4">
        @foreach($series as $s)
          <div class="col-sm-6 col-xl-4">
            <a href="{{ route('frontend.ranking.show', $s->id) }}"
               class="ranking-card text-decoration-none h-100"
               aria-label="View {{ $s->name }} rankings">
              <div class="ranking-card__top">
                <span class="ranking-card__icon" aria-hidden="true"><i class="ti ti-list-numbers"></i></span>
                @if($s->year && !str_contains($s->name, (string) $s->year))
                  <span class="ranking-card__year">{{ $s->year }}</span>
                @endif
              </div>
              <h3 class="ranking-card__name h6 mb-0">{{ $s->name }}</h3>
              <span class="ranking-card__action">View leaderboard <i class="ti ti-arrow-right" aria-hidden="true"></i></span>
            </a>
          </div>
        @endforeach
      </div>
    @endif
  </section>
</main>
@endsection
