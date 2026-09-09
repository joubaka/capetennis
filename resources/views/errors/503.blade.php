@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', 'Cape Tennis | Maintenance')

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-misc.css')}}">
<style>
  .maintenance-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    position: relative;
    overflow: hidden;
    padding: 2rem 1rem;
    background: #faf9fc;
  }
  .maintenance-page::before {
    content: '';
    position: absolute;
    width: 34rem;
    height: 34rem;
    border-radius: 50%;
    background: rgba(115, 82, 194, .08);
    top: -17rem;
    right: -8rem;
  }
  .maintenance-card {
    position: relative;
    z-index: 1;
    width: min(100%, 980px);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(280px, 430px);
    align-items: center;
    gap: clamp(1.5rem, 5vw, 4rem);
    padding: clamp(1.5rem, 5vw, 4rem);
    border: 1px solid rgba(108, 99, 128, .12);
    border-radius: 1.5rem;
    background: rgba(255, 255, 255, .9);
    box-shadow: 0 1.25rem 3rem rgba(52, 44, 72, .1);
  }
  .maintenance-brand { color: #655b73; font-weight: 700; letter-spacing: .02em; }
  .maintenance-status {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .45rem .75rem;
    border-radius: 999px;
    background: #f1edff;
    color: #7250d1;
    font-size: .8rem;
    font-weight: 600;
  }
  .maintenance-status::before { content: ''; width: .5rem; height: .5rem; border-radius: 50%; background: #f0a34a; }
  .maintenance-card h1 { color: #51485f; font-size: clamp(2rem, 4vw, 3.25rem); line-height: 1.1; margin: 1.25rem 0 .9rem; }
  .maintenance-card p { color: #756d80; max-width: 34rem; font-size: 1rem; line-height: 1.7; margin-bottom: 1.25rem; }
  .maintenance-actions { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem 1rem; }
  .maintenance-retry {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.75rem;
    padding: .65rem 1rem;
    border-radius: .75rem;
    background: #7250d1;
    color: #fff;
    font-size: .875rem;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 .4rem 1rem rgba(114, 80, 209, .2);
  }
  .maintenance-retry:hover,
  .maintenance-retry:focus { background: #6241c1; color: #fff; }
  .maintenance-retry:focus-visible { outline: .2rem solid rgba(114, 80, 209, .3); outline-offset: .2rem; }
  .maintenance-note { color: #8a8292; font-size: .875rem; }
  .maintenance-art { width: 100%; max-width: 430px; justify-self: center; }
  .maintenance-art img { display: block; width: 100%; height: auto; }
  @media (max-width: 767.98px) {
    .maintenance-page { padding: 1rem; }
    .maintenance-card { grid-template-columns: 1fr; padding: 1.5rem; border-radius: 1rem; text-align: center; }
    .maintenance-card p { margin-left: auto; margin-right: auto; }
    .maintenance-actions { justify-content: center; }
    .maintenance-art { order: -1; max-width: 300px; }
  }
</style>
@endsection

@section('content')
<main class="maintenance-page" aria-labelledby="maintenance-title">
  <section class="maintenance-card">
    <div>
      <div class="maintenance-brand">Cape Tennis</div>
      <div class="maintenance-status mt-4" role="status">Quick system update</div>
      <h1 id="maintenance-title">We’ll be back shortly.</h1>
      <p>
        Cape Tennis is briefly unavailable while we deploy an update.
        Your account, entries and tournament data remain safe.
      </p>
      <div class="maintenance-actions">
        <a class="maintenance-retry" href="{{ request()->getRequestUri() }}">Check now</a>
        <div class="maintenance-note">
          We’ll check again automatically in <span id="maintenance-countdown" aria-hidden="true">20 seconds</span>.
        </div>
      </div>
    </div>
    <div class="maintenance-art">
      <img src="{{ asset('assets/img/illustrations/page-misc-under-maintenance.png') }}" alt="A person working on a laptop during maintenance">
    </div>
  </section>
</main>
@endsection

@section('page-script')
<script>
  (() => {
    const countdown = document.getElementById('maintenance-countdown');
    let secondsRemaining = 20;

    window.setInterval(() => {
      secondsRemaining -= 1;

      if (secondsRemaining <= 0) {
        window.location.reload();
        return;
      }

      if (countdown) {
        countdown.textContent = `${secondsRemaining} ${secondsRemaining === 1 ? 'second' : 'seconds'}`;
      }
    }, 1000);
  })();
</script>
@endsection
