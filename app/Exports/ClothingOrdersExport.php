<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClothingOrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $clothings;

    // Constructor to receive data
    public function __construct($clothings)
    {
        $this->clothings = $clothings->loadMissing(['items.itemType', 'items.size', 'player', 'team'])
            ->flatMap(fn ($order) => $order->items->map(fn ($item) => compact('order', 'item')))
            ->values();
    }

    // Return collection of orders
    public function collection()
    {
        return $this->clothings;
    }

    // Excel headers
    public function headings(): array
    {
        return ['Order #', 'Date', 'Player', 'Item', 'Size', 'Team', 'Qty', 'Unit Price', 'Line Total', 'PayFast Id', 'Status'];
    }

    // Mapping data for each row
    public function map($row): array
    {
        $order = $row['order'];
        $item = $row['item'];

        return [
            $order->id,
            $item->created_at ? $item->created_at->format('d M Y') : 'N/A',
            optional($order->player)->getFullNameAttribute(),
            $item->item_name ?: optional($item->itemType)->item_type_name,
            $item->size_name ?: optional($item->size)->size,
            optional($order->team)->name,
            $item->qty ?: 1,
            (float) $item->price,
            (float) $item->line_total,
            $order->pf_id,
            $order->pay_status ? 'Paid' : 'Unpaid',
        ];
    }
}

