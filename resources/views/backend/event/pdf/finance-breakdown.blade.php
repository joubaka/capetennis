<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Income and expense breakdown</title>
<style>
@page { margin: 30px; } body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #26374a; }
h1 { font-size: 19px; margin-bottom: 5px; } h2 { font-size: 13px; margin-top: 22px; border-bottom: 1px solid #b7d6d0; padding-bottom: 6px; }
table { width: 100%; border-collapse: collapse; margin: 8px 0; } th { background: #edf4f3; text-align: left; } th, td { padding: 7px; border-bottom: 1px solid #e2e7eb; } tr { page-break-inside: avoid; } thead { display: table-header-group; } .amount { text-align: right; white-space: nowrap; } .muted { color: #68798a; } .total { font-weight: bold; } .payee { page-break-inside: avoid; }
</style></head><body>
<h1>{{ $event->name }}</h1><strong>Income and expense breakdown</strong>
<p class="muted">Generated {{ now()->timezone('Africa/Johannesburg')->format('d M Y H:i') }} SAST · Private financial report</p>
<h2>Registration income and expenses</h2>
<table><tbody>
<tr><td>Registration gross income</td><td class="amount">R {{ number_format($totalGross, 2) }}</td></tr>
<tr><td>PayFast fees</td><td class="amount">R {{ number_format($totalPayfastFees, 2) }}</td></tr>
<tr><td>Cape Tennis fees</td><td class="amount">R {{ number_format($totalCapeTennisFees, 2) }}</td></tr>
<tr><td>Completed registration refund adjustments</td><td class="amount">R {{ number_format($registrationRefundImpact, 2) }}</td></tr>
<tr><td>Registration net income (after fees and refunds)</td><td class="amount">R {{ number_format($netRegistrationIncome, 2) }}</td></tr>
<tr><td>Operational expenses</td><td class="amount">R {{ number_format($totalExpenses, 2) }}</td></tr>
<tr class="total"><td>Registration {{ $netProfit < 0 ? 'loss' : 'profit' }}</td><td class="amount">R {{ number_format($netProfit, 2) }}</td></tr>
<tr><td>Registration entries</td><td class="amount">{{ $totalEntries }}</td></tr>
</tbody></table>
<p class="muted">System fees are already deducted from registration income. Clothing and other event income are kept separate from registration profit.</p>
<h2>Operational expense detail</h2>
<table><thead><tr><th>Expense</th><th>Paid by</th><th class="amount">Amount</th></tr></thead><tbody>
@forelse($expenses->reject(fn($expense) => in_array($expense->expense_type, ['payfast', 'cape_tennis_fee'])) as $expense)
<tr><td>{{ $expenseTypes[$expense->expense_type] ?? $expense->expense_type }}@if($expense->description)<br><span class="muted">{{ $expense->description }}</span>@endif</td><td>{{ $expense->paidByConvenor?->user?->name ?? $expense->convenor_name ?? '—' }}</td><td class="amount">R {{ number_format($expense->calculatedAmount(), 2) }}</td></tr>
@empty<tr><td colspan="3">No operational expenses recorded.</td></tr>@endforelse
</tbody></table>
@if($incomeItems->isNotEmpty())
<h2>Other event income (separate)</h2><table><thead><tr><th>Income</th><th class="amount">Amount</th></tr></thead><tbody>
@foreach($incomeItems as $item)<tr><td>{{ $item->label }}</td><td class="amount">R {{ number_format($item->calculatedTotal(), 2) }}</td></tr>@endforeach
<tr class="total"><td>Total other income</td><td class="amount">R {{ number_format($totalIncomeItems, 2) }}</td></tr></tbody></table>
@endif
@if($recon->isNotEmpty())<h2>Convenor payout breakdown</h2>
<p class="muted">Projected amounts only. Payments and recoveries must still be recorded when settled. Clothing adjustments do not change registration profit.</p>
@foreach($recon as $row)
@php($projection = ($convenorPayoutProjection['cards'] ?? collect())->get($row['convenor']->id))
<div class="payee"><h3>{{ $row['convenor']->user?->name ?? 'Unknown' }}</h3><table><tbody>
<tr><td>Expenses paid out</td><td class="amount">R {{ number_format($row['total_paid'], 2) }}</td></tr>
<tr><td>Already reimbursed</td><td class="amount">R {{ number_format($row['reimbursed'], 2) }}</td></tr>
<tr><td>Expenses still owed</td><td class="amount">R {{ number_format($row['owed_back'] - $row['reimbursed'], 2) }}</td></tr>
<tr><td>Profit share ({{ number_format($row['profit_share_pct'], 1) }}%)</td><td class="amount">R {{ number_format($row['profit_share_amount'], 2) }}</td></tr>
<tr class="total"><td>Registration subtotal</td><td class="amount">R {{ number_format($row['final_payout'], 2) }}</td></tr>
@foreach($projection['regions'] ?? collect() as $region)
<tr><td>Clothing — {{ $region['region_name'] }} ({{ $region['contribution_cents'] < 0 ? 'proposed recovery' : 'still payable after pending refund reserve' }})</td><td class="amount">R {{ number_format($region['contribution_cents'] / 100, 2) }}</td></tr>
@endforeach
<tr class="total"><td>Projected total (negative means recover)</td><td class="amount">R {{ number_format($projection ? $projection['combined_cents'] / 100 : $row['final_payout'], 2) }}</td></tr>
</tbody></table></div>
@endforeach @endif
@if(($convenorPayoutProjection['unmatched_regions'] ?? collect())->isNotEmpty())
<h2>Regional clothing kept separate</h2><table><thead><tr><th>Region</th><th>Note</th><th class="amount">Projected amount</th></tr></thead><tbody>
@foreach($convenorPayoutProjection['unmatched_regions'] as $region)<tr><td>{{ $region['region_name'] }}</td><td>{{ $region['reason'] }}</td><td class="amount">R {{ number_format($region['projected_cents'] / 100, 2) }}</td></tr>@endforeach
</tbody></table>@endif
@if($venueEntrySummary->isNotEmpty())<h2>Venue entry summary</h2><table><thead><tr><th>Venue</th><th class="amount">Entries</th></tr></thead><tbody>@foreach($venueEntrySummary as $venue)<tr><td>{{ $venue->name }}</td><td class="amount">{{ $venue->entry_count }}</td></tr>@endforeach</tbody></table>@endif
</body></html>
