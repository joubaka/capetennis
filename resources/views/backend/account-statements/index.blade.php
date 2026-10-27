@extends('layouts.backend')
@section('title', 'User account statements')
@section('content')
<x-backend.page-header title="User account statements" eyebrow="Super Admin" subtitle="Find a payer account and download its entry fee and clothing payment history." icon="ti-file-invoice" />
<div class="card"><div class="card-body">
    <a href="{{ route('backend.dashboard') }}" class="btn btn-outline-secondary mb-3">Back to dashboard</a>
    <form action="{{ route('superadmin.account-statements.index') }}" method="get" class="row g-3 align-items-end mb-4">
        <div class="col-12 col-md-8"><label for="statement-user-search" class="form-label">Search name or email</label><input id="statement-user-search" name="search" class="form-control" maxlength="100" value="{{ $search }}" placeholder="Username, first name, surname or email"></div>
        <div class="col-12 col-md-4 d-flex flex-wrap gap-2"><button class="btn btn-primary">Search</button><a href="{{ route('superadmin.account-statements.index') }}" class="btn btn-outline-secondary">Clear</a></div>
    </form>
    <p class="small text-muted">{{ $users->total() }} matching accounts. Users do not need a wallet to have a statement.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Account</th><th>Email</th><th>Statement</th></tr></thead><tbody>
        @forelse($users as $user)<tr><td>{{ $user->name }}<br><small>{{ trim(($user->userName ?? '').' '.($user->userSurname ?? '')) }}</small></td><td class="text-break">{{ $user->email }}</td><td><a class="btn btn-outline-primary" href="{{ route('superadmin.account-statements.show', $user) }}">View statement</a></td></tr>
        @empty<tr><td colspan="3">No accounts match this search.</td></tr>@endforelse
    </tbody></table></div>
    {{ $users->links('pagination::bootstrap-5') }}
</div></div>
@endsection
