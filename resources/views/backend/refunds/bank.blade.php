@extends('layouts/layoutMaster')

@section('title', 'Bank Refunds')

@section('content')
<div class="container-xl">

  <h4 class="mb-3">Pending Bank Refunds</h4>

  @if(session('pf_query_result'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <i class="ti ti-search me-1"></i> {{ session('pf_query_result') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ $errors->first() }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(app()->environment('local'))
    <div class="mb-2">
      <small class="text-muted">Debug - registration pending: {{ $refunds->count() ?? 0 }} | team pending: {{ $pendingTeamRefunds->count() ?? 0 }}</small>
      @if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count())
        <div class="small mt-1">Team IDs: {{ $pendingTeamRefunds->pluck('id')->join(', ') }}</div>
      @endif
    </div>
  @endif

  @if((empty($refunds) || $refunds->isEmpty()) && (empty($pendingTeamRefunds) || $pendingTeamRefunds->isEmpty()))
    <div class="alert alert-success">
      No pending bank refunds 🎉
    </div>
  @else

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Event</th>
            <th>Player(s)</th>
            <th>User</th>
            <th>PayFast ID</th>
            <th>Gross</th>
            <th>Fee</th>
            <th>Net</th>
            <th>Withdrawn</th>
            <th></th>
          </tr>
        </thead>
        <tbody>

        @foreach($refunds as $reg)
          <tr>
            <td>
              <strong>{{ $reg->categoryEvent->event->name }}</strong><br>
              <small class="text-muted">
                {{ $reg->categoryEvent->name ?? '' }}
              </small>
            </td>

            <td>{{ $reg->display_name }}</td>

            <td>
              {{ $reg->user->name ?? '—' }}<br>
              <small class="text-muted">
                {{ $reg->user->email ?? '' }}
              </small>
            </td>

            <td><code>{{ $reg->pf_transaction_id ?? '—' }}</code></td>

            <td>R{{ number_format($reg->refund_gross, 2) }}</td>
            <td class="text-danger">
              R{{ number_format($reg->refund_fee, 2) }}
            </td>
            <td class="fw-bold text-success">
              R{{ number_format($reg->refund_net, 2) }}
            </td>

            <td>
              {{ optional($reg->withdrawn_at)->format('Y-m-d') }}
            </td>

            <td class="text-end">
              <a href="{{ route('admin.refunds.bank.show', $reg) }}" class="btn btn-sm btn-outline-primary me-1">
                👁 View
              </a>
              @if($reg->pf_transaction_id)
                <a href="{{ route('admin.refunds.bank.payfast-query', $reg) }}"
                   class="btn btn-sm btn-outline-secondary me-1"
                   title="Query PayFast refund status">
                  🔍 PF Status
                </a>
              @endif
              <form method="POST"
                    action="{{ route('admin.refunds.bank.complete', $reg) }}"
                    onsubmit="return confirm('Mark this bank refund as paid?');"
                    class="d-inline">
                @csrf
                <button class="btn btn-sm btn-success">
                  ✔ Mark Paid
                </button>
              </form>
            </td>
          </tr>
        @endforeach

        {{-- Team refunds appended below --}}
        @if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count())
          <tr>
            <td colspan="9"><strong>Team Refunds</strong></td>
          </tr>
          @foreach($pendingTeamRefunds as $t)
            <tr>
              <td>
                <strong>{{ optional($t->event)->name ?? 'Event #' . ($t->event_id ?? '') }}</strong><br>
                <small class="text-muted">Team ID: {{ $t->team_id }}</small>
              </td>
              <td>{{ optional($t->player)->name ?? 'Player #' . ($t->player_id ?? '') }}</td>
              <td>
                {{ $t->user->name ?? '—' }}<br>
                <small class="text-muted">{{ $t->user->email ?? '' }}</small>
              </td>
              <td><code>{{ $t->payfast_pf_payment_id ?? '—' }}</code></td>
              <td>R{{ number_format($t->refund_gross, 2) }}</td>
              <td class="text-danger">R{{ number_format($t->refund_fee, 2) }}</td>
              <td class="fw-bold text-success">R{{ number_format($t->refund_net, 2) }}</td>
              <td>{{ optional($t->updated_at)->format('Y-m-d') }}</td>
              <td class="text-end">
                <form method="POST" action="{{ route('bank.complete.team', $t) }}" onsubmit="return confirm('Mark this team bank refund as paid?');">
                  @csrf
                  <button class="btn btn-sm btn-success">✔ Mark Paid</button>
                </form>
              </td>
            </tr>
          @endforeach
        @endif

        </tbody>
      </table>
    </div>
  </div>

  @endif

  {{-- Completed Refunds --}}
  @if(!empty($completedRefunds) && $completedRefunds->count())
    <h4 class="mt-4 mb-3">Completed Bank Refunds</h4>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>Event</th>
              <th>Player(s)</th>
              <th>User</th>
              <th>PayFast ID</th>
              <th>Net Refunded</th>
              <th>Refunded At</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($completedRefunds as $reg)
              <tr>
                <td>
                  <strong>{{ optional($reg->categoryEvent?->event)->name ?? '—' }}</strong><br>
                  <small class="text-muted">{{ $reg->categoryEvent->name ?? '' }}</small>
                </td>
                <td>{{ $reg->display_name }}</td>
                <td>
                  {{ $reg->user->name ?? '—' }}<br>
                  <small class="text-muted">{{ $reg->user->email ?? '' }}</small>
                </td>
                <td><code>{{ $reg->pf_transaction_id ?? '—' }}</code></td>
                <td class="fw-bold text-success">R{{ number_format($reg->refund_net, 2) }}</td>
                <td>{{ optional($reg->refunded_at)->format('Y-m-d') }}</td>
                <td class="text-end">
                  @if($reg->pf_transaction_id)
                    <a href="{{ route('admin.refunds.bank.payfast-query', $reg) }}"
                       class="btn btn-sm btn-outline-secondary"
                       title="Query PayFast refund status">
                      🔍 PF Status
                    </a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

</div>
@endsection
