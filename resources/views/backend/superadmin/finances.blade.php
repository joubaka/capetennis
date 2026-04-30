@extends('layouts/layoutMaster')

@section('title', 'Super Admin – Financial Dashboard')

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<style>
  .balance-positive { color: #28a745; font-weight: 600; }
  .balance-negative { color: #dc3545; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        Financial Dashboard
      </h4>
      <a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>Back to Dashboard
      </a>
    </div>
  </div>

  {{-- SUMMARY CARDS --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-start border-success border-3">
        <div class="card-body">
          <small class="text-muted">Total Gross Income</small>
          <h5 class="text-success">R {{ number_format($financeSummary['total_gross'], 2) }}</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-primary border-3">
        <div class="card-body">
          <small class="text-muted">Total Net Income</small>
          <h5>R {{ number_format($financeSummary['total_income'], 2) }}</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-start border-danger border-3">
        <div class="card-body">
          <small class="text-muted">Total Paid Out</small>
          <h5 class="text-danger">R {{ number_format($financeSummary['total_paid_out'], 2) }}</h5>
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
        </div>
      </div>
    </div>
  </div>

  {{-- EVENT TABLE --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Per-Event Financial Summary</h5>
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
            <tr>
              <td>
                <a href="{{ route('superadmin.finances.event', $row['event']) }}" class="fw-semibold text-primary">
                  {{ $row['event']->name }}
                </a>
              </td>
              <td>
                <small class="text-muted">
                  {{ $row['event']->start_date ? \Carbon\Carbon::parse($row['event']->start_date)->format('d M Y') : '—' }}
                </small>
              </td>
              <td class="text-end text-success">R {{ number_format($row['total_gross'], 2) }}</td>
              <td class="text-end">R {{ number_format($row['total_income'], 2) }}</td>
              <td class="text-end text-danger">
                {{ $row['total_paid_out'] > 0 ? 'R ' . number_format($row['total_paid_out'], 2) : '—' }}
              </td>
              <td class="text-end {{ $row['balance'] < 0 ? 'balance-negative' : 'balance-positive' }}">
                R {{ number_format($row['balance'], 2) }}
              </td>
              <td class="text-center">{{ number_format($row['total_entries']) }}</td>
              <td>
                <a href="{{ route('superadmin.finances.event', $row['event']) }}"
                   class="btn btn-icon btn-sm btn-outline-warning" title="View Transactions & Payouts">
                  <i class="ti ti-report-money"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-3">No events found.</td>
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

@section('page-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script>
$(function () {
  $('#financeTable').DataTable({
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: [7] }],
    pageLength: 25,
  });
});
</script>
@endsection
