<div class="col-xl-12">
    <h6 class="text-muted">Player planning for: {{$player->full_name}}</h6>
    <div class="nav-align-top mb-4">
        <ul class="nav nav-pills mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-home" aria-controls="navs-pills-top-home" aria-selected="true">Add</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-profile" aria-controls="navs-pills-top-profile" aria-selected="false" tabindex="-1">Physical Evaluations</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-training" aria-controls="navs-pills-top-training" aria-selected="false" tabindex="-1">Practice Sessions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-charts" aria-controls="navs-pills-top-chars" aria-selected="false" tabindex="-1">Player Analisys</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-messages" aria-controls="navs-pills-top-messages" aria-selected="false" tabindex="-1">Goal Setting</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-practiceMatches" aria-controls="navs-pills-top-practiceMatches" aria-selected="false" tabindex="-1">Practice Matches</button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-events" aria-controls="navs-pills-top-events" aria-selected="false" tabindex="-1">Registered Events</button>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade active show" id="navs-pills-top-home" role="tabpanel">
           
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="row">
                    <div class="col-lg-6 col-sm-12 p-4">
                        <small class="text-light fw-semibold mb-4">Set my goals</small>
                        <div class="demo-inline-spacing">
                            @foreach($goal_themes as $theme)
                            <h3>{{$theme->theme}} Goals</h3>
                            <ul class="d-grid gap-2">

                                @foreach($theme->goal_types as $types)
                                @if($theme->id == 1)
                                <li class="list-group-item"> <a href="{{route('create.general.goal', ['id' => $player->id, 'type' => $types->id])}}" class="btn bg-label-{{$theme->id == 1 ? 'primary':'warning'}}">{{$types->name}} Goal</a></li>
                                @elseif($theme->id == 2)
                                <li class="list-group-item"> <a href="{{route('create.career.goal', ['id' => $player->id, 'type' => $types->id])}}" class="btn bg-label-{{$theme->id == 1 ? 'primary':'warning'}}">{{$types->name}} Goal</a></li>
                                @endif

                                @endforeach



                            </ul>



                            @endforeach






                        </div>
                    </div>
                    <div class="col-lg-6 col-sm-12 p-4">

                        <div class="demo-inline-spacing">
                            <small class="text-light fw-semibold mb-4">Add</small>
                            <h3>Exersizes</h3>


                            <button type="button" class="btn btn-label-linkedin waves-effect" data-bs-target="#addExersize" data-bs-toggle="modal"><i class="ms-1 tf-icons fa-solid fa-star ti-xs me-1"></i> Add Physical Exersize result</button>
                            <button type="button" class="btn btn-label-github waves-effect" data-bs-target="#addPractice" data-bs-toggle="modal">
                                <svg xmlns="http://www.w3.org/2000/svg" class="tf-icons ti-xs me-1 icon icon-tabler icon-tabler-ball-tennis" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                    <path d="M6 5.3a9 9 0 0 1 0 13.4"></path>
                                    <path d="M18 5.3a9 9 0 0 0 0 13.4"></path>
                                </svg> Add Practice</button>
                        </div>

                    </div>


                </div>

                @else
                @include('templates.premium')

                @endif






            </div>
            <div class="tab-pane fade" id="navs-pills-top-profile" role="tabpanel">
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="table-responsive text-nowrap">
                    <table class="table ">
                        <thead>
                            <th>Date</th>
                            <th>Exerize</th>
                            <th>Score</th>
                            <th>100% score</th>
                            <th>Type</th>

                        </thead>
                        <tbody>



                            @foreach($player->exersizes as $exersize)

                            <tr>
                                <td>{{$exersize->created_at->format('d M Y')}}</td>
                                <td>{{$exersize->exersizeName->name}}</td>
                                <td>{{$exersize->score}}</td>
                                <td>{{$exersize->exersizeName->max_score}}</td>
                                <td>{{$exersize->exersizeName->exersize_type->name}}</td>
                            </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                @include('templates.premium')

                @endif
            </div>

            <div class="tab-pane fade" id="navs-pills-top-messages" role="tabpanel">
                <!--  Goal setting tabs -->
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="col-lg-12">

                    <div class="demo-inline-spacing mt-3">
                        <div class="list-group list-group-horizontal-md text-md-center" role="tablist">
                            <a class="list-group-item list-group-item-action active" id="home-list-item" data-bs-toggle="list" href="#horizontal-home" aria-selected="true" role="tab">General Short Term Goals</a>
                            <a class="list-group-item list-group-item-action" id="profile-list-item" data-bs-toggle="list" href="#horizontal-profile" aria-selected="false" role="tab" tabindex="-1">Career Goals</a>
                            <!--                             <a class="list-group-item list-group-item-action" id="messages-list-item" data-bs-toggle="list" href="#horizontal-messages" aria-selected="false" role="tab" tabindex="-1">Training Feedback</a>
                            <a class="list-group-item list-group-item-action" id="settings-list-item" data-bs-toggle="list" href="#horizontal-settings" aria-selected="false" role="tab" tabindex="-1">Matches Feedback</a> -->
                        </div>
                        <div class="tab-content px-0 mt-0">
                            <div class="tab-pane fade active show" id="horizontal-home" role="tabpanel" aria-labelledby="#home-list-item">
                                <div class="col-xl-12 col-md-12 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between">
                                            <div class="card-title mb-0">
                                                <h4 class="mb-0">My General Short Term Goals</h4>
                                                <small class="text-muted"></small>
                                            </div>

                                        </div>




                                        <div class="card-body">

                                            @foreach($general_goal_types as $goal)

                                            <div class="row  mt-4">
                                                <div class="col-sm-12  col-md-10 mb-4 mb-xl-0">

                                                    <div class="demo-inline-spacing mt-3 ">

                                                        <div class="row goalList{{$goal->id}}" data-id="{{$goal->id}}">
                                                            <h6>{{$goal->names[0]->goal_type->name}} Goal</h6>
                                                            <div class="col-12 border border-primary p-5">
                                                                <ol class="list-group list-group-numbered">

                                                                    @foreach($goal->names as $value)

                                                               
                                                                    <li class="mb-3 pb-1 ">



                                                                        <div class="row">

                                                                            <div class="col-1 badge bg-label-primary me-3 rounded p-2">
                                                                                <i class="fa-solid fa-trophy"></i>
                                                                            </div>
                                                                            <div class=" col-10 ">
                                                                                <div class="row">
                                                                                    <div class="col-12 col-md-4">
                                                                                    <h6 class="mb-0">Improve my {{$value->name}}</h6>

                                                                                    </div>
                                                                                    <div class="col-12 col-md-8">
                                                                                    <h6 class="mb-0 badge bg-label-warning">{{ \Carbon\Carbon::parse($goal->endDate)->diffForHumans()}} by {{\Carbon\Carbon::parse($goal->endDate)->format('d M Y')}}</h6>
                                                                                    </div>

                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                    </li>
                                                                    @endforeach

                                                                </ol>
                                                            </div>

                                                        </div>




                                                    </div>

                                                </div>


                                            </div>
                                            @endforeach
                                        </div>


                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="horizontal-profile" role="tabpanel" aria-labelledby="#home-list-item">
                                <div class="col-sm-12 col-md-12 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between">
                                            <div class="card-title mb-0">
                                                <h4 class="mb-0">My Career Goals</h4>
                                                <small class="text-muted"></small>
                                            </div>

                                        </div>




                                        <div class="card-body">

                                            @foreach($career_goal_types as $goal)

                                            <div class="row  mt-4">
                                                <div class="col-sm-12  col-md-10 mb-4 mb-xl-0">

                                                    <div class="demo-inline-spacing mt-3 ">

                                                        <div class="row goalList{{$goal->id}}" data-id="{{$goal->id}}">
                                                            <h6>{{$goal->names[0]->goal_type->name}} Goal</h6>
                                                            <div class="col-12 border border-primary p-5">
                                                                <ol class="list-group list-group-numbered">

                                                                    @foreach($goal->names as $value)

                                                                    <li class="mb-3 pb-1 ">



                                                                        <div class="row">

                                                                            <div class="col-1 badge bg-label-primary me-3 rounded p-2">
                                                                                <i class="fa-solid fa-trophy"></i>
                                                                            </div>
                                                                            <div class=" col-10 ">
                                                                                <div class="row">
                                                                                    <div class="col-12 col-md-4">
                                                                                        <h6 class="mb-0">Achieve {{$value->name}}</h6>

                                                                                    </div>
                                                                                    <div class="col-12 col-md-8">
                                                                                        <h6 class="mb-0 badge bg-label-warning">{{ \Carbon\Carbon::parse($goal->endDate)->diffForHumans()}} by {{\Carbon\Carbon::parse($goal->endDate)->format('d M Y')}}</h6>
                                                                                    </div>

                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                    </li>
                                                                    @endforeach

                                                                </ol>
                                                            </div>

                                                        </div>




                                                    </div>

                                                </div>


                                            </div>

                                            @endforeach
                                        </div>


                                    </div>

                                </div>
                            </div>
                            <div class="tab-pane fade" id="horizontal-messages" role="tabpanel" aria-labelledby="#messages-list-item">






                            </div>
                            <div class="tab-pane fade" id="horizontal-settings" role="tabpanel" aria-labelledby="#settings-list-item">
                                Parent/coach
                            </div>
                        </div>
                    </div>
                </div>
                @else
                @include('templates.premium')

                @endcan

            </div>
            <div class="tab-pane fade" id="navs-pills-top-training" role="tabpanel">
                <!--  Goal setting tabs -->
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="table-responsive text-nowrap">
                    <table class="table ">
                        <thead>
                            <th>Date</th>
                            <th>Practice Type</th>
                            <th>Duration</th>



                        </thead>
                        <tbody>



                            @foreach($player->practices as $value)
                            <tr>
                                <td>{{$value->created_at->format('d M y')}}</td>
                                <td>{{$value->practice_type->practice_type}}</td>
                                <td>{{$value->duration->duration}} mins</td>

                            </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                @include('templates.premium')

                @endcan

            </div>
            <div class="tab-pane fade" id="navs-pills-top-charts" role="tabpanel">
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <h3>Practice</h3>
                <div class="card">
                    <div id="lineAreaChart"></div>

                </div>
                <h3>Physical</h3>
                <div class="card">
                    <div id="physicalChart"></div>

                </div>
                @else
                @include('templates.premium')

                @endcan
            </div>
            <div class="tab-pane fade" id="navs-pills-top-practiceMatches" role="tabpanel">
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="col-lg-12 mb-4 col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h5 class="card-title mb-0">Matches</h5>
                            <small class="text-muted"></small>
                        </div>
                        <div class="card-body pt-2">
                            <div class="row gy-3">
                                <div class="col-md-3 col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge rounded-pill bg-label-primary me-3 p-2"><i class="fa-solid fa-user"></i></div>
                                        <div class="card-info">
                                            <h5 class="mb-0">{{$player->practiceMatches->count()}}</h5>
                                            <small>Practice Matches</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge rounded-pill bg-label-info me-3 p-2"><i class="fa-solid fa-arrow-up"></i></div>
                                        <div class="card-info">
                                            <h5 class="mb-0">{{$totsets}}</h5>
                                            <small>Sets played</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge rounded-pill bg-label-success me-3 p-2"><i class="fa-solid fa-check"></i></div>
                                        <div class="card-info">
                                           
                                            <h5 class="mb-0">{{$setswon->count()}}</h5>
                                            <small>Sets won</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge rounded-pill bg-label-danger me-3 p-2"><i class="fa-solid fa-xmark"></i></div>
                                        <div class="card-info">
                                            <h5 class="mb-0">{{$setslost->count()}}</h5>
                                            <small>Sets lost</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table ">
                        <thead>
                            <th>Date</th>
                            <th>Opponent</th>
                            <th>Score</th>


                        </thead>
                        <tbody>



                            @foreach($player->practiceMatches as $value)
                            <tr>
                                <td>{{$value->practice->date_of_lesson}}</td>
                                <td>

                                    @if($value->registration2_id > 0)
                                    {{$value->registration1_id == $player->id ? $value->player2->full_name:$value->registration1_id}}
                                    @else
                                    {{isset($value->noProfile) ? $value->noProfile->full_name:'No Name'}}
                                    @endif

                                </td>
                                <td>
                                    @foreach($value->results as $result)
                                    <span class="badge bg-label-{{$result->registration1_score > $result->registration2_score ? 'success':'danger'}}">{{$result->registration1_score}} - {{$result->registration2_score}}</span>

                                    @endforeach

                                </td>

                            </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                @include('templates.premium')

                @endcan

            </div>
            <div class="tab-pane fade" id="navs-pills-top-events" role="tabpanel">
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="col-lg-12 mb-4 col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <h5 class="card-title mb-0">Events</h5>
                            <small class="text-muted"></small>
                        </div>
                        <div class="card-body pt-2">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Event</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php

                                        $count = 0;
                                        @endphp
                                        @foreach($player->registrations as $key => $registration)

                                        @if($registration->categoryEvents->count() > 0)
                                        @php

                                        $count += 1;
                                        @endphp
                                        <tr>
                                            <td>{{$count}}</td>
                                            <td>{{$registration->categoryEvents->count() > 0 ? $registration->categoryEvents[0]->event->name:''}}</td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </tbody>

                                </table>

                            </div>
                        </div>
                    </div>
                </div>

                @else
                @include('templates.premium')

                @endcan

            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal animate__animated animate__flipInX" id="flipInXAnimationModal" tabindex="-1" aria-labelledby="flipInXAnimationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Modal title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <form id="add_goal_form">
                    <div class="row">
                        <div class="mb-3 col">
                            <label for="info" class="form-label" id="goal">Name</label>
                            <textarea name="info" id="info" class="form-control" placeholder="Type goal here...."></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="player_id" id="player_id">
                    <input type="hidden" name="goal_type_id" id="goal_type_id">

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="addGoalButton" class="addGoalButton btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addExersize" tabindex="-2" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Exersize</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="addExersize" action="{{route('evaluation.store')}}" method="POST">
                <div class="modal-body">
                    <div class="mb-3 ">
                        <label for="html5-date-input" class="form-label">Date</label>

                        <input name="date" class="form-control" type="date" value="{{Carbon\Carbon::now()->format('y-m-d')}}" id="html5-date-input">

                    </div>
                </div>
                <div id="content">
                    <div class="modal-body">
                        @csrf
                        <div class="addExersizeBody">
                            <div class="row">
                                <div class="mb-3 col-6">
                                    <label for="select2Basic" class="form-label ">Please select Exersize Type</label>

                                    <select name="exersize[]" class="select2 form-select form-select-lg" data-allow-clear="true">
                                        @foreach($physical_exersizes as $exersize)
                                        <option value="{{$exersize->id}}">{{$exersize->name}}</option>
                                        @endforeach
                                    </select>

                                </div>


                                <div class="mb-3 col-6">

                                    <label for="html5-number-input" class="form-label">Number of</label>

                                    <input name="score[]" class="form-control" type="number" value="18" id="html5-number-input">

                                    <input type="hidden" name="player_id" value="{{$player->id}}">
                                </div>

                            </div>


                        </div>

                    </div>

                </div>

                <div class="addAnotherExersize btn bg-label-success" id="addAnotherButton">Add Another</div>
                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="addPractice" tabindex="-2" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Practice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form action="{{route('practice.store')}}" method="POST" id="practiceStoreForm">
                <div class="modal-body">
                    <div class="col-6">
                        <div class="mb-3 ">
                            <label for="html5-date-input" class="form-label">Date</label>

                            <input name="date" class="form-control" type="date" value="{{Carbon\Carbon::now()->format('y-m-d')}}" id="html5-date-input">

                        </div>
                    </div>
                    @csrf


                    <div class="col-6">
                        <div class="mb-3">
                            <label for="select2Basic" class="form-label">Please select practice type</label>
                            <select name="practice_type_id" id="select2Practice" class="select2Practice form-select form-select-lg" data-allow-clear="true">
                                <option value="0" selected>Please Select</option>
                                @foreach($practice_types as $value)
                                <option value="{{$value->id}}">{{$value->practice_type}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div id="score" class="d-none m-2 p-2">
                            <div class="mb-3">
                                <div class="row alert-success ">
                                    <div id="p1" class="col-3">
                                        player 1

                                    </div>
                                    <span class="col-1"> VS </span>
                                    <div id="p2" class=" col-3">

                                        player 2
                                    </div>
                                    <div id="matchScore" class="col-5">
                                        score
                                    </div>

                                </div>
                            </div>

                        </div>

                    </div>


                    <div class="col-6">
                        <div class="mb-3">
                            <label for="select2Basic" class="form-label">Please select practice duration</label>
                            <select name="duration_id" id="select2Duration" class="select2Duration form-select form-select-lg" data-allow-clear="true">
                                @foreach($durations as $value)
                                <option value="{{$value->id}}">{{$value->duration}} mins</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="player_id" value="{{$player->id}}" id="">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Practice</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal -->
<div class="modal fade" id="scoreModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Record Score</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="col-xxl">
                    <div class="card mb-4">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Insert details below</h5>
                        </div>
                        <div class="card-body">
                            <form id="scoreForm">
                                <div class="row mb-3">
                                    <label class="col-sm-2 col-form-label" for="basic-icon-default-fullname" id="player1">Player 1</label>
                                    <div class="col-sm-10">
                                        <div class="input-group input-group-merge">
                                            <span id="basic-icon-default-fullname2" class="input-group-text"><i class="ti ti-user"></i></span>
                                            <input id="player1" type="text" data-id="" readonly class="form-control player1name" placeholder="John Does" aria-label="John Doe" aria-describedby="basic-icon-default-fullname2">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-2 col-form-label" for="basic-icon-default-fullname" id="player1">Player 2</label>
                                    <div class="col-sm-10">
                                        <select name="practice_type_id" id="select2player2" class=" form-select form-select-lg" data-allow-clear="true">
                                            <option value="0" selected disabled>Please Select</option>
                                            @foreach($players as $value)
                                            <option value="{{$value->id}}">{{$value->full_name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <h6>Score</h6>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="btn btn-secondary btn-sm" id="addSet">Add Set</div>
                                    </div>
                                    <div class="scoreContent row ">

                                    </div>



                                </div>


                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-target="#addPractice" data-bs-toggle="modal">Close</button>
                    <button type="submit" id="continueButton" class="btn btn-primary" onclick="updateExersizeModal(this)">Continue</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-10 d-none " id="setData">
    <div class="card">
        <div class="card-header">
            <div class="mt-3">Set # <span id="setNr"></span></div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-6 col-form-label player1nameScore">Player 1</label>
                <div class="col-sm-6">
                    <input name="setplayer1[]" type="text" class="form-control " placeholder="">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-6 col-form-label player2nameScore" for="basic-icon-default-fullname" id="player2Score"></label>
                <div class="col-sm-6">
                    <input name="setplayer2[]" type="text" class="form-control " placeholder="">
                </div>
            </div>
        </div>
    </div>


</div>

<!-- Modal -->
<div class="modal fade" id="noProfileModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Record Score</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="col-xxl">
                    <div class="mb-3">
                        <label for="defaultFormControlInput" class="form-label">Name</label>
                        <input name="noProfileName" type="text" class="form-control" id="noProfileName" placeholder="John Doe" />

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-bs-target="#addPractice" data-bs-toggle="modal">Close</button>
                    <button type="submit" id="continueButton" class="btn btn-primary" onclick="updateNoProfile(this)">Continue</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    var player = "{{$player->full_name}}",
        player_id = "{{$player->id}}"
</script>