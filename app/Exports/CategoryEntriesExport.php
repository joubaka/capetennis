<?php

namespace App\Exports;

use App\Models\CategoryEvent;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CategoryEntriesExport implements FromCollection, WithHeadings, ShouldAutoSize
{
  protected CategoryEvent $categoryEvent;

  public function __construct(CategoryEvent $categoryEvent)
  {
    $this->categoryEvent = $categoryEvent;
  }

  public function collection()
  {
    return $this->categoryEvent
      ->categoryEventRegistrations()
      ->with(['registration.players'])
      ->get()
      ->map(function ($entry) {

        $player = optional(
          optional($entry->registration)->players
        )->first();

        return [
          'registration_id' => $entry->registration_id,
          'player_name' => trim(
            ($player->name ?? '') . ' ' . ($player->surname ?? '')
          ),
          'category' => optional($this->categoryEvent->category)->name,
          'status' => ucfirst($entry->status ?? 'active'),
          'payment_status' => $entry->payment_status_id == 1
            ? 'Paid'
            : 'Unpaid',
        ];
      });
  }

  public function headings(): array
  {
    return [
      'Registration ID',
      'Player',
      'Category',
      'Entry Status',
      'Payment Status',
    ];
  }
}
