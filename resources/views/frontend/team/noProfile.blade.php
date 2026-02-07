
@foreach($team->team_players_no_profile as $key => $play)

  <li class="d-flex align-items-center mb-4">
      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
          <div class="me-2">
              <div class="d-flex align-items-center">
                  <div class="fs-6 mb-0 me-1"><span class="badge bg-label-primary">{{$key+1}}</span> {{ucfirst(strtolower($play->name))}} {{ucfirst(strtolower($play->surname))}}</div>

              </div>
              <small class="text-muted"></small>
          </div>
          <div class="user-progress">
              <p class="text-success fw-semibold mb-0">
          {{dd($team->team_players[$key])}}
                  @if($team->team_players[$key]->pay_status == 1)
                 
                  <span class="badge bg-label-success">Registered</span>

                  @else
                
                      @if($event->signUp == 1)
                    
                      <a href="{{route('player.create',['name'=> ucfirst(strtolower($play->name)),'surname' => ucfirst(strtolower($play->surname)),'team'=>$team->id,'event'=>$event->id,'noProfileId'=> $play->id])}}" class="badge bg-label-warning">Register now</a>
                      @else

                      @endif
                 
                  @endif

              </p>
          </div>
      </div>


  </li>
  @endforeach
