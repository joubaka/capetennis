@extends('layouts.backend')
@section('title', 'CTR marketing contacts')
@section('content')
@include('backend.marketing.header')
<div class="card"><div class="card-body">
  <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2"><label class="visually-hidden" for="search">Search coach contacts</label><input class="form-control w-auto" id="search" name="search" maxlength="100" value="{{ $search }}" placeholder="Academy, coach, town or email"><button class="btn btn-outline-primary">Search</button>@if($search !== '')<a class="btn btn-outline-secondary" href="{{ route('superadmin.marketing.contacts.index') }}">Clear</a>@endif</form>
    <a class="btn btn-primary" href="{{ route('superadmin.marketing.contacts.create') }}">Add coach contact</a>
  </div>
  <p class="small text-muted">{{ $contacts->total() }} contacts. Preferences are planning records, not proof of consent. No contacts are imported automatically.</p>
  <div class="table-responsive"><table class="table"><thead><tr><th>Academy / coach</th><th>Contact details</th><th>Venue</th><th>Interest</th><th>Preference</th><th>Action</th></tr></thead><tbody>
  @forelse($contacts as $contact)
    <tr><td><strong>{{ $contact->academy }}</strong><br>{{ $contact->name }}</td><td>{{ $contact->email ?: 'No email' }}<br>{{ $contact->phone ?: 'No phone' }}</td><td>{{ $contact->town ?: 'Not recorded' }}<br>{{ $contact->courts ?? 'Unknown' }} courts</td><td>{{ ucfirst($contact->status) }}</td><td>{{ ucfirst(str_replace('_', ' ', $contact->preference)) }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.marketing.contacts.edit', $contact) }}">Edit</a></td></tr>
  @empty<tr><td colspan="6">No coach contacts found. Add a contact to start planning outreach.</td></tr>@endforelse
  </tbody></table></div>
  <div class="mt-3">{{ $contacts->links() }}</div>
</div></div>
@endsection
