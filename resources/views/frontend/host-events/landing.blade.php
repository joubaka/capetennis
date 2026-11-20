@extends('layouts/contentNavbarLayout')
@section('title', 'Host a CTR event')
@section('content')
<div class="container-xxl container-p-y" style="max-width:950px;overflow-wrap:anywhere">
<div class="card"><div class="card-body p-4 p-md-5">
<div class="mb-3">@include('_partials.macros')</div>
<span class="badge bg-label-primary mb-3">Cape Tennis · CTR host programme</span>
<h1 class="h3">Host small. Develop players. Generate funds.</h1>
<p>Propose a local tournament for Cape Tennis to review. Start with the Stage One small-event template: 52 places across Red, Orange, Green and Yellow categories.</p>
<div class="row g-3 my-3"><div class="col-md-4"><h2 class="h6">Grow your programme</h2><p>Give players regular competition and welcome eligible players from any academy.</p></div><div class="col-md-4"><h2 class="h6">Generate funds</h2><p>Plan your entry income, convenor and court costs before committing to an event.</p></div><div class="col-md-4"><h2 class="h6">Support development</h2><p>Record qualifying results on Cape Tennis. Red, Orange and Green/Yellow ratings stay separate.</p></div></div>
<h2 class="h5">From proposal to published event</h2>
<ol><li>Sign in and submit your proposed event, venue and categories.</li><li>Cape Tennis reviews the details and decides whether to approve the proposal.</li><li>A super-admin prepares the event, confirms categories and fees, and explicitly publishes it when ready.</li></ol>
<p class="text-muted">Submitting a proposal does not certify or publish an event, open registration, grant account permissions or take payment.</p>
<a class="btn btn-primary" href="{{ route('host-events.create') }}">{{ auth()->check() ? 'Create an event proposal' : 'Sign in to propose an event' }}</a>
</div></div>
</div>
@endsection
