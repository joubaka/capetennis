<?php

namespace App\Imports;

use App\Models\NoProfileTeamPlayer;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        
        return new NoProfileTeamPlayer([
            'team_id'     => $row[0],
            'rank'    => $row[1],
            'name' => $row[2],
            'surname' => $row[3],
            'pay_status' => $row[4],
        ]);
    }
}
