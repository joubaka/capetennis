@extends('layouts.backend')
@section('title', 'Review host event proposal')
@section('content')
@include('backend.marketing.header')
<div class="card mb-4" style="overflow-wrap:anywhere"><div class="card-body"><span class="badge bg-label-primary">Proposal #{{ $application->id }} · {{ ucfirst($application->status) }}</span><h2 class="h4 mt-3">{{ $application->event_name }}</h2>
<dl class="row"><dt class="col-sm-4">Organiser</dt><dd class="col-sm-8">{{ $application->academy }} · {{ $application->contact_name }}</dd><dt class="col-sm-4">Contact</dt><dd class="col-sm-8" style="overflow-wrap:anywhere">{{ $application->email }}<br>{{ $application->phone }}</dd><dt class="col-sm-4">Venue</dt><dd class="col-sm-8">{{ $application->venue }}, {{ $application->town }}</dd><dt class="col-sm-4">Proposed dates</dt><dd class="col-sm-8">{{ $application->start_date->format('d M Y') }}–{{ $application->end_date->format('d M Y') }}</dd><dt class="col-sm-4">Available courts</dt><dd class="col-sm-8">{{ $application->courts }}</dd></dl>
<h3 class="h6">Requested categories and fees</h3><div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th class="text-nowrap">Limit</th><th>Fee</th></tr></thead><tbody>@foreach($application->requested_categories as $category)<tr><td>{{ $category['name'] }}</td><td>{{ $category['limit'] }}</td><td class="text-nowrap">R{{ number_format((float)$category['fee'], 2) }}</td></tr>@endforeach</tbody></table></div>
<p class="mt-3" style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $application->notes }}</p>
@include('frontend.host-events.court-plan')
<p class="small text-muted">Submission alerts: {{ $notificationCounts->sum() }} recorded. @foreach($notificationCounts as $status => $total) {{ $total }} {{ str_replace('_', ' ', $status) }}. @endforeach Queued is not delivered; “needs review” or “sending” can indicate an ambiguous delivery outcome.</p>
</div></div>
@if($application->status === 'pending')
<div class="card"><div class="card-body"><h2 class="h5">Cape Tennis review</h2><p>Check venue capacity, dates, organiser details and the proposed categories. Approval permits preparation of an event; it does not publish it or grant organiser access.</p>
<form method="post" action="{{ route('superadmin.marketing.applications.review', $application) }}">@csrf
<label class="form-label" for="review_notes">Review notes *</label><textarea class="form-control mb-3" name="review_notes" id="review_notes" rows="4" maxlength="10000" required>{{ old('review_notes') }}</textarea>
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="confirm" name="confirm" value="1" required><label class="form-check-label" for="confirm">I have reviewed this proposal and confirm this decision.</label></div>
<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" name="decision" value="approved">Approve proposal</button><button class="btn btn-outline-danger" name="decision" value="rejected">Reject proposal</button></div>
</form></div></div>
@else
<div class="card"><div class="card-body"><h2 class="h5">Review recorded</h2><p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $application->review_notes }}</p><p class="small text-muted">Reviewed {{ $application->reviewed_at?->format('d M Y H:i') }} by account #{{ $application->reviewed_by }}.</p>
@if($application->status === 'approved')
  @if($application->event)
  <p>{{ $application->event->published ? 'The linked event is published.' : 'The linked event is unpublished. Complete its categories, fees, venue and deadlines before publishing.' }}</p><a class="btn btn-primary" href="{{ route('backend.events.edit', $application->event) }}">Configure event and publication</a><a class="btn btn-outline-secondary" href="{{ route('admin.events.overview', $application->event) }}">Event workspace</a>
  @else
  <p>Next, use the standard event form to create an unpublished event. Requested categories and fees must then be configured and verified through the event workspace.</p><a class="btn btn-primary" href="{{ route('backend.events.create', ['host_application' => $application->id]) }}">Prepare unpublished event</a>
  @endif
@endif
</div></div>
@endif
@endsection
