  <!-- Multiple Lists Draggable -->
  <div class="row mb-4">
      @foreach($event->eventCategories as $k => $category_event)

      <div class="col-12 col-md-4">
          <div class="card shadow-none bg-transparent border border-primary mb-2">
              <div class="card-header {{count($category_event->positions) > 0 ? 'bg-label-success':'noooo'}}">
                  <h5>{{$category_event->category->name}}</h5>
              </div>

              <div class="card-body ">
                  <form class="subitScoresForm">
                      <input type="hidden" name="category_event" value="{{$category_event->id}}">
                      <div class="col-12 m-2">

                          <ul class="">

                            
                              @foreach($category_event->registrations as $key => $reg)


                              <li data-categoryevent="{{$category_event->id}}" value="{{$reg->players[0]->id}}" class="list-group-item">
                                  <span class="row">
                                      <span class="col-6"> <span class="number"></span><span> {{$reg->players[0]->name}} {{$reg->players[0]->surname}}</span></span>
                                      <span class="col-6">

                                          <span><input type="text" class="form-control" placeholder="score" name="rrscore[]" value="{{$reg->players[0]->positions($category_event->id) ? $reg->players[0]->positions($category_event->id)->round_robin_score : 0 }}"></span>

                                      </span>

                                  </span>
                                  <input type="hidden" name="order[]" value="{{$reg->players[0]->id}}">

                              </li>
                              @endforeach

                             
                          </ul>

                      </div>
                  </form>
                  <div class="btn btn-secondary btn-sm submitScoreButton">Submit Scores</div>

              </div>
          </div>


      </div>

      @endforeach
  </div>
  <!-- /Multiple Lists Draggable ends -->