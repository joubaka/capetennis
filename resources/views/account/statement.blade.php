@extends('layouts.backend')
@section('title', 'Account statement')
@section('content')
@include('account.statement-styles')
<x-backend.page-header title="Account statement" eyebrow="Cape Tennis" subtitle="Your entry fees, clothing payments and completed refunds." icon="ti-file-invoice" />
<div class="card mb-4 statement-controls"><div class="card-body">
    @if($adminView ?? false)
        <div class="alert alert-info"><strong>Super Admin: viewing another account</strong><br>{{ $account->name }} · {{ $account->email }} · Account #{{ $account->id }}</div>
        <a href="{{ route('superadmin.account-statements.index') }}" class="btn btn-outline-secondary mb-3">Choose another user</a>
    @else
        <a href="{{ route('backend.dashboard') }}" class="btn btn-outline-secondary mb-3">Back to my account</a>
    @endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="get" action="{{ $statementUrl ?? route('account.statement') }}" class="row g-3 align-items-end">
        <div class="col-12 col-lg-6"><label for="statement-period" class="form-label">Statement period</label><select id="statement-period" name="all_time" class="form-select"><option value="1" @selected($allTime ?? false)>All time — from the start</option><option value="0" @selected(! ($allTime ?? false))>Selected recorded dates</option></select></div>
        <div class="col-12 col-lg-6"><label for="statement-category" class="form-label">Payments included</label><select id="statement-category" name="category" class="form-select"><option value="all" @selected($category === 'all')>Entry fees and clothing</option><option value="entry" @selected($category === 'entry')>Entry fees only</option><option value="clothing" @selected($category === 'clothing')>Clothing only</option></select></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-from" class="form-label">Recorded from</label><input id="statement-from" class="form-control" type="date" name="from" required value="{{ old('from', $from) }}"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-to" class="form-label">Recorded to</label><input id="statement-to" class="form-control" type="date" name="to" required value="{{ old('to', $to) }}"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-player" class="form-label">Player name (optional)</label><input id="statement-player" class="form-control" name="player" maxlength="100" value="{{ old('player', $player) }}" placeholder="All players you paid for"></div>
        <div class="col-12 col-lg-3 d-flex flex-wrap gap-2"><button class="btn btn-primary">View</button><button name="format" value="pdf" class="btn btn-outline-primary">Download PDF</button><button name="format" value="csv" class="btn btn-outline-secondary">CSV</button></div>
    </form>
    <p class="text-muted small mt-3 mb-0">All time includes recorded payments from the start. For a selected period, choose up to two years. Historical records with unavailable payment dates appear separately below.</p>
</div></div>
<section class="card ct-statement-document"><div class="card-body p-3 p-sm-4">
    @include('account.statement-heading')
    @include('account.statement-warnings')
    <div class="statement-section">
        <h2 class="statement-section-title">Payment transactions</h2>
        <p class="statement-caption">{{ $payments->total() }} records. Totals cover all matching pages.</p>
        <div class="table-responsive">@include('account.statement-table', ['statementRows' => $payments])</div>
        <div class="statement-pagination mt-3">{{ $payments->links('pagination::bootstrap-5') }}</div>
    </div>
    <div class="statement-section">
        <p class="statement-caption">{{ $historicalPayments->total() }} historical paid records shown separately; excluded from known totals.</p>
        @include('account.statement-history', ['historyRows' => $historicalPayments])
        <div class="statement-pagination mt-3">{{ $historicalPayments->links('pagination::bootstrap-5') }}</div>
    </div>
    <div class="statement-notes">@include('account.statement-notes')</div>
    <p class="statement-footer">Generated {{ $generated_at->format('Y-m-d H:i T') }} · Cape Tennis · Account #{{ $account->id }}</p>
</div></section>
@endsection
