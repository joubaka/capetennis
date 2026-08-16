@extends('layouts/layoutMaster')

@section('title', 'Bank Refunds')

@section('content')
<div class="container-xl">

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h4 class="mb-1">Refund Operations</h4>
      <p class="text-muted mb-0">Process pending refunds and keep a clear audit trail. “Processed” records an action in Cape Tennis; use PayFast Status to verify provider settlement.</p>
    </div>
    <div class="d-flex gap-2">
      <span class="badge bg-label-warning">{{ $refunds->total() + $pendingTeamRefunds->count() }} pending</span>
      <span class="badge bg-label-success">{{ $completedRefunds->total() + $completedTeamRefunds->count() }} processed</span>
      <span class="badge bg-label-secondary">{{ $waivedRefunds->total() + $waivedTeamRefunds->count() }} waived</span>
    </div>
  </div>

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
      <i class="ti ti-circle-check me-1"></i>No pending refunds require action.
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

            <td>
              <code>{{ $reg->pf_transaction_id ?? '—' }}</code>
              @if($reg->pf_transaction_id && $reg->refund_account_name)
                <br><small class="text-success"><i class="ti ti-building-bank"></i> Bank details ✓</small>
              @elseif($reg->pf_transaction_id)
                <br><small class="text-warning"><i class="ti ti-alert-triangle"></i> No bank details</small>
              @endif
            </td>

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
                {{-- Request bank details from player --}}
                <form method="POST"
                      action="{{ route('admin.refunds.bank.request-bank-details', $reg) }}"
                      onsubmit="return confirm('Send bank details request email to {{ $reg->user->email ?? 'the player' }}?');"
                      class="d-inline me-1">
                  @csrf
                  <button class="btn btn-sm btn-outline-info" title="Email player to submit bank details">
                    ✉ Bank Details
                  </button>
                </form>
                <form method="POST"
                      action="{{ route('admin.refunds.bank.complete', $reg) }}"
                      onsubmit="return confirm('Process PayFast refund of R{{ number_format($reg->refund_net, 2) }} for {{ $reg->display_name }}? This will submit the refund to PayFast.');"
                      class="d-inline me-1">
                  @csrf
                  <button class="btn btn-sm btn-warning text-dark" title="Submit refund via PayFast API">
                    <i class="ti ti-credit-card-refund me-1"></i>Submit to PayFast
                  </button>
                </form>
              @else
                <form method="POST"
                      action="{{ route('admin.refunds.bank.complete', $reg) }}"
                      onsubmit="return confirm('Mark this bank refund as manually paid?');"
                      class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-success">
                    <i class="ti ti-check me-1"></i>Record manual payment
                  </button>
                </form>
              @endif
              <button type="button"
                      class="btn btn-sm btn-outline-danger js-waive-refund"
                      data-waive-url="{{ route('admin.refunds.bank.waive', $reg) }}"
                      data-refund-name="{{ $reg->display_name }}"
                      title="Close without paying">
                <i class="ti ti-ban me-1"></i>Waive
              </button>
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
                @if($t->payfast_pf_payment_id)
                  <form method="POST" action="{{ route('admin.refunds.bank.complete.team', $t) }}" onsubmit="return confirm('Process PayFast refund of R{{ number_format($t->refund_net, 2) }}? This will submit to PayFast.');" class="d-inline me-1">
                    @csrf
                    <button class="btn btn-sm btn-warning text-dark" title="Submit refund via PayFast API">
                    <i class="ti ti-credit-card-refund me-1"></i>Submit to PayFast
                    </button>
                  </form>
                @else
                  <form method="POST" action="{{ route('admin.refunds.bank.complete.team', $t) }}" onsubmit="return confirm('Record this team bank refund as manually paid?');" class="d-inline">
                    @csrf
                    <button class="btn btn-sm btn-success"><i class="ti ti-check me-1"></i>Record manual payment</button>
                  </form>
                @endif
                <button type="button"
                        class="btn btn-sm btn-outline-danger js-waive-refund"
                        data-waive-url="{{ route('admin.refunds.bank.waive.team', $t) }}"
                        data-refund-name="{{ optional($t->player)->name ?? 'Team refund #' . $t->id }}"
                        title="Close without paying">
                  <i class="ti ti-ban me-1"></i>Waive
                </button>
              </td>
            </tr>
          @endforeach
        @endif

        </tbody>
      </table>
    </div>
  </div>
  @if($refunds->hasPages())
    <div class="mt-3">{{ $refunds->links() }}</div>
  @endif

  @endif

  {{-- Completed Refunds --}}
  @if(!empty($completedRefunds) && $completedRefunds->count())
    <h4 class="mt-4 mb-1">Processed Refunds</h4>
    <p class="text-muted">These were recorded as processed by Cape Tennis. PayFast rows should still be checked for their current provider status.</p>
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
              <th>Recorded At</th>
              <th>Status</th>
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
                <td>
                  @if($reg->pf_transaction_id)
                    <span class="badge bg-label-info">Submitted to PayFast</span>
                  @else
                    <span class="badge bg-label-success">Manual payment recorded</span>
                  @endif
                </td>
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
    @if($completedRefunds->hasPages())
      <div class="mt-3">{{ $completedRefunds->links() }}</div>
    @endif
  @endif

  @if(($waivedRefunds->count() + $waivedTeamRefunds->count()) > 0)
    <h4 class="mt-4 mb-1">Waived Refunds</h4>
    <p class="text-muted">Closed without payment. These records remain visible for audit purposes.</p>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Event</th>
              <th>Player</th>
              <th>Amount not paid</th>
              <th>Waived at</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            @foreach($waivedRefunds as $reg)
              <tr>
                <td>{{ optional($reg->categoryEvent?->event)->name ?? '—' }}</td>
                <td>{{ $reg->display_name }}</td>
                <td>R{{ number_format($reg->refund_net, 2) }}</td>
                <td>{{ optional($reg->refund_waived_at)->format('Y-m-d H:i') }}</td>
                <td class="text-wrap" style="min-width: 16rem">{{ $reg->refund_waiver_reason }}</td>
              </tr>
            @endforeach
            @foreach($completedTeamRefunds as $order)
              <tr>
                <td>
                  <strong>{{ optional($order->event)->name ?? '—' }}</strong><br>
                  <small class="text-muted">Team refund</small>
                </td>
                <td>{{ optional($order->player)->name ?? 'Team refund #' . $order->id }}</td>
                <td>
                  {{ $order->user->name ?? '—' }}<br>
                  <small class="text-muted">{{ $order->user->email ?? '' }}</small>
                </td>
                <td><code>{{ $order->payfast_pf_payment_id ?? '—' }}</code></td>
                <td class="fw-bold text-success">R{{ number_format($order->refund_net, 2) }}</td>
                <td>{{ optional($order->refunded_at)->format('Y-m-d') }}</td>
                <td>
                  @if($order->payfast_pf_payment_id)
                    <span class="badge bg-label-info">Submitted to PayFast</span>
                  @else
                    <span class="badge bg-label-success">Manual payment recorded</span>
                  @endif
                </td>
                <td></td>
              </tr>
            @endforeach
            @foreach($waivedTeamRefunds as $order)
              <tr>
                <td>{{ optional($order->event)->name ?? '—' }}</td>
                <td>{{ optional($order->player)->name ?? 'Team refund #' . $order->id }}</td>
                <td>R{{ number_format($order->refund_net, 2) }}</td>
                <td>{{ optional($order->refund_waived_at)->format('Y-m-d H:i') }}</td>
                <td class="text-wrap" style="min-width: 16rem">{{ $order->refund_waiver_reason }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @if($waivedRefunds->hasPages())
      <div class="mt-3">{{ $waivedRefunds->links() }}</div>
    @endif
  @endif

  <div class="modal fade" id="waiveRefundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" id="waiveRefundForm" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Waive refund</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <strong>No money will be paid.</strong> This closes the pending refund and keeps it in the audit history.
          </div>
          <p id="waiveRefundName" class="fw-semibold"></p>
          <label for="waiveReason" class="form-label">Reason <span class="text-danger">*</span></label>
          <textarea id="waiveReason" name="reason" class="form-control" rows="3" minlength="5" maxlength="500" required placeholder="Why is this refund being waived?"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="ti ti-ban me-1"></i>Waive without payment</button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modalElement = document.getElementById('waiveRefundModal');
  const form = document.getElementById('waiveRefundForm');
  const name = document.getElementById('waiveRefundName');
  const reason = document.getElementById('waiveReason');

  document.querySelectorAll('.js-waive-refund').forEach(function (button) {
    button.addEventListener('click', function () {
      form.action = button.dataset.waiveUrl;
      name.textContent = button.dataset.refundName;
      reason.value = '';
      bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
  });
});
</script>
@endsection
