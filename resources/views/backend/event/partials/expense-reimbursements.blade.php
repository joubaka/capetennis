@php($reimbursementErrors = $errors ?? new \Illuminate\Support\ViewErrorBag())
<details class="card mb-4" id="expense-reimbursements" @if($reimbursementErrors->any()) open @endif>
  <summary class="card-header"><h5 class="mb-0">Record reimbursement already paid</h5></summary>
  <div class="card-body">
    <p>Record a payment already made to a director for event expenses. This reduces expenses still owed, without changing event profit or the director’s profit share.</p>
    @if($reimbursementErrors->any())
      <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($reimbursementErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    @can('event-finance.manage', $event)
    <form method="POST" action="{{ route('admin.events.expense-reimbursements.store', $event) }}" class="row g-3">
      @csrf
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
      <div class="col-md-6"><label for="reimbursement-director" class="form-label">Director</label>
        <select id="reimbursement-director" name="convenor_id" class="form-select" required>
          <option value="">Choose director</option>
          @foreach($recon as $row)<option value="{{ $row['convenor']->id }}" @selected((string) old('convenor_id') === (string) $row['convenor']->id)>{{ $row['convenor']->user->name ?? 'Unknown' }} — R {{ number_format($row['owed_back'] - $row['reimbursed'], 2) }} expenses outstanding</option>@endforeach
        </select>
      </div>
      <div class="col-md-6"><label for="reimbursement-amount" class="form-label">Amount already paid (R)</label><input id="reimbursement-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" value="{{ old('amount') }}" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Paid from</label><p class="form-control-plaintext">Registration entries</p></div>
      <div class="col-md-6"><label for="reimbursement-date" class="form-label">Payment date (optional)</label><input id="reimbursement-date" name="paid_at" type="date" max="{{ now()->toDateString() }}" value="{{ old('paid_at') }}" class="form-control"><small class="text-muted">Leave blank if the payment date is unknown.</small></div>
      <div class="col-md-6"><label for="reimbursement-reference" class="form-label">Payment reference (optional)</label><input id="reimbursement-reference" name="reference" maxlength="150" value="{{ old('reference') }}" class="form-control"></div>
      <div class="col-md-6"><label for="reimbursement-note" class="form-label">Note (optional)</label><input id="reimbursement-note" name="description" maxlength="255" value="{{ old('description') }}" class="form-control"></div>
      <div class="col-12"><button class="btn btn-primary" type="submit">Record reimbursement</button></div>
    </form>
    @endcan
    @if($reimbursementPayments->isNotEmpty())
      <h6 class="mt-4">Recorded expense reimbursements</h6>
      <div class="table-responsive"><table class="table"><thead><tr><th>Director</th><th>Amount</th><th>Paid from</th><th>Payment date</th><th>Reference / note</th><th>Recorded by</th></tr></thead><tbody>
      @foreach($reimbursementPayments as $payment)
        <tr><td>{{ $payment->recipient_name }}</td><td>R {{ number_format((float) $payment->amount, 2) }}</td><td>Registration entries</td><td>{{ $payment->paid_at?->format('d M Y') ?? 'Unknown' }}</td><td>{{ $payment->reference }} {{ $payment->description }}</td><td>{{ $payment->recorded_by_name ?? $payment->paidByUser?->name ?? 'Unknown' }}<small class="d-block">{{ $payment->created_at->format('d M Y H:i') }}</small></td></tr>
      @endforeach
      </tbody></table></div>
    @endif
  </div>
</details>
