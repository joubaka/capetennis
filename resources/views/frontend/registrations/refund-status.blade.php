@extends('layouts/layoutMaster')

@section('title', 'My Refunds')

@section('content')
<div class="container mt-4" style="max-width: 860px;">

  <h4 class="mb-4">My Withdrawal & Refund Status</h4>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if($registrations->isEmpty())
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="ti ti-inbox fs-2 mb-2 d-block"></i>
        No withdrawals found.
      </div>
    </div>
  @else
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th>Event</th>
              <th>Category</th>
              <th>Player(s)</th>
              <th>Withdrawn</th>
              <th>Refund Method</th>
              <th>Refund Status</th>
              <th>Amount</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($registrations as $reg)
              @php
                $eventName    = optional($reg->categoryEvent?->event)->name ?? '—';
                $categoryName = optional($reg->categoryEvent?->category)->name ?? '—';
                $playerNames  = $reg->players->map(fn($p) => trim($p->name . ' ' . $p->surname))->implode(', ') ?: '—';
                $statusBadge  = match($reg->refund_status) {
                  'completed'   => ['bg-success',  'Completed'],
                  'pending'     => ['bg-warning text-dark', 'Pending'],
                  'not_refunded' => ['bg-secondary', 'No Refund'],
                  default        => ['bg-light text-muted border', $reg->refund_status ?? 'Not set'],
                };
              @endphp
              <tr>
                <td>{{ $eventName }}</td>
                <td>{{ $categoryName }}</td>
                <td>{{ $playerNames }}</td>
                <td>{{ $reg->withdrawn_at?->format('d M Y') ?? '—' }}</td>
                <td>{{ $reg->refund_method ? ucfirst($reg->refund_method) : '—' }}</td>
                <td>
                  <span class="badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                </td>
                <td>
                  @if($reg->refund_net > 0)
                    R{{ number_format($reg->refund_net, 2) }}
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if($reg->canRequestRefund())
                    <a href="{{ route('registrations.refund.choose', $reg) }}"
                       class="btn btn-sm btn-primary">
                      Choose Refund
                    </a>
                  @elseif($reg->refund_status === 'pending' && $reg->refund_method === 'bank')
                    <span class="text-muted small">Awaiting bank transfer</span>
                  @elseif($reg->refund_status === 'completed')
                    <span class="text-success small">
                      <i class="ti ti-check me-1"></i>Done
                      @if($reg->refunded_at)
                        · {{ $reg->refunded_at->format('d M Y') }}
                      @endif
                    </span>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <p class="text-muted small mt-3">
      For bank refunds still showing as <strong>Pending</strong>, our team processes these manually. If your refund has not arrived within 5 business days, please contact
      <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a>.
    </p>
  @endif

</div>
@endsection
