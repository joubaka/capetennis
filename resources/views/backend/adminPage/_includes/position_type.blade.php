  <!-- Multiple Lists Draggable -->
  <div class="row mb-4">
      @foreach($event->eventCategories as $k => $c)

      <div class="col-12 col-md-3">
          <div class="card shadow-none bg-transparent border border-primary mb-2">
            <div class="card-header {{ count($c->positions) > 0 ? 'bg-label-success' : 'noooo' }}" data-categoryevent="{{ $c->id }}">

                  <h5>{{$c->category->name}} {{$c->id}}</h5>
                  <button type="button"
                  class="btn btn-sm btn-outline-danger reset-order"
                  data-categoryevent="{{ $c->id }}">
            Reset
          </button>
              </div>

              <div class="card-body ">

                  <div class="col-12  m-2">

                    <ul class="sortable" data-categoryevent="{{ $c->id }}">
                          @if(count($c->positions) > 0)
                          @foreach($c->positions as $key => $position)

                          <li data-categoryevent="{{$c->id}}" value="{{$position->player->id}}" class="list-group-item">
                              <span class="number">{{$key+1}}</span><span> {{$position->player->name}} {{$position->player->surname}}</span>

                          </li>
                          @endforeach
                          @else
                          @foreach($c->registrations as $key => $reg)
                          <li data-categoryevent="{{$c->id}}" value="{{$reg->players[0]->id}}" class="list-group-item">
                              <span class="number"></span><span> {{$reg->players[0]->name}} {{$reg->players[0]->surname}}</span>

                          </li>
                          @endforeach
                          @endif
                      </ul>
                      <ul class="original-order d-none" data-categoryevent="{{ $c->id }}">
                        @foreach($c->registrations as $key => $reg)
                          <li data-categoryevent="{{$c->id}}" value="{{$reg->players[0]->id}}" class="list-group-item">
                              <span class="number"></span><span> {{$reg->players[0]->name}} {{$reg->players[0]->surname}}</span>

                          </li>
                          @endforeach
                      </ul>
                  </div>


              </div>
          </div>


      </div>

      @endforeach
  </div>
  <!-- /Multiple Lists Draggable ends -->
