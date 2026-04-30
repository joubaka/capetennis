@extends('layouts/layoutMaster')

@section('title', 'Super Admin – Financial Dashboard')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<style>
  .balance-positive { color: #28a745; font-weight: 600; }
  .balance-negative { color: #dc3545; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        Financial Dashboard
      </h4>
      <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>Back to Dashboard
      </a>
    </div>
  </div>

  {{-- FINANCIAL YEAR FILTER --}}
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="text-muted me-1 small fw-semibold">Financial Year:</span>
        @foreach($availableFYs->reverse() as $fy)
          <a href="{{ request()->fullUrlWithQuery(['fy' => $fy]) }}"
             class="btn btn-sm {{ $fy === $currentFY ? 'btn-warning' : 'btn-outline-secondary' }}">
            {{ $fy }}
          </a>
        @endforeach
      </div>
    </div>
  </div>

  {{-- SUMMARY CARDS --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-start border-success border-3">
        <div class="card-body">
          <small class="text-muted">Total Gross Income</small>
          <h5 class="text-success">R {{ number_format($financeSummary['total_gross'], 2) }}</h5>
          <small class="text-muted">FY {{ $currentFY }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-primary border-3">
        <div class="card-body">
          <small class="text-muted">Total Net Income</small>
          <h5>R {{ number_format($financeSummary['total_income'], 2) }}</h5>
          <small class="text-muted">FY {{ $currentFY }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-danger border-3">
        <div class="card-body">
          <small class="text-muted">Total Paid Out</small>
          <h5 class="text-danger">R {{ number_format($financeSummary['total_paid_out'], 2) }}</h5>
          <small class="text-muted">FY {{ $currentFY }}</small>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-info border-3">
        <div class="card-body">
          <small class="text-muted">Balance (unpaid)</small>
          <h5 class="{{ $financeSummary['balance'] < 0 ? 'text-danger' : 'text-success' }}">
            R {{ number_format($financeSummary['balance'], 2) }}
          </h5>
          <small class="text-muted">FY {{ $currentFY }}</small>
        </div>
      </div>
    </div>
  </div>

  {{-- EVENT TABLE --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Per-Event Financial Summary &mdash; FY {{ $currentFY }}</h5>
    </div>
    <div class="table-responsive">
      <table id="financeTable" class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th class="text-end">Gross Income</th>
            <th class="text-end">Net Income</th>
            <th class="text-end">Paid Out</th>
            <th class="text-end">Balance</th>
            <th class="text-center">Entries</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($financeByEvent as $row)
            @php
              $isPast    = $row['event']->start_date && $row['event']->start_date->isPast();
              $noTx      = ! $row['has_transactions'];
              $showAlert = $isPast && $noTx;
            @endphp
            <tr>
              <td>
                <a href="{{ route('superadmin.finances.event', $row['event']) }}" class="fw-semibold text-primary">
                  {{ $row['event']->name }}
                </a>
                @if($showAlert)
                  <span class="badge bg-label-secondary ms-1"
                        title="No PayFast transactions found for this past event"
                        aria-label="No PayFast transactions found for this past event">
                    <i class="ti ti-alert-circle me-1"></i>No transactions
                  </span>
                @endif
              </td>
              <td data-order="{{ $row['event']->start_date ?? '0000-00-00' }}">
                <small class="text-muted">
                  {{ $row['event']->start_date ? \Carbon\Carbon::parse($row['event']->start_date)->format('d M Y') : '—' }}
                </small>
              </td>
              <td class="text-end text-success" data-order="{{ $row['total_gross'] }}">R {{ number_format($row['total_gross'], 2) }}</td>
              <td class="text-end" data-order="{{ $row['total_income'] }}">R {{ number_format($row['total_income'], 2) }}</td>
              <td class="text-end text-danger" data-order="{{ $row['total_paid_out'] }}">
                {{ $row['total_paid_out'] > 0 ? 'R ' . number_format($row['total_paid_out'], 2) : '—' }}
              </td>
              <td class="text-end {{ $row['balance'] < 0 ? 'balance-negative' : 'balance-positive' }}" data-order="{{ $row['balance'] }}">
                R {{ number_format($row['balance'], 2) }}
              </td>
              <td class="text-center" data-order="{{ $row['total_entries'] }}">{{ number_format($row['total_entries']) }}</td>
              <td>
                <a href="{{ route('superadmin.finances.event', $row['event']) }}"
                   class="btn btn-icon btn-sm btn-outline-warning" title="View Transactions & Payouts">
                  <i class="ti ti-report-money"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-3">No events found for FY {{ $currentFY }}.</td>
            </tr>
          @endforelse
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="2">Totals</td>
            <td class="text-end text-success">R {{ number_format($financeSummary['total_gross'], 2) }}</td>
            <td class="text-end">R {{ number_format($financeSummary['total_income'], 2) }}</td>
            <td class="text-end text-danger">R {{ number_format($financeSummary['total_paid_out'], 2) }}</td>
            <td class="text-end">R {{ number_format($financeSummary['balance'], 2) }}</td>
            <td class="text-center">{{ number_format($financeSummary['total_entries']) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

</div>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('page-script')
<script>
$(function () {
  $('#financeTable').DataTable({
    order: [[3, 'desc']],  // Net Income DESC — events with actual transactions appear first
    columnDefs: [{ orderable: false, targets: [7] }],
    pageLength: 25,
  });
});
</script>
@endsection
