<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Cape Tennis account payment statement</title>
<style>
@page { margin: 30px; } body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #25334a; }
h1 { font-size: 21px; margin-bottom: 8px; } table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { background: #edf2f6; text-align: left; } th, td { padding: 7px; border-bottom: 1px solid #dce3ea; vertical-align: top; overflow-wrap: break-word; }
th:nth-child(1) { width: 14%; } th:nth-child(2) { width: 22%; } th:nth-child(3) { width: 38%; } th:nth-child(4), th:nth-child(5) { width: 13%; }
thead { display: table-header-group; } tr { page-break-inside: avoid; } .text-end { text-align: right; } .entry-detail { margin-bottom: 6px; } .notes { font-size: 8px; margin-top: 16px; color: #526075; } .totals { margin: 16px 0; font-size: 12px; }
</style></head><body>
<h1>Cape Tennis</h1><h2>Account payment statement</h2>
@include('account.statement-warnings')
<p>Payments included: {{ ($category ?? 'all') === 'all' ? 'Entry fees and clothing' : ucfirst($category) }}</p>
@if($player)<p><strong>Full matching checkout amounts include all players in shared checkouts. These are not player-only expenses.</strong></p>@endif
<p><strong>{{ $account->name }}</strong> · {{ $account->email }} · Account #{{ $account->id }}<br>
Recorded period: {{ $from }} to {{ $to }}@if($player) · Player search: {{ $player }}@endif<br>Generated {{ $generated_at->format('Y-m-d H:i T') }} · {{ $rows->count() }} records</p>
<p class="totals">Payments: <strong>R {{ number_format($paid / 100, 2) }}</strong> &nbsp; Completed refunds: <strong>R {{ number_format($refunded / 100, 2) }}</strong> &nbsp; Net payments: <strong>R {{ number_format($net / 100, 2) }}</strong></p>
@include('account.statement-table', ['statementRows' => $rows])
<div class="notes">@include('account.statement-notes')</div>
</body></html>
