<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EventEntriesExport implements FromCollection, WithHeadings
{
  public function __construct(public Event $event)
  {
  }

  public function collection()
  {
    return $this->event->eventCategories()
      ->with([
        'category',
        'categoryEventRegistrations.registration.players',
      ])
      ->get()
      ->flatMap(function ($categoryEvent) {

        return $categoryEvent->categoryEventRegistrations->map(function ($entry) use ($categoryEvent) {

          $player = optional($entry->registration?->players)->first();

          return [
            'category' => $categoryEvent->category->name ?? '',
            'player' => trim(($player->name ?? '') . ' ' . ($player->surname ?? '')),
            'status' => $entry->status ?? '',
            'payment' => ($entry->payment_status_id ?? 0) == 1 ? 'Paid' : 'Unpaid',
          ];
        });
      });
  }

  public function headings(): array
  {
    return [
      'Category',
      'Player',
      'Status',
      'Payment',
    ];
  }
}
