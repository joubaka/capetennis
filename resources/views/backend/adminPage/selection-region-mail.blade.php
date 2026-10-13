@extends('layouts.backend')
@section('title', 'Email participating regions')
@section('content')
<div class="container-xxl py-3">
<h3>{{ $event->name }} — Email participating regions</h3>
<p>Select the regions whose players and parents should receive this email. Every email contains all saved selected teams and reserves, across all age groups. Sending this private communication does not publish selections or create invitations.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('backend.selection-region-mail.preview', $event) }}">@csrf
<input type="hidden" name="selection_fingerprint" value="{{ old('selection_fingerprint', $draft['selection_fingerprint']) }}">
<div class="card card-body mb-3"><h5>1. Select regions</h5>
<label class="mb-2"><input type="checkbox" id="select-all-regions" @checked(count(old('selection_region_ids', $selectedRegions)) === $regions->count())> Select all participating regions</label>
@foreach($regions as $region)<label class="d-block mb-2"><input type="checkbox" name="selection_region_ids[]" value="{{ $region->region_id }}" @checked(in_array($region->region_id, old('selection_region_ids', $selectedRegions)))> {{ $region->region?->region_name }}</label>@endforeach
<p class="form-text">Recipients are current participating players and their linked parent contacts, including reserves, in the selected regions. Shared addresses receive one email. Missing contacts will be shown for review.</p></div>
<div class="card card-body mb-3"><h5>2. Customize email</h5>
<label class="d-block w-100 mb-3">Subject<input class="form-control" name="subject" maxlength="200" value="{{ old('subject', $draft['subject']) }}" required></label>
<label class="d-block w-100 mb-3">Message<textarea class="form-control" name="body" rows="24" maxlength="30000" required>{{ old('body', $draft['body']) }}</textarea></label>
<label class="d-block w-100 mb-3">Sender name<input class="form-control" name="from_name" maxlength="150" value="{{ old('from_name', $draft['from_name'] ?? auth()->user()->name) }}" required></label>
<label class="d-block w-100">Reply-to email<input class="form-control" type="email" name="reply_to" value="{{ old('reply_to', $draft['reply_to'] ?? auth()->user()->email) }}" required></label></div>
<button class="btn btn-primary">Review recipients and email</button>
<a class="btn btn-outline-secondary" href="{{ route('backend.selection-preview.show', $event) }}">Back to selections</a>
<p class="mt-2">Nothing is sent at this step. You will approve the reviewed email on the next screen.</p>
</form></div>
<script>
const regionBoxes = [...document.querySelectorAll('input[name="selection_region_ids[]"]')];
const allRegions = document.getElementById('select-all-regions');
allRegions.addEventListener('change', () => regionBoxes.forEach(box => box.checked = allRegions.checked));
regionBoxes.forEach(box => box.addEventListener('change', () => { allRegions.checked = regionBoxes.every(item => item.checked); allRegions.indeterminate = regionBoxes.some(item => item.checked) && !allRegions.checked; }));
</script>
@endsection
