@extends('layouts.backend')
@section('title', 'Wallets')
@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<h4>Wallets</h4>
<p class="text-muted">Open a user wallet to review its balance and transaction history.</p>
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Wallet</th><th>Account</th><th>Balance</th><th>Review</th></tr></thead>
<tbody>
@forelse($wallets as $wallet)
<tr><td>#{{ $wallet->id }}</td><td>{{ $wallet->payable?->name ?? 'Account unavailable' }}</td><td>R{{ number_format($wallet->balance, 2) }}</td>
<td>@if($wallet->payable instanceof \App\Models\User)<a class="btn btn-outline-primary" href="{{ route('wallet.show', $wallet->payable_id) }}">Open wallet</a>@else<span class="text-muted">No user wallet link</span>@endif</td></tr>
@empty
<tr><td colspan="4" class="text-center py-4">No wallets found.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $wallets->links() }}</div>
</div>
@endsection
