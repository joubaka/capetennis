<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EventPlayersExport implements FromView, WithTitle, ShouldAutoSize, WithStyles
{
  protected $event;

  public function __construct($event)
  {
    $this->event = $event;
  }

  public function view(): View
  {
    return view('backend.event.exports.event_players_excel', [
      'event' => $this->event
    ]);
  }

  public function title(): string
  {
    return 'Players List';
  }

  public function styles(Worksheet $sheet)
  {
    $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal('center');
    return [];
  }
}
