@extends('layouts.backend')

@section('title', 'Super Admin â€“ Financial Dashboard')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<style>
  .finance-amount { white-space: nowrap; font-variant-numeric: tabular-nums; }
  #financeTable .finance-event { min-width: 230px; }
  #financeTable .finance-date { white-space: nowrap; }
  .balance-positive { color: #28a745; font-weight: 600; }
  .balance-negative { color: #dc3545; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')
<div class="container-xl">

  {{-- HEADER --}}
  <x-backend.page-header title="Financial dashboard" eyebrow="Administration" subtitle="Financial years, balances and event statements." icon="ti-report-money">
  <x-slot:actions><a href="{{ route('backend.superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>Back to Dashboard
      </a></x-slot:actions>
</x-backend.page-header>

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

  @php
    $category = request('category') === 'clothing' ? 'clothing' : 'registration';
    $isClothing = $category === 'clothing';
    $columns = ['received' => 'Received', 'net' => 'Net income', 'paid_out' => 'Paid out'];
    if ($isClothing) $columns['recoveries'] = 'Recoveries';
    $columns['balance'] = 'Balance';
  @endphp
  <nav class="nav nav-pills gap-2 mb-3" aria-label="Finance category">
    @foreach(['registration' => 'Registrations', 'clothing' => 'Clothing'] as $key => $label)
      <a class="nav-link {{ $category === $key ? 'active' : '' }}" {{ $category === $key ? 'aria-current=page' : '' }}
         href="{{ request()->fullUrlWithQuery(['category' => $key]) }}">{{ $label }}</a>
    @endforeach
  </nav>
  <div class="row g-3 mb-3">
    @foreach(['received' => 'Received', 'net' => 'Net income', 'paid_out' => 'Paid out', 'balance' => 'Balance'] as $key => $label)
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
          <small class="text-muted">{{ $isClothing ? 'Clothing' : 'Registration' }} {{ strtolower($label) }}</small>
          <h5 class="finance-amount mb-1 {{ $financeSummary[$category.'_'.$key] < 0 ? 'text-danger' : '' }}">R {{ number_format($financeSummary[$category.'_'.$key], 2) }}</h5>
          <small class="text-muted">FY {{ $currentFY }}</small>
        </div></div>
      </div>
    @endforeach
  </div>
  <p class="small text-muted mb-3">
    Net income includes fees and completed refunds. Pending refunds remain liabilities.
    @if($isClothing)
      Balance includes recorded clothing payouts and recoveries. Recoveries: <span class="finance-amount">R {{ number_format($financeSummary['clothing_recoveries'], 2) }}</span>.
      @if($financeSummary['clothing_offsets'] != 0)
        Net regional offsets: <span class="finance-amount">R {{ number_format($financeSummary['clothing_offsets'], 2) }}</span>.
      @endif
      Open an event for the regional breakdown.
    @else
      Balance deducts registration payouts.
    @endif
  </p>
  <div class="card">
    <div class="card-header"><h5 class="mb-0">{{ $isClothing ? 'Clothing' : 'Registrations' }} by event &mdash; FY {{ $currentFY }}</h5></div>
    <div class="table-responsive">
      <table id="financeTable" class="table table-hover mb-0">
        <thead class="table-light"><tr>
          <th>Event</th><th>Date</th>
          @foreach($columns as $label)<th class="text-end">{{ $label }}</th>@endforeach
          @unless($isClothing)<th class="text-center">Entries</th>@endunless
          <th></th>
        </tr></thead>
        <tbody>
          @foreach($financeByEvent as $row)
            <tr>
              <td class="finance-event"><a href="{{ route('superadmin.finances.event', $row['event']) }}" class="fw-semibold text-primary">{{ $row['event']->name }}</a></td>
              <td class="finance-date" data-order="{{ $row['event']->start_date?->format('Y-m-d') ?? '' }}"><small class="text-muted">{{ $row['event']->start_date?->format('d M Y') ?? '—' }}</small></td>
              @foreach($columns as $key => $label)
                @php($amount = $row[$category.'_'.$key])
                <td class="text-end finance-amount {{ $key === 'balance' ? ($amount < 0 ? 'balance-negative' : 'balance-positive') : '' }}" data-order="{{ number_format($amount, 2, '.', '') }}">R {{ number_format($amount, 2) }}</td>
              @endforeach
              @unless($isClothing)<td class="text-center" data-order="{{ $row['total_entries'] }}">{{ number_format($row['total_entries']) }}</td>@endunless
              <td><a href="{{ route('superadmin.finances.event', $row['event']) }}" class="btn btn-icon btn-sm btn-outline-warning" title="View transactions and payouts" aria-label="View transactions and payouts"><i class="ti ti-report-money"></i></a></td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="table-light fw-bold"><tr>
          <td colspan="2">Totals</td>
          @foreach($columns as $key => $label)<td class="text-end finance-amount">R {{ number_format($financeSummary[$category.'_'.$key], 2) }}</td>@endforeach
          @unless($isClothing)<td class="text-center">{{ number_format($financeSummary['total_entries']) }}</td>@endunless
          <td></td>
        </tr></tfoot>
      </table>
    </div>
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
    order: [[1, 'desc']],  // Latest event dates first.
    columnDefs: [{ orderable: false, targets: [-1] }, { type: 'num', targets: [2, 3, 4, 5, 6] }],
    autoWidth: false,
    language: { emptyTable: 'No events found for this financial year.' },
    pageLength: 25,
  });
});
</script>
@endsection
