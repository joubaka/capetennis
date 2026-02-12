@extends('layouts/layoutMaster')

@section('title', 'Bank Refunds')

@section('content')
<div class="container mt-4">

  {{-- ================= PENDING ================= --}}
  <div class="card mb-4">
    <div class="card-header bg-warning">
      <h5 class="mb-0">Pending Bank Refunds</h5>
    </div>

    <div class="card-body">

      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Player</th>
            <th>Amount</th>
            <th>Account Name</th>
            <th>Bank</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          @forelse($pendingRefunds as $refund)
          <tr>
            <td>{{ $refund->id }}</td>
            <td>{{ $refund->display_name }}</td>
            <td>R{{ number_format($refund->refund_net, 2) }}</td>
            <td>{{ $refund->refund_account_name }}</td>
            <td>{{ $refund->refund_bank_name }}</td>
            <td>
              <a href="{{ route('admin.refunds.bank.show', $refund) }}"
                 class="btn btn-sm btn-primary">
                View
              </a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center">No pending refunds</td>
          </tr>
          @endforelse
        </tbody>
      </table>

    </div>
  </div>


  {{-- ================= COMPLETED ================= --}}
  <div class="card">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0">Completed Bank Refunds</h5>
    </div>

    <div class="card-body">

      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Player</th>
            <th>Amount</th>
            <th>Completed At</th>
          </tr>
        </thead>

        <tbody>
          @forelse($completedRefunds as $refund)
          <tr>
            <td>{{ $refund->id }}</td>
            <td>{{ $refund->display_name }}</td>
            <td>R{{ number_format($refund->refund_net, 2) }}</td>
            <td>{{ $refund->refunded_at?->format('d M Y H:i') }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="text-center">No completed refunds</td>
          </tr>
          @endforelse
        </tbody>
      </table>

    </div>
  </div>

</div>
@endsection
