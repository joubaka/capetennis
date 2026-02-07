<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Fixtures;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\DrawTeam;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\Venues;
use Carbon\Carbon;
use Illuminate\Http\Request;

use function PHPUnit\Framework\isNull;

class HeadOfficeController extends Controller
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

    $event = Event::findOrFail($id);

    // Categories from teams in this event
    $categories = $event->categories()
      ->orderBy('name')
      ->get();

    $data['categories'] = $categories;
    $data['event'] = $event;
    $data['venues'] = Venues::all();
    $data['teamDrawTypes'] = DrawType::where('type', 'team')
      ->orderBy('drawTypeName')
      ->get();

    $data['individualDrawTypes'] = DrawType::where('type', 'individual')
      ->orderBy('drawTypeName')
      ->get();


    // ===============================
    // INDIVIDUAL SHOW (U/11–U/13 etc.)
    // ===============================
    if ($event->eventType == 6) {
      return view('backend.headOffice.individual-event-show', $data);

      // ===============================
      // CAVALIERS TRIALS (uses brackets)
      // ===============================
    } elseif ($event->eventType == 5) {
      // ----------------------------------------------
// Playing days
// ----------------------------------------------
      $data['playingDays'] = $this->getDatesBetween($event->start_date, $event->endDate);

      // ----------------------------------------------
// LOAD DRAWS WITH FIXTURES + BRACKETS
// ----------------------------------------------
      $draws = $event->draws()
        ->with(['drawFixtures.bracket'])
        ->orderBy('drawName')
        ->get();

      $data['draws'] = [];

      foreach ($draws as $draw) {

        $grouped = $draw->drawFixtures
          ->groupBy(function ($fixture) {
            return optional($fixture->bracket)->name ?? 'No Bracket';
          })
          ->map(function ($bracketGroup) {
            return $bracketGroup->groupBy('round')->sortKeys();
          });

        // Use draw ID to guarantee uniqueness
        $data['draws'][$draw->id] = [
          'name' => $draw->drawName,
          'bracket' => $grouped
        ];
      }

      return view('backend.headOffice.cavaliers-trials-show', $data);


      // ============================================
      // ⭐ NEW: EVENT TYPE 13 → INTERPRO PAGE (RR HUB)
      // ============================================
    } elseif ($event->eventType == 13) {

      return view('backend.headOffice.interpro-event-show', $data);
    }

    // ===============================
    // DEFAULT: TEAM EVENT PAGE
    // ===============================
    return view('backend.headOffice.team-event-show', $data);
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

  public function updateRegionOrder(Request $request)
  {

    foreach ($request->data as $key => $data) {
      if (!$data == 0) {
        $temp = EventRegion::find($data);
        $temp->ordering = ($key + 1);
        $temp->save();
      }
    }

    return $request;
  }

  public function createFormatFixturesTeam(Request $request)


  {  // Step 1: Fetch and validate data

    $validatedData = $request->validate([
      'category' => 'required|array',
      'category.*' => 'exists:category_events,id',
      'event_id' => 'required|exists:events,id',
      'drawType' => 'required|integer'
    ]);

    $categories = $validatedData['category'];
    $event_id = $validatedData['event_id'];
    $drawType = $validatedData['drawType'];

    $regions = EventRegion::where('event_id', $event_id)
      ->with('region')
      ->orderBy('ordering')
      ->get();

    // Check if the number of regions is odd
    if ($regions->count() % 2 != 0) {
      // Get all the existing ordering values
      $orderingValues = $regions->pluck('ordering')->toArray();

      // Find the missing number in the ordering sequence
      $missingOrdering = null;
      for ($i = 1; $i < count($orderingValues); $i++) {
        if ($orderingValues[$i] - $orderingValues[$i - 1] > 1) {
          // Missing number found between $orderingValues[$i-1] and $orderingValues[$i]
          $missingOrdering = $orderingValues[$i - 1] + 1;
          break;
        }
      }

      // If no missing ordering found (e.g., all numbers are sequential), add at the next higher value
      if ($missingOrdering === null) {
        $missingOrdering = $orderingValues[count($orderingValues) - 1] + 1;
      }

      // Create a dummy region with the missing ordering value
      $dummyRegion = (object) [
        'id' => 0,
        'region' => 'bye',
        'ordering' => $missingOrdering // Insert at the missing ordering value
      ];

      // Insert the dummy region into the collection
      $regions->push($dummyRegion); // Add it to the end (or use prepend to add to the beginning)
    }

    // Sort the collection by the 'ordering' field
    $regions = $regions->sortBy('ordering')->values();



    $regionFixtures = Fixtures::makeRegionFixtures($regions);

    // Preload category names
    $categoryNames = CategoryEvent::whereIn('category_events.id', $categories) // Specify table name
      ->join('categories', 'category_events.category_id', '=', 'categories.id')
      ->pluck('categories.name', 'category_events.id');



      $draws = [];
    $allFixtures = [];

    if ($drawType == 3) {


        $drawName = trim($categoryNames[$categories[0]], 'Boys').'Mixed';

        $draw = $this->createDraw($event_id, $drawType, $drawName);
        $draws[] = $draw;

        return $this->createFixtures($draw, $regionFixtures, $categories);



        // Generate fixtures for each draw
        // $this->createFixtures($draw, $regionFixtures, $category);

        $allFixtures = array_merge($allFixtures, $this->createFixtures($draw, $regionFixtures, [$category]));
        // Generate fixtures
        $allFixtures = $this->createFixtures($draw, $regionFixtures, $categories);

    } elseif ($drawType == 6) {
      foreach ($categories as $category) {
        $drawName = $categoryNames[$category];
        $draws[] = $this->createDraw($event_id, $drawType, $drawName);
      }
    } else {

      foreach ($categories as $category) {
        $drawName = $categoryNames[$category];
        $draw = $this->createDraw($event_id, $drawType, $drawName);
        $draws[] = $draw;

        // Generate fixtures for each draw
        // $this->createFixtures($draw, $regionFixtures, $category);

        $allFixtures = array_merge($allFixtures, $this->createFixtures($draw, $regionFixtures, [$category]));
      }
    }

    return response()->json([
      'draws' => $draws,
      'fixtures' => $allFixtures
    ]);
  }
  private function createDraw(int $event_id, int $drawType, string $drawName): Draw
  {
    $draw = new Draw();
    $draw->drawName = $drawName;
    $draw->drawType_id = $drawType;
    $draw->event_id = $event_id;
    $draw->save();

    // Create and link draw settings
    $settings = new DrawSetting();
    $settings->draw_id = $draw->id;
    $settings->num_sets = 3;
    $settings->save();

    return $draw;
  }

  private function getTeamsByRegionAndCategory($regionId, $categories)
  {

    $team = Team::whereHas('regions', function ($query) use ($regionId, $categories) {
      $query->where('region_id', $regionId)->whereIn('category_event_id', $categories);
    })->get();
    return $team;
  }

  private function createFixtures($draw, $regionFixtures, $category)
  {

    
    $count = 1;
    $fixtures = [];
    $tieCount = 1; // Assuming $tieCount starts from 1 or you can adjust it accordingly



    foreach ($regionFixtures as $roundKey => $round) {
      foreach ($round as $match) {

        //  return $round;
        // Validate the structure of the match array
        //  if (!isset($match[0], $match[1]) || !isset($match[0]->region, $match[1]->region)) {
        //       continue; // Skip invalid fixtures
        //  }

        $region1 = (object) $match[0];  // Access as object
        $region2 = (object) $match[1];  // Access as object




        // Check if either region is a dummy region (id = 0 or name = 'Dummy')
        if ($region1->id == 0 || $region2->id == 0) {
        } else {


          if($draw->drawType_id == 3){
            $teams1['boys'] = $this->getTeamsByRegionAndCategory($region1->region_id, [$category[0]]);
            $teams1['girls'] = $this->getTeamsByRegionAndCategory($region1->region_id, [$category[1]]);


            $teams2['boys'] = $this->getTeamsByRegionAndCategory($region2->region_id, [$category[0]]);
            $teams2['girls'] = $this->getTeamsByRegionAndCategory($region2->region_id, [$category[1]]);


            $count = Fixtures::createMixedFixtures( $draw,
            $draw->drawType_id,
            $region1,
            $region2,
            $teams1,
            $teams2,
            $count, 
            $tieCount,
            $roundKey);


          }else{
                $teams1 = $this->getTeamsByRegionAndCategory($region1->region_id, [$category]);

                    $teams2 = $this->getTeamsByRegionAndCategory($region2->region_id, [$category]);

                    // Only proceed if there are teams in both regions and categories
                    if ($teams1->isNotEmpty() && $teams2->isNotEmpty()) {

                      $count = Fixtures::createTeamFixtures(
                        $draw,
                        $draw->drawType_id,
                        $region1,
                        $region2,
                        $teams1,
                        $teams2,
                        $count,
                        $tieCount,
                        $roundKey
                      );
                    }
                  }





            $tieCount++; // Increment tie count after each successful fixture creation

        }
      }
    }



    return $fixtures;
  }

  function getDatesBetween($start_date, $endDate)
  {
    // Convert start and end dates to Carbon instances
    $start = Carbon::parse($start_date);
    $end = Carbon::parse($endDate);

    // Define the interval as 1 day
    $interval = new \DateInterval('P1D');

    // Define the period, including the end date
    $period = new \DatePeriod($start, $interval, $end->addDay());

    // Collect dates into an array
    $dates = [];
    foreach ($period as $date) {
      $dates[] = $date->format('Y-m-d');
    }

    return $dates;
  }
}
