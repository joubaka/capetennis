@php($reimbursementErrors = $errors ?? new \Illuminate\Support\ViewErrorBag())
<details class="card mb-4" id="expense-reimbursements" @if($reimbursementErrors->any()) open @endif>
  <summary class="card-header"><h5 class="mb-0">Record payment to director</h5></summary>
  <div class="card-body">
    <p>Record money already paid to a director from registration funds for expenses, profit share, or both. This reduces the total still owed without changing event profit or the calculated profit share. Use “Full outstanding balance” to include linked clothing amounts and deductions in one recorded settlement.</p>
    @if($reimbursementErrors->any())
      <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($reimbursementErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    @can('event-finance.manage', $event)
    <form method="POST" action="{{ route('admin.events.expense-reimbursements.store', $event) }}" class="row g-3" data-director-payment-form>
      @csrf
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
      @if(!empty($directorSettlementPreviews))
      <div class="col-12"><label for="director-payment-mode" class="form-label">Payment to record</label>
        <select name="payment_mode" id="director-payment-mode" class="form-select" data-payment-mode>
          <option value="partial" @selected(old('payment_mode', 'partial') === 'partial')>Amount already paid from registration entries</option>
          <option value="full_net" @selected(old('payment_mode') === 'full_net')>Full outstanding balance, including clothing deductions</option>
        </select>
      </div>
      @endif
      <div class="col-md-6"><label for="reimbursement-director" class="form-label">Director</label>
        <select id="reimbursement-director" name="convenor_id" class="form-select" required>
          <option value="">Choose director</option>
          @foreach($recon as $row)<option value="{{ $row['convenor']->id }}" data-settlement="{{ json_encode($directorSettlementPreviews[$row['convenor']->id] ?? null) }}" @selected((string) old('convenor_id') === (string) $row['convenor']->id)>{{ $row['convenor']->user->name ?? 'Unknown' }} — R {{ number_format($row['final_payout'], 2) }} total still owed</option>@endforeach
        </select>
      </div>
      <div class="col-md-6"><label for="reimbursement-amount" class="form-label">Amount already paid (R)</label><input id="reimbursement-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Paid from</label><p class="form-control-plaintext" data-funding-label>Registration entries</p></div>
      <div class="col-12" data-net-settlement hidden>
        <div class="alert alert-info mb-3" data-net-preview aria-live="polite"></div>
        <input type="hidden" name="preview_token" value="{{ old('preview_token') }}" disabled>
        <label for="director-settled-at" class="form-label">Actual payment date and time</label>
        <input id="director-settled-at" name="settled_at" type="datetime-local" value="{{ old('settled_at') }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control mb-3" disabled>
        <label class="form-check-label"><input type="checkbox" name="settlement_confirmed" value="1" class="form-check-input me-2" disabled> I confirm the net amount was already paid and the clothing deductions were withheld from this settlement.</label>
        <small class="d-block text-muted mt-2">This records the payment and reconciles the linked balances. It does not transfer money. Pending clothing refunds remain reserved.</small>
      </div>
      <div class="col-md-6"><label for="reimbursement-date" class="form-label">Payment date (optional)</label><input id="reimbursement-date" name="paid_at" type="date" max="{{ now()->toDateString() }}" value="{{ old('paid_at') }}" class="form-control"><small class="text-muted">Leave blank if the payment date is unknown.</small></div>
      <div class="col-md-6"><label for="reimbursement-reference" class="form-label" data-reference-label>Payment reference (optional)</label><input id="reimbursement-reference" name="reference" maxlength="150" value="{{ old('reference') }}" class="form-control"></div>
      <div class="col-md-6"><label for="reimbursement-note" class="form-label">Note (optional)</label><input id="reimbursement-note" name="description" maxlength="255" value="{{ old('description') }}" class="form-control"></div>
      <div class="col-12"><button class="btn btn-primary" type="submit" data-payment-submit>Record payment</button></div>
    </form>
    @endcan
    @if($reimbursementPayments->isNotEmpty())
      <h6 class="mt-4">Recorded director payments</h6>
      <div class="table-responsive"><table class="table"><thead><tr><th>Director</th><th>Purpose</th><th>Amount</th><th>Paid from</th><th>Payment date</th><th>Reference / note</th><th>Recorded by</th></tr></thead><tbody>
      @foreach($reimbursementPayments as $payment)
        <tr><td>{{ $payment->recipient_name }}</td><td>{{ $payment->settlement_details ? 'Full net settlement' : ($payment->purpose === 'expense_reimbursement' ? 'Expense reimbursement' : 'Expenses / profit share') }}</td><td>R {{ number_format($payment->settlement_details ? $payment->settlement_details['cash_cents'] / 100 : (float) $payment->amount, 2) }}@if($payment->settlement_details)<small class="d-block text-muted">Registration settled: R {{ number_format((float) $payment->amount, 2) }}; clothing adjustment: R {{ number_format(($payment->settlement_details['cash_cents'] - $payment->settlement_details['registration_cents']) / 100, 2) }}</small>@endif</td><td>{{ $payment->settlement_details ? 'Registration and linked clothing' : 'Registration entries' }}</td><td>{{ $payment->paid_at?->format('d M Y') ?? 'Unknown' }}</td><td>{{ $payment->reference }} {{ $payment->description }}</td><td>{{ $payment->recorded_by_name ?? $payment->paidByUser?->name ?? 'Unknown' }}<small class="d-block">{{ $payment->created_at->format('d M Y H:i') }}</small></td></tr>
      @endforeach
      </tbody></table></div>
    @endif
  </div>
</details>

@once
<script src="{{ asset('js/event-director-payments.js') }}" defer></script>
@endonce
