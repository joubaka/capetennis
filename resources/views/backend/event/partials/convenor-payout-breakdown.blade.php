  @if($recon->count())
  <section class="card mb-4 no-print" id="finance-convenor-payouts" aria-labelledby="convenor-payout-title">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h5 class="mb-0" id="convenor-payout-title"><i class="ti ti-cash me-2"></i>Convenor Payout Breakdown</h5>
      <small class="text-muted">Registration {{ $netProfit >= 0 ? 'profit' : 'loss' }}: <strong class="{{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">R {{ number_format(abs($netProfit), 2) }}</strong></small>
    </div>
    <div class="card-body">
      <p class="small text-muted mb-3">Clothing balances adjust the projected payment below; registration profit stays unchanged. Payments and recoveries must still be recorded when settled.</p>
      <div class="row g-3">
        @foreach($recon as $row)
          @php
            $outstanding = $row['owed_back'] - $row['reimbursed'];
            $projection = ($convenorPayoutProjection['cards'] ?? collect())->get($row['convenor']->id);
            $combinedPayout = $projection ? $projection['combined_cents'] / 100 : $row['final_payout'];
            $clothingRegions = $projection['regions'] ?? collect();
          @endphp
          <div class="col-md-6 col-xl-4">
            <div class="border rounded p-3 h-100 bg-light">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <div class="fw-bold">{{ $row['convenor']->user->name ?? 'Unknown' }}</div>
                  <span class="badge {{ $row['convenor']->isHoof() ? 'bg-warning text-dark' : 'bg-label-secondary' }} mt-1">
                    {{ $row['convenor']->isHoof() ? 'Head Director' : ($row['convenor']->isHulp() ? 'Assist Director' : ucfirst($row['convenor']->role)) }}
                  </span>
                </div>
                <span class="badge {{ $combinedPayout < 0 ? 'bg-danger' : 'bg-primary' }} fs-6 flex-shrink-0 ms-2">
                  R {{ number_format(abs($combinedPayout), 2) }}
                </span>
              </div>
              <ul class="list-unstyled mb-0 small text-muted">
                <li class="d-flex justify-content-between gap-2">
                  <span>Expenses paid out:</span>
                  <span class="fw-semibold text-body text-nowrap flex-shrink-0">R {{ number_format($row['total_paid'], 2) }}</span>
                </li>
                @if($row['reimbursed'] > 0)
                <li class="d-flex justify-content-between gap-2">
                  <span>Already reimbursed:</span>
                  <span class="text-success text-nowrap flex-shrink-0">−R {{ number_format($row['reimbursed'], 2) }}</span>
                </li>
                @endif
                @if($outstanding > 0)
                <li class="d-flex justify-content-between gap-2">
                  <span>Expenses still owed:</span>
                  <span class="text-danger fw-semibold text-nowrap flex-shrink-0">R {{ number_format($outstanding, 2) }}</span>
                </li>
                @endif
                @if($row['profit_share_pct'] > 0)
                <li class="d-flex justify-content-between gap-2 mt-1 pt-1 border-top">
                  <span>Profit share ({{ number_format($row['profit_share_pct'], 1) }}% of R {{ number_format($netProfit, 2) }}):</span>
                  <span class="text-info fw-semibold text-nowrap flex-shrink-0">R {{ number_format($row['profit_share_amount'], 2) }}</span>
                </li>
                @endif
                <li class="d-flex justify-content-between gap-2 mt-1 pt-1 border-top fw-semibold text-body">
                  <span>Registration subtotal:</span>
                  <span class="text-nowrap flex-shrink-0">R {{ number_format($row['final_payout'], 2) }}</span>
                </li>
              </ul>
              @foreach($clothingRegions as $clothing)
                <div class="mt-3 pt-2 border-top small">
                  <div class="d-flex justify-content-between gap-2 fw-semibold">
                    <span>Clothing — {{ $clothing['region_name'] }}</span>
                    <span class="text-nowrap flex-shrink-0 {{ $clothing['contribution_cents'] < 0 ? 'text-danger' : 'text-primary' }}">{{ $clothing['contribution_cents'] < 0 ? '−' : '+' }}R {{ number_format(abs($clothing['contribution_cents']) / 100, 2) }}</span>
                  </div>
                  <small class="d-block {{ $clothing['contribution_cents'] < 0 ? 'text-danger' : 'text-muted' }}">{{ $clothing['contribution_cents'] < 0 ? 'Proposed recovery against this payout' : ($clothing['contribution_cents'] > 0 ? 'Clothing still payable, after pending refund reserve' : 'No clothing amount added or recovered') }}</small>
                  <a class="d-inline-block mt-1" href="{{ route('admin.events.finances', ['event' => $event->id, 'tab' => 'clothing']) }}#finance-clothing-region-{{ $clothing['region_id'] }}">View clothing breakdown<span class="visually-hidden"> for {{ $clothing['region_name'] }}</span></a>
                  @if($clothing['confirmed_recipient_link'])
                    <small class="d-block text-muted">Confirmed same-person account link · {{ $clothing['confirmed_recipient_link']->source_name }} · {{ $clothing['confirmed_recipient_link']->confirmed_at->format('d M Y') }}</small>
                  @endif
                  <ul class="list-unstyled text-muted mt-2 mb-0">
                    <li>Already paid out: R {{ number_format($clothing['payout_cents'] / 100, 2) }}</li>
                    @if($clothing['completed_refund_cents'] > 0)
                      <li>
                        Completed refunds: R {{ number_format($clothing['completed_refund_cents'] / 100, 2) }} · {{ $clothing['refunded_line_count'] }} refunded {{ $clothing['refunded_line_count'] === 1 ? 'line' : 'lines' }}
                        @if($clothing['recorded_quantity'] > 0)
                          · {{ $clothing['recorded_quantity'] }} {{ $clothing['recorded_quantity'] === 1 ? 'unit' : 'units' }}
                        @endif
                      </li>
                      @if($clothing['partial_amount_lines'] > 0)
                        <li>Includes {{ $clothing['partial_amount_lines'] }} partial amount {{ $clothing['partial_amount_lines'] === 1 ? 'line' : 'lines' }} with no recorded unit quantity.</li>
                      @endif
                    @endif
                    @if($clothing['shortfall_cents'] > 0)
                      <li>Original payout difference: R {{ number_format($clothing['shortfall_cents'] / 100, 2) }}</li>
                    @endif
                    @if($clothing['recovery_cents'] > 0)
                      <li>Already recovered: R {{ number_format($clothing['recovery_cents'] / 100, 2) }}</li>
                    @endif
                    @if($clothing['offset_cents'] !== 0)
                      <li>Recorded agreed offsets: {{ $clothing['offset_cents'] < 0 ? '−' : '+' }}R {{ number_format(abs($clothing['offset_cents']) / 100, 2) }}</li>
                    @endif
                    @if($clothing['pending_cents'] > 0)
                      <li>Pending refund liability: R {{ number_format($clothing['pending_cents'] / 100, 2) }} (not a completed recovery).</li>
                    @endif
                  </ul>
                  @foreach($clothing['refunds'] as $clothingRefund)
                    <div class="border rounded bg-body p-2 mt-2 text-break">
                      <div class="d-flex align-items-baseline gap-2 fw-semibold text-body">
                        <span aria-hidden="true">&bull;</span>
                        <span>{{ $clothingRefund['player_name'] }}</span>
                      </div>
                      <div class="ps-3">
                      <small class="d-block text-muted mt-1">
                        {{ $clothingRefund['refunded_at']?->format('d M Y') }}
                        @if($clothingRefund['after_payout'])
                          · After recorded payout
                        @endif
                        @if($clothingRefund['after_deadline'])
                          · After recorded deadline
                        @endif
                      </small>
                      @foreach($clothingRefund['items'] as $refundedItem)
                        <small class="d-block mt-1">{{ $refundedItem['quantity'] > 0 ? $refundedItem['quantity'].' × ' : 'Partial amount: ' }}{{ $refundedItem['name'] }}{{ $refundedItem['size'] ? ' · '.$refundedItem['size'] : '' }} — <span class="fw-semibold text-nowrap">R {{ number_format($refundedItem['amount_cents'] / 100, 2) }}</span></small>
                      @endforeach
                      </div>
                    </div>
                  @endforeach
                </div>
              @endforeach
              <div class="d-flex justify-content-between gap-2 mt-3 pt-2 border-top fw-bold {{ $combinedPayout < 0 ? 'text-danger' : 'text-body' }}">
                <span>{{ $combinedPayout < 0 ? 'Projected amount to recover:' : 'Projected total to pay:' }}</span>
                <span class="text-nowrap flex-shrink-0">R {{ number_format(abs($combinedPayout), 2) }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>
      @if(($convenorPayoutProjection['unmatched_regions'] ?? collect())->isNotEmpty())
        <div class="alert alert-warning mt-3 mb-0" role="status">
          <strong>Regional clothing kept separate from these director totals</strong>
          <ul class="mb-0 mt-2">
            @foreach($convenorPayoutProjection['unmatched_regions'] as $unmatchedClothing)
              <li>
                {{ $unmatchedClothing['region_name'] }}{{ $unmatchedClothing['manager_name'] ? ' — '.$unmatchedClothing['manager_name'] : '' }}: R {{ number_format(abs($unmatchedClothing['projected_cents']) / 100, 2) }} {{ $unmatchedClothing['balance_cents'] < 0 ? 'to recover or offset' : 'available to pay after pending refunds' }}. {{ $unmatchedClothing['reason'] }}
                @if($unmatchedClothing['pending_cents'] > 0)
                  Pending refund liability: R {{ number_format($unmatchedClothing['pending_cents'] / 100, 2) }}.
                @endif
              </li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>
  </section>
  @endif

