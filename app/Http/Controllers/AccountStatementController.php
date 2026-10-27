<?php

namespace App\Http\Controllers;

use App\Services\AccountStatementService;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountStatementController extends Controller
{
    public function __invoke(Request $request, AccountStatementService $service)
    {
        return $this->forAccount($request, $service, $request->user());
    }

    public function forAccount(Request $request, AccountStatementService $service, User $account, bool $adminView = false)
    {
        abort_unless($request->user() && ($request->user()->is($account) || ($adminView && $request->user()->hasRole('super-user'))), 403);
        abort_if($adminView && ! $request->user()->hasRole('super-user'), 403);
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'player' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:pdf,csv'],
            'category' => ['nullable', 'in:all,entry,clothing'],
            'all_time' => ['nullable', 'boolean'],
        ]);
        $from = $input['from'] ?? now()->startOfYear()->toDateString();
        $to = $input['to'] ?? now()->toDateString();
        $allTime = array_key_exists('all_time', $input) ? (bool) $input['all_time'] : ! $request->hasAny(['from', 'to']);
        if (! $allTime && ($to < $from || \Carbon\Carbon::parse($from)->diffInDays($to) > 731)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'Choose a period of up to two years, ending on or after the start date.']);
        }
        $statement = $service->build($account, $from, $to, trim($input['player'] ?? ''), $input['category'] ?? 'all', $allTime);
        $statement['adminView'] = $adminView;
        $statement['statementUrl'] = $adminView ? route('superadmin.account-statements.show', $account) : route('account.statement');
        $filename = 'cape-tennis-account-'.$account->id.'-statement-'.($allTime ? 'all-time' : $from.'-'.$to);
        if (($input['format'] ?? null) === 'pdf') {
            $pdf = Pdf::loadView('account.statement-pdf', $statement)->setPaper('a4', 'landscape');
            $pdf->render();
            $pdf->getDomPDF()->getCanvas()->page_text(730, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8);

            return $pdf->download($filename.'.pdf')->header('Cache-Control', 'private, no-store');
        }
        if (($input['format'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($statement) {
                $out = fopen('php://output', 'w');
                $write = function (array $cells) use ($out) {
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r\n]/', (string) $v) ? "'".$v : $v, $cells), ',', '"', '');
                };
                $write(['Cape Tennis account payment statement', $statement['account']->name, $statement['account']->email]);
                $write(['Category', $statement['category'], 'Player search', $statement['player']]);
                foreach ($statement['warnings'] as $warning) {
                    $write(['History notice', $warning]);
                }
                $write(['Matching checkouts include all their players and full receipt amounts. Item fees are descriptive, not additional payments.']);
                $write(['Completed refunds and category-change wallet credits recorded in the selected dates reduce the net. Category-change charges are included with entry fees. Unverified/private collections and unreconciled payments are excluded. This is not a wallet balance or outstanding-fee statement.']);
                fputcsv($out, ['Period', $statement['allTime'] ? 'All time' : $statement['from'].' to '.$statement['to'], 'Generated', $statement['generated_at']->toIso8601String()], ',', '"', '');
                fputcsv($out, ['Recorded date', 'Method', 'Reference', 'Checkout', 'Entry details (fees are not additional payments)', 'Paid ZAR', 'Refunded ZAR'], ',', '"', '');
                foreach ($statement['rows'] as $row) {
                    $details = collect($row['details'])->map(fn ($d) => $d['player'].' / '.$d['event'].' / '.($d['event_date'] ?? '').($d['fee'] === null ? '' : ' / fee R'.number_format($d['fee'] / 100, 2, '.', '')))->implode('; ');
                    $cells = [$row['date'], $row['method'], $row['reference'], $row['checkout'], $details,
                        number_format($row['paid'] / 100, 2, '.', ''), number_format($row['refunded'] / 100, 2, '.', '')];
                    $write($cells);
                }
                fputcsv($out, ['Known receipt totals', '', '', '', '', number_format($statement['paid'] / 100, 2, '.', ''), number_format($statement['refunded'] / 100, 2, '.', '')], ',', '"', '');
                $write(['Historical paid record count (excluded from known receipt totals)', $statement['historicalRows']->count()]);
                $write(['Recorded historical entry fee subtotal ZAR (not verified payments; excluded from known receipt totals)', number_format($statement['recordedFeeTotal'] / 100, 2, '.', '')]);
                $write(['Current listed entry fee reference subtotal ZAR (not historical price or payment proof; excluded from captured fees and receipt totals)', number_format($statement['listedFeeTotal'] / 100, 2, '.', '')]);
                $write(['Historical paid records — separate from known receipt totals. Undated records are shown regardless of selected payment period. Amounts and dates unavailable in the original records are not inferred.']);
                $write(['Records with no available amount or fee reference are omitted. Registration dates are linked registration creation dates, not payment dates.']);
                $write(['Payment date', 'Registration date (linked registration created at; not payment date)', 'Method', 'Reference', 'Historical record', 'Player / event / fee sources', 'Recorded amount ZAR (excluded from receipt totals)', 'Recorded historical entry fee ZAR (not verified payment)', 'Fee completeness', 'Current listed entry fee ZAR (reference only; historical price unverified)']);
                foreach ($statement['historicalRows'] as $row) {
                    $write([$row['date'] ?? 'Payment date unavailable', $row['registration_date'] ?? ($row['registration_applicable'] ? 'Registration date unavailable' : 'Not applicable'),
                        $row['method'], $row['reference'], $row['checkout'],
                        collect($row['details'])->map(fn ($d) => $d['player'].' / '.$d['event'].' / Event date: '.($d['event_date'] ?? 'unavailable')
                            .' / Recorded historical entry fee: '.(($d['recorded_fee'] ?? null) === null ? 'Amount unavailable' : 'R '.number_format($d['recorded_fee'] / 100, 2, '.', ''))
                            .(($d['listed_fee'] ?? null) === null ? '' : ' / '.$d['listed_fee_source'].': R '.number_format($d['listed_fee'] / 100, 2, '.', '').' (reference only; historical price unverified)'))->implode('; '),
                        $row['amount'] === null ? 'Amount unavailable' : number_format($row['amount'] / 100, 2, '.', ''),
                        $row['recordedFee'] === null ? 'Amount unavailable' : number_format($row['recordedFee'] / 100, 2, '.', ''),
                        $row['recordedFee'] === null ? 'Unavailable' : ($row['partialRecordedFee'] ? 'Partial — some player fees unavailable' : 'Captured fee available'),
                        $row['listedFee'] === null ? 'Amount unavailable' : number_format($row['listedFee'] / 100, 2, '.', '')]);
                }
                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        $statement['payments'] = new LengthAwarePaginator($statement['rows']->forPage($page, 25)->values(), $statement['rows']->count(), 25, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
        $historyPage = LengthAwarePaginator::resolveCurrentPage('history_page');
        $statement['historicalPayments'] = new LengthAwarePaginator($statement['historicalRows']->forPage($historyPage, 25)->values(), $statement['historicalRows']->count(), 25, $historyPage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'history_page']);

        return response()->view('account.statement', $statement)->header('Cache-Control', 'private, no-store');
    }
}
