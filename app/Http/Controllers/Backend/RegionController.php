<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ClothingItemType;
use App\Models\TeamRegion;
use Illuminate\Http\Request;

class RegionController extends Controller
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
        // return $request;
        $region = new TeamRegion();
        $region->region_name = $request->region_name;
        $region->short_name = $request->short_name;

        $region->save();
        return $region;
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

  public function getRegionClothingItems(Request $request)
  {
    $data = $request->validate(['region' => ['required', 'integer', 'exists:team_regions,id']]);
    $region = \App\Models\TeamRegion::findOrFail($data['region']);
    abort_unless((bool) $region->clothing_order, 404);

    $clothingItems = ClothingItemType::with('sizes')
      ->where('region_id', $region->id)
      ->where('price', '>', 0)
      ->whereHas('sizes')
      ->orderBy('ordering')
      ->get();

    return view('frontend.clothing.partials.items', [
      'clothingItems' => $clothingItems
    ]);
  }


}
