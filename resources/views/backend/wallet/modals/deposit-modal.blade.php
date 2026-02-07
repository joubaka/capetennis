<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1" aria-labelledby="depositModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="{{ route('wallet.transaction.store', $user->id) }}" method="POST" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title" id="depositModalLabel">Deposit Funds</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label for="deposit-amount" class="form-label">Amount</label>
          <input type="number" step="0.01" name="amount" class="form-control" required>
        </div>
        <input type="hidden" name="type" value="credit">
        <div class="mb-3">
          <label class="form-label">Reference (optional)</label>
          <input type="text" name="reference" class="form-control" placeholder="e.g. Admin top-up">
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Deposit</button>
      </div>
    </form>
  </div>
</div>
