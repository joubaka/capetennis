@extends('layouts.backend')
@section('title', 'Account statement')
@section('content')
<x-backend.page-header title="Account statement" eyebrow="Cape Tennis" subtitle="Your entry fees, clothing payments and completed refunds." icon="ti-file-invoice" />
<div class="card mb-4"><div class="card-body">
    @if($adminView ?? false)
        <div class="alert alert-info"><strong>Super Admin: viewing another account</strong><br>{{ $account->name }} · {{ $account->email }} · Account #{{ $account->id }}</div>
        <a href="{{ route('superadmin.account-statements.index') }}" class="btn btn-outline-secondary mb-3">Choose another user</a>
    @else
        <a href="{{ route('backend.dashboard') }}" class="btn btn-outline-secondary mb-3">Back to my account</a>
    @endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="get" action="{{ $statementUrl ?? route('account.statement') }}" class="row g-3 align-items-end">
        <div class="col-12"><label for="statement-category" class="form-label">Payments included</label><select id="statement-category" name="category" class="form-select"><option value="all" @selected($category === 'all')>Entry fees and clothing</option><option value="entry" @selected($category === 'entry')>Entry fees only</option><option value="clothing" @selected($category === 'clothing')>Clothing only</option></select></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-from" class="form-label">Recorded from</label><input id="statement-from" class="form-control" type="date" name="from" required value="{{ old('from', $from) }}"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-to" class="form-label">Recorded to</label><input id="statement-to" class="form-control" type="date" name="to" required value="{{ old('to', $to) }}"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-player" class="form-label">Player name (optional)</label><input id="statement-player" class="form-control" name="player" maxlength="100" value="{{ old('player', $player) }}" placeholder="All players you paid for"></div>
        <div class="col-12 col-lg-3 d-flex flex-wrap gap-2"><button class="btn btn-primary">View</button><button name="format" value="pdf" class="btn btn-outline-primary">Download PDF</button><button name="format" value="csv" class="btn btn-outline-secondary">CSV</button></div>
    </form>
    <p class="text-muted small mt-3 mb-0">Choose up to two years. For 2025 and 2026, use 1 January 2025 to 31 December 2026.</p>
</div></div>
<div class="card"><div class="card-body">
    <h2 class="h5">Cape Tennis account payment statement</h2>
    @include('account.statement-warnings')
    <p>{{ $account->name }} · {{ $account->email }}<br>Account #{{ $account->id }} · Generated {{ $generated_at->format('Y-m-d H:i T') }}<br>Recorded period: {{ $from }} to {{ $to }}@if($player) · Player search: {{ $player }}@endif</p>
    @if($player)<div class="alert alert-info">These are full matching checkout amounts, including other players in a shared checkout. They are not player-only expenses.</div>@endif
    <p>Payments included: {{ $category === 'all' ? 'Entry fees and clothing' : ucfirst($category) }}</p>
    <div class="d-flex flex-wrap gap-4 mb-3"><span>Payments: <strong>R {{ number_format($paid / 100, 2) }}</strong></span><span>Completed refunds: <strong>R {{ number_format($refunded / 100, 2) }}</strong></span><span>Net payments: <strong>R {{ number_format($net / 100, 2) }}</strong></span></div>
    <p class="small text-muted">{{ $payments->total() }} records. Totals cover all matching pages.</p>
    <div class="table-responsive">@include('account.statement-table', ['statementRows' => $payments])</div>
    {{ $payments->links('pagination::bootstrap-5') }}
    <div class="small text-muted mt-4">@include('account.statement-notes')</div>
</div></div>
@endsection
