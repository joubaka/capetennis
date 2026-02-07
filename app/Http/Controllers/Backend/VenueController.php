<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\Venues;
use Illuminate\Http\Request;

class VenueController extends Controller
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data['venues'] = Venues::all();
        $data['draw'] = Draw::find($id);
        $data['event'] = $data['draw']->events;
        $data['drawTypes'] = DrawType::all();
        $data['selectedVenues'] = $data['draw']->venues->pluck('id')->toArray(); // Assuming a relationship to get selected venues
        //dd($data);
        
        return view('backend.venue.venue-show', $data);
        
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

    public function venue_list(Request $request)
    {
        return Venues::all();
    }
    public function saveDrawVenues(Request $request)
    {
       
       $draw = Draw::find($request->draw);
       $draw->venues()->sync($request->venues);
       return redirect()->route('headOffice.show',$draw->events);
       //dd($draw->venues);
    }

}
