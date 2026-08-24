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
  .maintenance-card p { color: #756d80; max-width: 34rem; font-size: 1rem; line-height: 1.7; margin-bottom: 1.5rem; }
  .maintenance-note { color: #8a8292; font-size: .875rem; }
  .maintenance-art { width: 100%; max-width: 430px; justify-self: center; }
  .maintenance-art img { display: block; width: 100%; height: auto; }
  @media (max-width: 767.98px) {
    .maintenance-page { padding: 1rem; }
    .maintenance-card { grid-template-columns: 1fr; padding: 1.5rem; border-radius: 1rem; text-align: center; }
    .maintenance-card p { margin-left: auto; margin-right: auto; }
    .maintenance-art { order: -1; max-width: 300px; }
  }
</style>
@endsection

@section('content')
<main class="maintenance-page" aria-labelledby="maintenance-title">
  <section class="maintenance-card">
    <div>
      <div class="maintenance-brand">Cape Tennis</div>
      <div class="maintenance-status mt-4" role="status">Scheduled maintenance</div>
      <h1 id="maintenance-title">We’ll be back shortly.</h1>
      <p>
        Cape Tennis is temporarily offline while we carry out important improvements.
        Your account and tournament data remain safe, and access will return as soon as the work is complete.
      </p>
      <div class="maintenance-note">Please try again in about 60 minutes. Thank you for your patience.</div>
    </div>
    <div class="maintenance-art">
      <img src="{{ asset('assets/img/illustrations/page-misc-under-maintenance.png') }}" alt="A person working on a laptop during maintenance">
    </div>
  </section>
</main>
@endsection
