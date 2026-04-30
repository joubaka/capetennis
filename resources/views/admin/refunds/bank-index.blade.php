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

      {{-- Debug: show counts so admins can see why navbar badge differs --}}
      <div class="mb-3">
        <span class="badge bg-info">Registration pending: {{ $pendingRefunds->count() ?? 0 }}</span>
        <span class="badge bg-primary">Team pending: {{ $pendingTeamRefunds->count() ?? 0 }}</span>
      </div>

      {{-- Bulk complete form (hidden; submitted by button below table) --}}
      <form id="bulk-complete-form"
            method="POST"
            action="{{ route('admin.refunds.bank.bulk-complete') }}"
            onsubmit="return confirm('Mark all selected registrations as completed?');">
        @csrf
        {{-- checkboxes injected by the table rows above --}}
      </form>

      <div class="mb-2">
        <button type="submit" form="bulk-complete-form" class="btn btn-sm btn-success">
          <i class="ti ti-checks me-1"></i> Mark Selected as Completed
        </button>
      </div>

      <table class="table table-bordered">
        <thead>
          <tr>
            <th style="width:36px;">
              <input type="checkbox" id="select-all" title="Select all">
            </th>
            <th>ID</th>
            <th>Player</th>
            <th>PayFast ID</th>
            <th>Amount</th>
            <th>Account Name</th>
            <th>Bank</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          {{-- Registration refunds --}}
          @forelse($pendingRefunds as $refund)
          <tr>
            <td><input type="checkbox" name="registration_ids[]" value="{{ $refund->id }}" form="bulk-complete-form" class="reg-checkbox"></td>
            <td>R-REG-{{ $refund->id }}</td>
            <td>{{ $refund->display_name }}</td>
            <td><code>{{ $refund->pf_transaction_id ?? '—' }}</code></td>
            <td>R{{ number_format($refund->refund_net, 2) }}</td>
            <td>{{ $refund->refund_account_name }}</td>
            <td>{{ $refund->refund_bank_name }}</td>
            <td>
              <a href="{{ route('admin.registration.refunds.bank.show', $refund) }}" class="btn btn-sm btn-primary">View</a>
            </td>
          </tr>
          @empty
          @endforelse

          {{-- Team refunds --}}
          @if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count())
            <tr>
              <td colspan="7"><strong>Team Refunds</strong></td>
            </tr>
            @foreach($pendingTeamRefunds as $t)
              <tr>
                <td>R-TEAM-{{ $t->id }}</td>
                <td>{{ optional($t->player)->name ?? 'Player #' . ($t->player_id ?? 'N/A') }}</td>
                <td><code>{{ $t->payfast_pf_payment_id ?? '—' }}</code></td>
                <td>R{{ number_format($t->refund_net, 2) }}</td>
                <td>{{ $t->refund_account_name }}</td>
                <td>{{ $t->refund_bank_name }}</td>
                <td>
                  <form method="POST" action="{{ route('admin.registration.refunds.bank.complete.team', $t) }}" onsubmit="return confirm('Mark this team bank refund as paid?');">
                    @csrf
                    <button class="btn btn-sm btn-success">Mark Paid</button>
                  </form>
                </td>
              </tr>
            @endforeach
          @else
            @if($pendingRefunds->isEmpty())
              <tr>
                <td colspan="7" class="text-center">No pending refunds</td>
              </tr>
            @endif
          @endif
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

      {{-- Completed team refunds --}}
      @if(!empty($completedTeamRefunds) && $completedTeamRefunds->count())
        <hr>
        <h6 class="mt-3">Completed Team Refunds</h6>
        <table class="table table-bordered mt-2">
          <thead>
            <tr>
              <th>ID</th>
              <th>Player</th>
              <th>Amount</th>
              <th>Completed At</th>
            </tr>
          </thead>
          <tbody>
            @foreach($completedTeamRefunds as $t)
              <tr>
                <td>R-TEAM-{{ $t->id }}</td>
                <td>{{ optional($t->player)->name ?? 'Player #' . ($t->player_id ?? '') }}</td>
                <td>R{{ number_format($t->refund_net, 2) }}</td>
                <td>{{ optional($t->refunded_at)->format('d M Y H:i') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif

    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
document.getElementById('select-all')?.addEventListener('change', function () {
  document.querySelectorAll('.reg-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endsection
