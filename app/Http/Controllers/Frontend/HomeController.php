<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Position;
use App\Models\RankingScores;
use App\Models\Registration;
use App\Models\RegistrationOrderItems;
use App\Models\Series;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\UserPlayer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    function get_players()
    {
        //get all duplicate names
        $dup_players = Player::all()->groupBy('full_name')->filter(function ($item) {
            return $item->count() > 1;
        });
        throw new \RuntimeException('This legacy duplicate-player maintenance action is disabled.');
        foreach ($dup_players as $name => $profiles) {

            foreach ($profiles as $key => $profile) {
                if ($profile->registrations->count() > 0) {
                    //dd($profile->id);
                    $p['name'][] = $name;
                    $p['registration'][] = $name . ' ' . $profile->registrations->count();
                } else {




                    if ($profile->team->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'team';
                        $tp = TeamPlayer::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);

                    } else if ($profile->rankings->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'rank';
                        $tp = RankingScores::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);
                    } else if ($profile->allPositions->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'rank';
                        $tp = Position::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);
                    } else if ($profile->registrations_order_items->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'order';
                        $tp = RegistrationOrderItems::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);
                    } else {
                        if($profile->registrations->count() == 0){
                              Player::where('id', $profile->id)->delete();
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'was deleted';
                        }else{
                            $p['name'][] = $name;
                            $p['registration'][] = $name . ' ' . 'reg found';
                        }

                    }
                }
            }
        }
        $players = Player::all();
        foreach ($players as $key => $value) {

            $value->users()->attach($value->userId);
        }



        if(isset($p)){

            throw new \RuntimeException('This legacy duplicate-player maintenance action is disabled.');
        }else{
               throw new \RuntimeException('This legacy duplicate-player maintenance action is disabled.'); /*
               $dup_players = Player::all()->groupBy('full_name')->filter(function ($item) {
            return $item->count() > 1;
        }); */






    }
        }
    public function mergePlayers(){

        $dup_players = Player::with('registrations')->get()->groupBy('full_name')->filter(function ($item) {
            return $item->count() > 1;
        });

        foreach ($dup_players as $name => $profiles) {

            foreach ($profiles as $key => $profile) {
                if ($profile->registrations->count() > 0) {

                    foreach($profile->registrations as $reg){






                    }
                    $p['name'][] = $name;
                    $p['registration'][] = $name . ' ' . $profile->registrations->count();

                } else {




                    if ($profile->team->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'team';
                        $tp = TeamPlayer::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);

                    } else if ($profile->rankings->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'rank';
                        $tp = RankingScores::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);
                    } else if ($profile->allPositions->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'rank';
                        $tp = Position::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);
                    } else if ($profile->registrations_order_items->count() > 0) {
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'order';
                        $tp = RegistrationOrderItems::where('player_id',$profile->id)->update(['player_id' => $profiles[0]->id]);
                    } else {

                        if($profile->registrations->count() == 0){
                              Player::where('id', $profile->id)->delete();
                        $p['name'][] = $name;
                        $p['registration'][] = $name . ' ' . 'was deleted';
                        }else{
                            $p['name'][] = $name;
                            $p['registration'][] = $name . ' ' . 'reg found';
                        }

                    }
                }
            }
        }

        $dup_players = Player::with('registrations')->get()->groupBy('full_name')->filter(function ($item) {
            return $item->count() > 1;
        });
        throw new \RuntimeException('This legacy duplicate-player maintenance action is disabled.');

    }

    public function index()
    {
        $series = Series::query()
            ->where('leaderboard_published', true)
            ->orderByDesc('year')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('frontend.home', compact('series'));
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



  public function get_events(Request $request)
  {
    $startedAt = hrtime(true);
    $requestId = (string) Str::uuid();
    $period = in_array($request->get('period'), ['upcoming', 'past', 'all'], true)
      ? $request->get('period')
      : 'upcoming';
    $search = mb_substr(trim((string) $request->get('search', '')), 0, 100);

    $eventLog = Log::channel('home-events');
    $eventLog->info('Home event feed request started.', [
      'request_id' => $requestId,
      'user_id' => $request->user()?->id,
      'authenticated' => $request->user() !== null,
      'period' => $period,
      'page' => max(1, (int) $request->get('page', 1)),
      'search_length' => mb_strlen($search),
    ]);

    try {

    $query = Event::query()
      ->visibleTo($request->user())
      ->select([
        'id',
        'name',
        'start_date',
        'end_date',
        'deadline',
        'logo',
      ]);

    // 🔍 SEARCH FILTER
    if (!empty($search)) {
      $query->where('name', 'like', '%' . $search . '%');
    }

    // 📅 PERIOD FILTER
    if ($period === 'past') {
      $query
        ->whereDate('start_date', '<', Carbon::today())
        ->orderBy('start_date', 'desc');

    } elseif ($period === 'upcoming') {
      $query
        ->whereDate('start_date', '>=', Carbon::today())
        ->orderBy('start_date', 'asc');

    } else {
      $query->orderBy('start_date', 'desc');
    }

    $events = $query->paginate(20);
    $data = $events->getCollection()->map(function (Event $event) {
      $payload = [
        'id' => $event->id,
        'name' => $event->name,
        'start_date' => $event->start_date?->toDateString(),
        'end_date' => $event->end_date?->toDateString(),
        'deadline' => $event->deadline,
        'logo' => $event->logo,
      ];

      return $payload;
    })->values();

    $response = response()->json([
      'data' => $data,
      'meta' => [
        'current_page' => $events->currentPage(),
        'last_page' => $events->lastPage(),
        'per_page' => $events->perPage(),
        'total' => $events->total(),
      ],
    ]);

    $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);
    $eventLog->info('Home event feed request completed.', [
      'request_id' => $requestId,
      'user_id' => $request->user()?->id,
      'period' => $period,
      'page' => $events->currentPage(),
      'last_page' => $events->lastPage(),
      'returned_count' => $data->count(),
      'total_count' => $events->total(),
      'duration_ms' => $durationMs,
    ]);

    return $response->header('X-Home-Events-Request-Id', $requestId);
    } catch (\Throwable $exception) {
      $eventLog->error('Home event feed request failed.', [
        'request_id' => $requestId,
        'user_id' => $request->user()?->id,
        'period' => $period,
        'page' => max(1, (int) $request->get('page', 1)),
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
        'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
      ]);

      throw $exception;
    }
  }

}
