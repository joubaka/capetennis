@extends('layouts.backend')
@section('title', 'Share nomination report')
@section('content')
<div class="card"><div class="card-body" data-roster-report data-url="{{ route('backend.cavaliers-report.index', $event) }}" data-preview-url="{{ route('backend.cavaliers-report.preview', $event) }}">
<h3>Share nomination report</h3><p>{{ $event->name }} · All prepared divisions</p>
<p>Choose up to 30 accounts, then preview the email and PDF. Each division includes its saved team, up to five reserves, the next five outside that list, and explanations for Inter-Districts players outside the top 32.</p>
<form data-find class="d-flex flex-wrap gap-2 mb-3"><input type="search" class="form-control" style="max-width:30rem" aria-label="Account name or email" placeholder="Account name or email" minlength="2" maxlength="80" required><button class="btn btn-outline-primary">Find users</button></form>
<div data-results class="list-group mb-3"></div><h5>Selected recipients</h5><ul data-chosen class="list-group mb-3"></ul>
<fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto px-1 h6">Sender details</legend><div class="row g-3">
<div class="col-12 col-lg-4"><label class="form-label" for="roster-from-name">Sender name</label><input class="form-control" id="roster-from-name" data-from-name type="text" value="{{ $fromName }}" maxlength="120" required></div>
<div class="col-12 col-lg-4"><label class="form-label" for="roster-from-address">From email address</label><select class="form-select" id="roster-from-address" data-from-address required>@foreach ($fromAddresses as $address)<option value="{{ $address }}" @selected($address === $fromAddress)>{{ $address }}</option>@endforeach</select></div>
<div class="col-12 col-lg-4"><label class="form-label" for="roster-reply-to">Reply-to email address</label><input class="form-control" id="roster-reply-to" data-reply-to type="email" value="{{ $replyTo }}" maxlength="254" required></div>
</div><p class="small text-muted mt-2 mb-0">From addresses use your configured verified senders. Set Reply-to to the address that should receive responses.</p></fieldset>
<div class="mb-3"><label class="form-label" for="roster-email-subject">Email subject</label><input class="form-control" id="roster-email-subject" data-subject type="text" value="{{ $subject }}" maxlength="200" required></div>
<div class="mb-3"><label class="form-label" for="roster-email-message">Email message</label><textarea class="form-control" id="roster-email-message" data-message rows="10" maxlength="10000" required>{{ $message }}</textarea><p class="small text-muted mt-1">Edit the message before previewing. It is sent as plain text with your line breaks preserved.</p></div>
<button class="btn btn-primary" type="button" data-preview>Preview email and PDF</button>
<p data-status role="status" aria-live="polite" class="mt-2"></p>
<section data-review hidden><h4>Review before sending</h4><p data-reviewed-recipients></p><h5>Email preview</h5><p data-reviewed-sender style="overflow-wrap:anywhere"></p><p><strong>Subject:</strong> <span data-reviewed-subject></span></p><div class="border rounded p-3" style="white-space:pre-wrap;overflow-wrap:anywhere;color:#17283b;background:#fff" data-email aria-label="Email message preview"></div>
<h5 class="mt-3">PDF attachment preview</h5><a data-pdf-link target="_blank" rel="noopener">Open PDF in a new tab</a><iframe title="PDF attachment preview" style="width:100%;height:38rem;border:1px solid #ddd" data-pdf></iframe>
<button class="btn btn-primary mt-3" type="button" data-send>Send reviewed email and PDF</button></section>
</div></div>
<script src="{{ asset('assets/js/cavaliers-report-share.js') }}?v={{ filemtime(public_path('assets/js/cavaliers-report-share.js')) }}" defer></script>
@endsection
