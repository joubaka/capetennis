<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use App\Models\GoalName;
use App\Models\GoalType;
use App\Models\Player;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\ToArray;

class GoalController extends Controller
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
        return view('backend.goal.create-goal');
    }
    public function create_general_goal(Request $request)
    {
        
        $data['player'] = Player::find($request->id);
        $data['type'] = GoalType::find($request->type);
        $data['technicals'] = GoalName::where('goal_type_id', $request->type)->get();

        $data['twoWeeks'] = Carbon::now()->addWeeks(2)->toDateString();;
        $data['endOfMonth'] = Carbon::now()->endOfMonth()->toDateString();
        $data['endOfNextMonth'] =  Carbon::now()->addMonth()->endOfMonth()->toDateString();
        $data['threemonths'] = Carbon::now()->addMonths(3)->toDateString();;
        $data['sixmonths'] = Carbon::now()->addMonths(6)->toDateString();
        $data['oneyear'] =  Carbon::now()->endOfYear()->toDateString();
        $data['tenyears'] = Carbon::now()->addYears(10)->endOfYear()->toDateString();;
        $data['fifteenyears'] = Carbon::now()->addYears(15)->endOfYear()->toDateString();
        $data['twoyears'] =  Carbon::now()->addYears(2)->endOfYear()->toDateString();

        return view('backend.goal.create-general-goal', $data);
    }
    public function create_career_goal(Request $request)
    {
        $data['player'] = Player::find($request->id);
        $data['type'] = GoalType::find($request->type);
        $data['technicals'] = GoalName::where('goal_type_id', $request->type)->get();

        $data['twoWeeks'] = Carbon::now()->addWeeks(2)->toDateString();;
        $data['endOfMonth'] = Carbon::now()->endOfMonth()->toDateString();
        $data['endOfNextMonth'] =  Carbon::now()->addMonth()->endOfMonth()->toDateString();
        $data['threemonths'] = Carbon::now()->addMonths(3)->toDateString();;
        $data['sixmonths'] = Carbon::now()->addMonths(6)->toDateString();
        $data['oneyear'] =  Carbon::now()->endOfYear()->toDateString();
        $data['tenyears'] = Carbon::now()->addYears(10)->endOfYear()->toDateString();;
        $data['fifteenyears'] = Carbon::now()->addYears(15)->endOfYear()->toDateString();
        $data['twoyears'] =  Carbon::now()->addYears(2)->endOfYear()->toDateString();
        $data ['nextYear'] =  Carbon::now()->addYears(1)->endOfYear()->toDateString();
        return view('backend.goal.create-career-goal', $data);
    }
   
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        parse_str($request->data, $data);

        if ($request->goalCustomDate == null) {
            $dateto = $request->goalDate;
        }


        $goal = new Goal();


        $goal->player_id = $data['player'];
        $goal->endDate = $data['goalDate'];
        $goal->start_date = Carbon::now();

        $goal->save();


        foreach ($request->names as $key => $value) {
            $goal->names()->attach($value);
        }





        return $goal->player_id;
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
        Goal::where('id', $id)->delete();

        return 'Deleted Succesfully';
    }
}
