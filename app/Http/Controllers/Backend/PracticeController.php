<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\NoProfilePlayer;
use App\Models\Practice;
use App\Models\PracticeFixtures;
use App\Models\PracticeResults;
use Illuminate\Http\Request;

class PracticeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {


        $practice = new Practice();
        $practice->duration_id = $request->duration_id;
        $practice->player_id = $request->player_id;
        $practice->practice_type_id = $request->practice_type_id;
        $practice->date_of_lesson = $request->date;
        $practice->save();

        if ($request->practice_type_id == 1) {
            $p1scores = json_decode($request->p1score[0]);
            $p2scores = json_decode($request->p2score[0]);
            $practiceReg = new PracticeFixtures();
            $practiceReg->registration1_id = $request->p1;
            $practiceReg->registration2_id = $request->p2;


            $practiceReg->practice_id = $practice->id;
            $practiceReg->save();
            if ($request->p2 == 0) {
                $noProfile = new NoProfilePlayer();
                $noProfile->full_name = $request->player2Name;
                $noProfile->fixture_practice_id = $practiceReg->id;
                $noProfile->save();
            }



            for ($i = 0; $i < count($p1scores); $i++) {
                $result = new PracticeResults();
                if ($p1scores[$i] > $p2scores[$i]) {
                    $result->winner_registration = $request->p1;
                    $result->loser_registration = $request->p2;
                } elseif ($p1scores[$i] < $p2scores[$i]) {
                    $result->winner_registration = $request->p2;
                    $result->loser_registration = $request->p1;
                } else {
                    return 'Error with score input';
                }
                $result->practice_fixture_id = $practiceReg->id;

                $result->registration1_score = $p1scores[$i];
                $result->registration2_score = $p2scores[$i];
                $result->save();
            }
        }





        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
