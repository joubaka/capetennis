<?php

namespace App\Domain\Finance\Services;

use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/** Read-only presentation of canonical ledger rows; never apportions receipts. */
class EventFinanceReceiptReport
{
    public function build(Event $event, Collection $rows): Collection
    {
        $rows = $rows->filter(fn ($row) => ! isset($row->event_id) || (int) $row->event_id === (int) $event->id);
        $transactions = Transaction::query()->where('event_id', $event->id)
            ->whereIn('id', $rows->pluck('source_tx_id')->filter()->unique())
            ->where('custom_str5', 'TeamOrder')->get(['id', 'custom_int5']);
        $orders = TeamPaymentOrder::with(['team.regions', 'team.category'])
            ->where('event_id', $event->id)
            ->whereIn('id', $transactions->pluck('custom_int5')->merge(
                $rows->filter(fn ($row) => ($row->order ?? null) instanceof TeamPaymentOrder)
                    ->map(fn ($row) => $row->order->id)
            )->merge($rows->pluck('team_order_id')->filter())->unique())->get()->keyBy('id');
        $transactionRegions = $transactions->mapWithKeys(fn ($tx) => [
            $tx->id => $this->orderRegion($orders->get($tx->custom_int5), $event),
        ]);

        return collect(['registrations' => 'Registration receipts', 'clothing' => 'Clothing receipts'])
            ->map(function ($label, $key) use ($rows, $event, $orders, $transactionRegions) {
                $sectionRows = $rows->filter(fn ($row) => ($row->type === 'clothing_payment') === ($key === 'clothing'));
                $groups = $sectionRows->groupBy(function ($row) use ($event, $orders, $transactionRegions) {
                    $order = $row->order ?? null;
                    if ($order instanceof ClothingOrder) {
                        return $this->orderRegion($order, $event);
                    }
                    if ($order instanceof TeamPaymentOrder) {
                        return $this->orderRegion($orders->get($order->id), $event);
                    }
                    if (isset($row->team_order_id)) {
                        return $this->orderRegion($orders->get($row->team_order_id), $event);
                    }
                    if ($region = $transactionRegions->get($row->source_tx_id ?? null)) {
                        return $region;
                    }
                    $regions = collect($row->registrationDetails ?? [])->pluck('region')
                        ->map(fn ($region) => trim((string) $region))
                        ->map(fn ($region) => $region === '' || $region === '—' ? 'Region not recorded' : $region)->unique()->sort()->values();
                    if ($regions->count() > 1) {
                        return 'Multiple regions: '.$regions->implode(', ');
                    }

                    return $regions->first() ?? 'Region not recorded';
                })->sortKeys()->map(fn ($regionRows) => [
                    'rows' => $regionRows->values(),
                    'totals' => $this->totals($regionRows),
                ]);

                return ['label' => $label, 'count' => $sectionRows->count(), 'groups' => $groups, 'totals' => $this->totals($sectionRows)];
            });
    }

    private function orderRegion(ClothingOrder|TeamPaymentOrder|null $order, Event $event): string
    {
        if (! $order || (int) $order->event_id !== (int) $event->id) {
            return 'Region not recorded';
        }
        $team = $order->team;
        if (! $team || (int) $team->category?->event_id !== (int) $event->id) {
            return 'Region not recorded';
        }

        return $team->regions?->region_name ?: 'Region not recorded';
    }

    private function totals(Collection $rows): array
    {
        $payments = $rows->reject(fn ($row) => in_array($row->type, ['refund', 'withdrawal'], true));
        $completed = $rows->where('type', 'refund')->where('refund_status', 'completed');

        return [
            'gross' => round($payments->sum('gross'), 2),
            'fees' => round($payments->sum(fn ($row) => ($row->fee ?? 0) + ($row->capeFee ?? 0)), 2),
            'completed_refunds' => round($completed->sum('refund_gross'), 2),
            'pending_refunds' => round($rows->where('type', 'refund')->where('refund_status', 'pending')->sum('refund_gross'), 2),
            'net' => round($payments->sum('net') + $completed->sum('net'), 2),
        ];
    }
}
