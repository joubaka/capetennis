@extends('layouts/contentNavbarLayout')
@section('title', 'Propose your CTR event')
@section('content')
<div class="container-xxl container-p-y" style="max-width:1000px;overflow-wrap:anywhere">
<h1 class="h3">Create your event proposal</h1><p>Cape Tennis will review your plan before an event can be prepared and published.</p>
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@php($value = function ($key, $fallback = null) use ($input) { $candidate = data_get($input, $key, $fallback); return is_array($fallback) ? (is_array($candidate) ? $candidate : $fallback) : (is_scalar($candidate) ? $candidate : $fallback); })
<form method="post" action="{{ route('host-events.store') }}" data-host-proposal data-suggestions-url="{{ route('host-events.suggestions') }}" data-plan-ready="{{ $plan && $projection && $projection['totals']['entries'] > 0 ? '1' : '0' }}">@csrf
<input type="hidden" name="submission_token" value="{{ $token }}">
<div class="card mb-4"><div class="card-body"><h2 class="h5">Event and organiser</h2><div class="row g-3">
@foreach(['event_name' => 'Proposed event name', 'academy' => 'Academy / organisation', 'contact_name' => 'Contact name', 'email' => 'Contact email', 'phone' => 'Phone / WhatsApp', 'town' => 'Town', 'venue' => 'Proposed venue'] as $field => $label)
<div class="col-12 col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" maxlength="{{ $field === 'phone' ? 40 : ($field === 'academy' ? 191 : 255) }}" required value="{{ $value($field, $field === 'email' ? auth()->user()->email : ($field === 'contact_name' ? auth()->user()->name : '')) }}"></div>
@endforeach
@foreach(['start_date' => 'Proposed start date', 'end_date' => 'Proposed end date'] as $field => $label)<div class="col-12 col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="date" min="{{ now()->toDateString() }}" required value="{{ $value($field) }}"></div>@endforeach
<div class="col-12"><label class="form-label" for="notes">Additional details</label><textarea class="form-control" name="notes" id="notes" rows="4" maxlength="10000">{{ $value('notes') }}</textarea></div>
</div></div></div>
<div class="card mb-4"><div class="card-body">
<h2 class="h5">Courts and requested categories</h2>
<p>Assign your available full-size courts to the categories you want to offer. Suggested entries update automatically.</p>
<div class="mb-3" style="max-width:22rem"><label class="form-label" for="courts">Total available full-size courts *</label><input class="form-control" name="courts" id="courts" type="number" min="1" max="100" required value="{{ $value('courts', $capacityDefaults['courts']) }}" data-capacity-input></div>
<p class="small text-muted">One Red court accommodates 16 players using four mini courts. Each Orange, Green or Yellow court accommodates four players. Assign zero courts to leave out a category.</p>
<div class="alert {{ $plan ? 'alert-info' : 'alert-warning' }}" data-capacity-status role="status" aria-live="polite">@if($plan){{ $plan['assigned_total'] }} of {{ $plan['inputs']['courts'] }} courts assigned · {{ $plan['suggested_entries'] }} suggested places.@else Check the court assignments to update suggestions. @endif</div>
<div class="table-responsive" style="max-width:100%"><table class="table"><thead><tr><th>Category</th><th>Assigned courts</th><th>Suggested entries</th><th>Your entry limit</th><th>Fee (R)</th></tr></thead><tbody>
@foreach(\App\Services\Marketing\PanarottisCalculator::CATEGORIES as $i => $category)
@php($suggestion = $plan['limits'][$i] ?? 0)
<tr><th scope="row">{{ $category }}</th><td><input class="form-control" style="min-width:6rem" type="number" min="0" max="100" required name="assigned_courts[{{ $i }}]" aria-label="{{ $category }} assigned courts" value="{{ $value('assigned_courts.'.$i, $capacityDefaults['assigned_courts'][$i]) }}" data-capacity-input></td><td><output data-court-suggestion="{{ $i }}">{{ $plan ? $suggestion : '—' }}</output></td><td><input class="form-control" style="min-width:6rem" type="number" min="0" max="{{ $suggestion }}" step="4" required name="limits[{{ $i }}]" aria-label="{{ $category }} entry limit" value="{{ $value('limits.'.$i, $suggestion) }}" data-entry-limit="{{ $i }}"></td><td><input class="form-control" style="min-width:6rem" type="number" min="0" max="100000" step="0.01" required name="fees[{{ $i }}]" data-projection-input aria-label="{{ $category }} entry fee" value="{{ $value('fees.'.$i, $defaults['fees'][$i]) }}"></td></tr>
@endforeach
</tbody></table></div>
<p class="small text-muted mt-3">You may reduce entry limits in groups of four, never above the suggestions. The default 52-place example assigns 10 courts: Red 1, Orange 4, Green 3 and Yellow 2. More assigned courts increase the suggested entries.</p>
<p class="small text-muted">Court layouts, safe mini-court setup and the actual playing schedule still need Cape Tennis review. These are proposed categories and fees; submitting does not publish an event or grant organiser access.</p>
<noscript><p class="alert alert-warning">Enable JavaScript for automatic suggestions. Submission still checks court assignments and entry limits on the server.</p></noscript>
</div></div>
<div class="card mb-4"><div class="card-body"><h2 class="h5">Hosting costs</h2><p class="small text-muted">Adjust your planning expenses. Rental uses the number of assigned courts, including courts for a category whose entry limit you reduce to zero.</p><div class="row g-3">
@foreach(['convenor_cost' => ['Convenor for the morning (R)', 100000], 'rental_hours' => ['Rental hours per assigned court', 24], 'rental_rate' => ['Rental per court per hour (R)', 100000], 'other_costs' => ['Other expenses (R)', 100000]] as $field => $settings)
<div class="col-12 col-sm-6"><label class="form-label" for="{{ $field }}">{{ $settings[0] }}</label><input class="form-control" type="number" min="0" max="{{ $settings[1] }}" step="0.01" required name="{{ $field }}" id="{{ $field }}" value="{{ $value($field, $projectionDefaults[$field]) }}" data-projection-input></div>
@endforeach
</div></div></div>
@include('frontend.host-events.financial-projection')
<div class="d-flex flex-wrap gap-2 mb-4"><button class="btn btn-primary" data-host-submit @disabled(!$plan || !$projection || $projection['totals']['entries'] === 0)>Submit for Cape Tennis review</button><a class="btn btn-outline-secondary" href="{{ route('host-events.index') }}">Cancel</a></div>
</form></div>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/host-court-capacity.js') }}?v={{ filemtime(public_path('assets/js/host-court-capacity.js')) }}" defer></script>
@endsection
