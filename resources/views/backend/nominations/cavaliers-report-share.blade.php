@extends('layouts.backend')
@section('title', 'Share nomination report')
@section('content')
<div class="card"><div class="card-body" data-roster-report data-url="{{ route('backend.cavaliers-report.index', $event) }}" data-preview-url="{{ route('backend.cavaliers-report.preview', $event) }}">
<h3>Share nomination report</h3><p>{{ $event->name }} · All prepared divisions</p>
<p>Choose up to 30 Cape Tennis accounts. Review the email and the attached PDF before sending. The PDF includes each saved team, its next five reserves, and the evidence for every available candidate.</p>
<form data-find class="d-flex flex-wrap gap-2 mb-3"><input type="search" class="form-control" style="max-width:30rem" aria-label="Account name or email" placeholder="Account name or email" minlength="2" maxlength="80" required><button class="btn btn-outline-primary">Find users</button></form>
<div data-results class="list-group mb-3"></div><h5>Selected recipients</h5><ul data-chosen class="list-group mb-3"></ul>
<button class="btn btn-primary" type="button" data-preview>Preview email and PDF</button>
<p data-status role="status" aria-live="polite" class="mt-2"></p>
<section data-review hidden><h4>Review before sending</h4><p data-reviewed-recipients></p><h5>Email preview</h5><iframe title="Email preview" sandbox style="width:100%;height:22rem;border:1px solid #ddd" data-email></iframe>
<h5 class="mt-3">PDF attachment preview</h5><a data-pdf-link target="_blank" rel="noopener">Open PDF in a new tab</a><iframe title="PDF attachment preview" style="width:100%;height:38rem;border:1px solid #ddd" data-pdf></iframe>
<button class="btn btn-primary mt-3" type="button" data-send>Send reviewed email and PDF</button></section>
</div></div>
<script src="{{ asset('assets/js/cavaliers-report-share.js') }}?v={{ filemtime(public_path('assets/js/cavaliers-report-share.js')) }}" defer></script>
@endsection
