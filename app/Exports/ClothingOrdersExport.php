<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class ClothingOrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $clothings;

    // Constructor to receive data
    public function __construct($clothings)
    {
        $this->clothings = $clothings;
    }

    // Return collection of orders
    public function collection()
    {
        return $this->clothings;
    }

    // Excel headers
    public function headings(): array
    {
        return ['Order #', 'Date', 'Player', 'Item', 'Size', 'Team', 'Payfast Id', 'Status'];
    }

    // Mapping data for each row
    public function map($order): array
    {
        return $order->items->map(function ($item) use ($order) {
            return [
                $order->id,
                $item->created_at ? $item->created_at->format('d M Y') : 'N/A',
                optional($order->player)->getFullNameAttribute(),
                optional($item->itemType)->item_type_name,
                optional($item->size)->size,
                optional($order->team)->name,
                $order->pf_id,
                $order->pay_status ? 'Paid' : 'Unpaid',
            ];
        })->toArray();
    }
}


