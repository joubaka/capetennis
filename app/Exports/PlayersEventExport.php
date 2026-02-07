<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PlayersEventExport implements FromCollection,WithMapping,WithHeadings 
    

/**
 * @return \Illuminate\Support\Collection
 */
{
    use Exportable;
    public $eventId;


    public function __construct(int $eventId)
    {
        $this->eventId = $eventId;
    }

   
        public function collection()
        {
            $event = Event::find($this->eventId);
            return $event->registrations;
        }
    
        public function map($row): array
        {
           return [$row->categoryEvent->category['name'],$row->registration->players[0]->name,$row->registration->players[0]->surname,$row->registration->players[0]->email,'0'.$row->registration->players[0]->cellNr];
        }
    
        public function columnFormats(): array
        {
            return [
                'E' => NumberFormat::FORMAT_NUMBER,
            ];
        }
    
      
        public function columnWidths(): array
        {
            return [
                'A' => 25,
                'B' => 20,
                'C' => 20,
                'D' => 35,
                'E' => 25,
                            
            ];
        }
    
        public function headings(): array
        {
            return [
                'Age Group',
                'Name',
                'Surname',
                'Email', 
                'Cell nr',           
            ]; 
        }
  



}
