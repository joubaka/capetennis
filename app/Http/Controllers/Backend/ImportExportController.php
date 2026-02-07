<?php

namespace App\Http\Controllers\backend;

use App\Exports\ExportUsers;
use App\Exports\PlayersEventExport;
use App\Http\Controllers\Controller;
use App\Imports\ImportUsers;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportController extends Controller
{
   

    public function exportRegistrations($event_id) 
    {
        return Excel::download(new PlayersEventExport($event_id), 'registrations.xlsx');
    }

    public function import() 
    {
        Excel::import(new ImportUsers, request()->file('file'));
            
        return back();
    }
}
