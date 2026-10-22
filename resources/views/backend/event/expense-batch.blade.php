@extends('layouts.backend')
@section('title', $event->name.' – Edit expenses')
@section('page-style')@include('backend.event.partials.cost-editor-style')@endsection
@section('content')
<div class="container-xl">
  @include('backend.event.partials.header', ['eventWorkspaceActive' => 'finances', 'eventWorkspaceIcon' => 'ti-report-money', 'eventWorkspaceSubtitle' => 'Event expenses'])
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h2 class="h4 mb-1">Edit expenses</h2><p class="text-muted mb-0">Update costs across all expense categories and save them together.</p></div><a class="btn btn-outline-secondary" href="{{ route('admin.events.finances', $event) }}">Back to finances</a></div>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Check the expense details. No changes from this save were recorded.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <p class="text-muted">Enter an amount, or use quantity × unit cost. Payee is who receives the money; paid by is the director who needs reimbursement. Use individual expenses in Finances for receipts, approvals and reimbursements.</p>
  @php($editable = $expenses->filter(fn($expense) => !$expense->approved_at && !$expense->reimbursed_at)->map(fn($expense) => $expense->toArray() + ['version' => \App\Domain\Finance\Services\EventExpenseBatchService::version($expense)])->values()->all())
  <form method="POST" action="{{ route('admin.events.expenses.update', $event) }}" id="expense-batch-form">
    @csrf @method('PUT')
    <input type="hidden" name="page" value="{{ $expenses->currentPage() }}"><input type="hidden" name="lines" value="">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3"><h3 class="h5 mb-0">Expense list</h3><button class="btn btn-outline-primary" type="button" id="add-expense-row">Add expense</button></div>
    <div id="expense-batch-rows">
      @foreach(old('lines', $editable) ?: [] as $index => $line)@include('backend.event.partials.expense-batch-row', ['index' => $index, 'line' => $line])@endforeach
    </div>
    <p id="expense-batch-empty" class="text-muted" @if(count(old('lines', $editable) ?: [])) hidden @endif>No editable expenses on this page. Add an expense to get started.</p>
    <div class="cost-editor-save"><span>List total: <strong id="expense-batch-total" aria-live="polite">R 0.00</strong></span><button class="btn btn-primary" type="submit">Save expenses</button></div>
    <input type="hidden" name="form_complete" value="1">
  </form>
  @if($expenses->contains(fn($expense) => $expense->approved_at || $expense->reimbursed_at))
    <details class="card mt-3"><summary class="card-header">Approved or reimbursed expenses</summary><div class="card-body"><p class="text-muted">These entries are protected in this editor. Open Finances to review their status.</p>@foreach($expenses->filter(fn($expense) => $expense->approved_at || $expense->reimbursed_at) as $expense)<div class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom"><span>{{ $expense->description ?: ($expenseTypes[$expense->expense_type] ?? $expense->expense_type) }} <small class="text-muted">{{ $expense->reimbursed_at ? 'Reimbursed' : 'Approved' }}</small></span><strong>R {{ number_format($expense->calculatedAmount(),2) }}</strong></div>@endforeach</div></details>
  @endif
  <div class="mt-3">{{ $expenses->links() }}</div><p class="text-muted small">Save before changing pages. Saving updates only the submitted expenses; other entries remain as they are.</p>
</div>
<template id="expense-batch-row-template">@include('backend.event.partials.expense-batch-row', ['index' => '__INDEX__', 'line' => []])</template>
@endsection
@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const list = document.getElementById('expense-batch-rows');
  let dirty = false;
  const hundredths = value => {
    if (value.startsWith('.')) value = '0' + value;
    if (!/^\d+(\.\d{1,2})?$/.test(value)) return 0;
    const [whole, fraction = ''] = value.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
  };
  let next = Math.max(-1, ...Array.from(list.querySelectorAll('[name$="[description]"]'), input => Number(input.name.match(/lines\[(\d+)\]/)?.[1] ?? -1))) + 1;
  const update = () => {
    let total = 0;
    list.querySelectorAll('[data-expense-row]').forEach(row => {
      const quantity = row.querySelector('[name$="[quantity]"]');
      const price = row.querySelector('[name$="[unit_price]"]');
      const amount = row.querySelector('[name$="[amount]"]');
      amount.readOnly = quantity.value !== '' && price.value !== '';
      if (amount.readOnly) amount.value = (Math.floor((hundredths(quantity.value) * hundredths(price.value) + 50) / 100) / 100).toFixed(2);
      total += hundredths(amount.value);
    });
    document.getElementById('expense-batch-total').textContent = 'R ' + (total / 100).toLocaleString('en-ZA', {minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('expense-batch-empty').hidden = list.children.length > 0;
    document.getElementById('add-expense-row').disabled = list.children.length >= 70;
  };
  document.getElementById('add-expense-row').addEventListener('click', () => {
    list.insertAdjacentHTML('beforeend', document.getElementById('expense-batch-row-template').innerHTML.replaceAll('__INDEX__', next++));
    const row = list.lastElementChild;
    row.querySelector('[name$="[batch_key]"]').value = crypto.randomUUID();
    dirty = true; update(); row.querySelector('[name$="[description]"]').focus();
  });
  list.addEventListener('input', () => { dirty = true; update(); });
  list.addEventListener('change', () => { dirty = true; update(); });
  list.addEventListener('click', event => { const button = event.target.closest('[data-remove-expense-row]'); if(button) { button.closest('[data-expense-row]').remove(); dirty = true; update(); } });
  document.getElementById('expense-batch-form').addEventListener('submit', () => { dirty = false; document.querySelector('#expense-batch-form button[type="submit"]').disabled = true; });
  window.addEventListener('pageshow', () => { document.querySelector('#expense-batch-form button[type="submit"]').disabled = false; });
  window.addEventListener('beforeunload', event => { if(dirty) { event.preventDefault(); event.returnValue = ''; } });
  update();
});
</script>
@endsection
