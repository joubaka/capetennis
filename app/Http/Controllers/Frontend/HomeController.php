<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAdmin;
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

        $users = User::all();
        $events = Event::all();
        $eventTypes = EventType::all();
        $series = Series::all();
        return view('frontend.home', compact('events', 'users', 'eventTypes', 'series'));
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
    $period = $request->get('period', 'upcoming');
    $search = $request->get('search');

    $query = Event::query()
      ->visibleTo($request->user());

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

    $events = $query->get();
    $user = $request->user();
    $isSuperUser = $user?->hasRole('super-user') ?? false;
    $canViewAllStatuses = $user?->hasAnyRole(['super-user', 'admin']) ?? false;
    $managedEventIds = $user && !$isSuperUser
      ? EventAdmin::query()
        ->where('user_id', $user->id)
        ->pluck('event_id')
        ->flip()
      : collect();

    $events->each(function (Event $event) use ($canViewAllStatuses, $managedEventIds) {
      if (!$canViewAllStatuses && !$managedEventIds->has($event->id)) {
        return;
      }

      $event->setAttribute('admin_status', [
        'publication' => $event->published ? 'Published' : 'Unpublished',
        'entries' => $this->entriesAreOpen($event) ? 'Sign-up open' : 'Sign-up closed',
      ]);
    });

    return $events;
  }

  private function entriesAreOpen(Event $event): bool
  {
    if (!$event->signUp || in_array($event->status, ['closed', 'draft'], true) || !$event->start_date) {
      return false;
    }

    $entryCloseAt = $event->start_date
      ->copy()
      ->subDays(is_numeric($event->deadline) ? (int) $event->deadline : 0)
      ->endOfDay();

    return now()->lte($entryCloseAt);
  }


}
