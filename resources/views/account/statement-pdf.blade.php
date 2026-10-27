<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Cape Tennis account payment statement</title>
@include('account.statement-styles')
<style>
@page{margin:30px 34px 42px}
body.statement-pdf{font-family:DejaVu Sans,sans-serif;font-size:11px;line-height:1.4}
.statement-pdf .statement-brand{margin-bottom:16px}.statement-pdf .statement-brand td{padding-bottom:12px}
.statement-pdf .statement-brand-logo{width:48px;height:48px}.statement-pdf .statement-brand-name{font-size:22px}.statement-pdf .statement-title{font-size:23px}
.statement-pdf .statement-meta{font-size:11px;margin-bottom:14px}.statement-pdf .statement-meta strong{font-size:14px}
.statement-pdf .statement-summary td{padding:11px 13px}.statement-pdf .statement-summary-label{font-size:10px}.statement-pdf .statement-value{font-size:19px}
.statement-pdf .statement-table{min-width:0;font-size:10px}.statement-pdf .statement-table th{font-size:8px}
.statement-pdf .statement-table th,.statement-pdf .statement-table td{padding:7px 8px}
.statement-pdf .statement-transactions th:nth-child(1){width:14%}.statement-pdf .statement-transactions th:nth-child(2){width:20%}.statement-pdf .statement-transactions th:nth-child(3){width:40%}.statement-pdf .statement-transactions th:nth-child(4),.statement-pdf .statement-transactions th:nth-child(5){width:13%}
.statement-pdf .statement-history-table th:nth-child(1){width:14%}.statement-pdf .statement-history-table th:nth-child(2){width:19%}.statement-pdf .statement-history-table th:nth-child(3){width:37%}.statement-pdf .statement-history-table th:nth-child(4),.statement-pdf .statement-history-table th:nth-child(5){width:15%}
.statement-pdf .statement-table small,.statement-pdf .statement-fee-source,.statement-pdf .statement-reference{font-size:9px}
.statement-pdf .statement-unavailable{font-size:10px}.statement-pdf .statement-caption,.statement-pdf .statement-fee-summary p{font-size:10px}
.statement-pdf .statement-section{margin-top:20px}.statement-pdf .statement-section-title{font-size:14px;page-break-after:avoid}
.statement-pdf .statement-notes{font-size:9px}.statement-pdf .statement-fee-summary{page-break-inside:avoid;padding:9px 12px}
.statement-pdf .entry-detail{margin-bottom:6px}.statement-pdf .statement-notice{font-size:9px}
.statement-pdf .statement-history-section.has-history{page-break-before:always}.statement-pdf .statement-history-intro{page-break-inside:avoid;page-break-after:avoid}
thead{display:table-header-group}tr{page-break-inside:avoid}h2{page-break-after:avoid}
</style></head>
<body class="ct-statement-document statement-pdf">
@include('account.statement-heading')
@include('account.statement-warnings')
<div class="statement-section">
    <h2 class="statement-section-title">Payment transactions</h2>
    <p class="statement-caption">{{ $rows->count() }} known receipt records · All matching payments included.</p>
    @include('account.statement-table', ['statementRows' => $rows])
</div>
<div class="statement-section statement-history-section {{ ($historicalRows ?? collect())->isNotEmpty() ? 'has-history' : '' }}">
    <p class="statement-caption">{{ ($historicalRows ?? collect())->count() }} historical paid records shown separately; excluded from known totals.</p>
    @include('account.statement-history', ['historyRows' => $historicalRows ?? collect()])
</div>
<div class="statement-notes">@include('account.statement-notes')</div>
<p class="statement-footer">Generated {{ $generated_at->format('Y-m-d H:i T') }} · Cape Tennis · Account #{{ $account->id }}</p>
</body></html>
