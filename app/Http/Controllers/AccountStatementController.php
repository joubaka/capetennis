<?php

namespace App\Http\Controllers;

use App\Services\AccountStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountStatementController extends Controller
{
    public function __invoke(Request $request, AccountStatementService $service)
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'player' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'in:pdf,csv'],
        ]);
        $from = $input['from'] ?? now()->startOfYear()->toDateString();
        $to = $input['to'] ?? now()->toDateString();
        if ($to < $from || \Carbon\Carbon::parse($from)->diffInDays($to) > 731) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'Choose a period of up to two years, ending on or after the start date.']);
        }
        $statement = $service->build($request->user(), $from, $to, trim($input['player'] ?? ''));
        $filename = 'cape-tennis-entry-statement-'.$from.'-'.$to;
        if (($input['format'] ?? null) === 'pdf') {
            return Pdf::loadView('account.statement-pdf', $statement)->setPaper('a4', 'landscape')
                ->download($filename.'.pdf')->header('Cache-Control', 'private, no-store');
        }
        if (($input['format'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($statement) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Cape Tennis entry payment statement', $statement['account']->name, $statement['account']->email], ',', '"', '');
                fputcsv($out, ['From', $statement['from'], 'To', $statement['to'], 'Generated', $statement['generated_at']->toIso8601String()], ',', '"', '');
                fputcsv($out, ['Recorded date', 'Method', 'Reference', 'Checkout', 'Entry details (fees are not additional payments)', 'Paid ZAR', 'Refunded ZAR'], ',', '"', '');
                foreach ($statement['rows'] as $row) {
                    $details = collect($row['details'])->map(fn ($d) => $d['player'].' / '.$d['event'].' / '.($d['event_date'] ?? '').($d['fee'] === null ? '' : ' / fee R'.number_format($d['fee'] / 100, 2, '.', '')))->implode('; ');
                    $cells = [$row['date'], $row['method'], $row['reference'], $row['checkout'], $details,
                        number_format($row['paid'] / 100, 2, '.', ''), number_format($row['refunded'] / 100, 2, '.', '')];
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r\n]/', (string) $v) ? "'".$v : $v, $cells), ',', '"', '');
                }
                fputcsv($out, ['Totals', '', '', '', '', number_format($statement['paid'] / 100, 2, '.', ''), number_format($statement['refunded'] / 100, 2, '.', '')], ',', '"', '');
                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        $statement['payments'] = new LengthAwarePaginator($statement['rows']->forPage($page, 25)->values(), $statement['rows']->count(), 25, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return response()->view('account.statement', $statement)->header('Cache-Control', 'private, no-store');
    }
}
