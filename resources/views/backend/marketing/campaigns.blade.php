@extends('layouts.backend')
@section('title', 'CTR campaign drafts')
@section('content')
@include('backend.marketing.header')
<div class="card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3"><h2 class="h5">Campaign drafts</h2><a class="btn btn-primary" href="{{ route('superadmin.marketing.campaigns.create') }}">Create campaign draft</a></div>
<p class="text-muted">Prepare email and WhatsApp copy. Saving a draft never sends or schedules a message.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Campaign</th><th>Status</th><th>Last saved</th><th>Action</th></tr></thead><tbody>
@forelse($campaigns as $campaign)<tr><td>{{ $campaign->title }}</td><td><span class="badge bg-label-secondary">Draft</span></td><td>{{ $campaign->updated_at->format('d M Y H:i') }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.marketing.campaigns.edit', $campaign) }}">Edit draft</a></td></tr>@empty<tr><td colspan="4">No drafts saved. Create a draft to start with the CTR host invitation template.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $campaigns->links() }}</div>
</div></div>
@endsection
