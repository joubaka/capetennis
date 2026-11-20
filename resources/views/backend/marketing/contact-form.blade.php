@extends('layouts.backend')
@section('title', 'Coach contact')
@section('content')
@include('backend.marketing.header')
<div class="card"><div class="card-body">
<h2 class="h5">{{ $contact->exists ? 'Edit coach contact' : 'Add coach contact' }}</h2>
<form method="post" action="{{ $contact->exists ? route('superadmin.marketing.contacts.update', $contact) : route('superadmin.marketing.contacts.store') }}">
@csrf
@if($contact->exists) @method('put') <input type="hidden" name="version" value="{{ $contact->version }}"> @endif
<div class="row g-3">
@foreach(['academy' => 'Academy / organisation', 'name' => 'Coach / contact name', 'email' => 'Email', 'phone' => 'WhatsApp / phone', 'town' => 'Town', 'courts' => 'Available courts'] as $field => $label)
<div class="col-12 col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}{{ in_array($field, ['academy', 'name']) ? ' *' : '' }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'courts' ? 'number' : ($field === 'email' ? 'email' : 'text') }}" value="{{ old($field, $contact->$field) }}" @if(in_array($field, ['academy', 'name'])) required @endif @if($field === 'courts') min="0" max="100" @else maxlength="{{ $field === 'phone' ? 40 : 255 }}" @endif></div>
@endforeach
<div class="col-12 col-md-6"><label class="form-label" for="status">Interest status</label><select class="form-select" name="status" id="status">@foreach(['prospect', 'interested', 'discussing', 'approved', 'inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $contact->status ?? 'prospect') === $status)>{{ ucfirst($status) }}</option>@endforeach</select><small class="text-muted">A contact status does not certify or publish an event.</small></div>
<div class="col-12 col-md-6"><label class="form-label" for="preference">Communication preference</label><select class="form-select" name="preference" id="preference">@foreach(['none' => 'Not recorded', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'both' => 'Email and WhatsApp', 'opted_out' => 'Opted out'] as $value => $label)<option value="{{ $value }}" @selected(old('preference', $contact->preference ?? 'none') === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" rows="4" name="notes" id="notes" maxlength="10000">{{ old('notes', $contact->notes) }}</textarea></div>
</div>
<div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary">Save contact</button><a class="btn btn-outline-secondary" href="{{ route('superadmin.marketing.contacts.index') }}">Cancel</a></div>
</form>
@if($contact->exists)<details class="mt-4"><summary>Delete contact</summary><p class="small mt-2">Permanently remove this marketing contact.</p><form method="post" action="{{ route('superadmin.marketing.contacts.destroy', $contact) }}">@csrf @method('delete')<input type="hidden" name="version" value="{{ $contact->version }}"><button class="btn btn-outline-danger">Delete this contact</button></form></details>@endif
</div></div>
@endsection
