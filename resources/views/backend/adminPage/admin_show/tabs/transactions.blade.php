<div class="table-responsive mb-5">
  <a href="{{ route('transactions.pdf', $event->id) }}" target="_blank" class="btn btn-sm btn-outline-danger mb-3">
    Download PDF
  </a>
  @include('backend.adminPage._includes.transactions_table', ['transactions' => $transactions])
</div>
