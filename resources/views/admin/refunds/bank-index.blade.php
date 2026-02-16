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
          {{-- Registration refunds --}}
          @forelse($pendingRefunds as $refund)
          <tr>
            <td>R-REG-{{ $refund->id }}</td>
            <td>{{ $refund->display_name }}</td>
            <td>R{{ number_format($refund->refund_net, 2) }}</td>
            <td>{{ $refund->refund_account_name }}</td>
            <td>{{ $refund->refund_bank_name }}</td>
            <td>
              <a href="{{ route('admin.refunds.bank.show', $refund) }}" class="btn btn-sm btn-primary">View</a>
            </td>
          </tr>
          @empty
          @endforelse

          {{-- Team refunds --}}
          @if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count())
            <tr>
              <td colspan="6"><strong>Team Refunds</strong></td>
            </tr>
            @foreach($pendingTeamRefunds as $t)
              <tr>
                <td>R-TEAM-{{ $t->id }}</td>
                <td>{{ optional($t->player)->name ?? 'Player #' . ($t->player_id ?? 'N/A') }}</td>
                <td>R{{ number_format($t->refund_net, 2) }}</td>
                <td>{{ $t->refund_account_name }}</td>
                <td>{{ $t->refund_bank_name }}</td>
                <td>
                  <form method="POST" action="{{ route('admin.refunds.bank.complete.team', $t) }}" onsubmit="return confirm('Mark this team bank refund as paid?');">
                    @csrf
                    <button class="btn btn-sm btn-success">Mark Paid</button>
                  </form>
                </td>
              </tr>
            @endforeach
          @else
            @if($pendingRefunds->isEmpty())
              <tr>
                <td colspan="6" class="text-center">No pending refunds</td>
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
