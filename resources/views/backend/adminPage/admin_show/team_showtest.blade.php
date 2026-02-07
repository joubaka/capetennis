<div class="col-xl-12 ">
    <h3>Team Event: {{$event->name}}</h3>

    <div class="col-xl-12">

        <div class="nav-align-top nav-tabs-shadow mb-4">
            <ul class="nav nav-tabs nav-fill" role="tablist">
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-home" aria-controls="navs-justified-home" aria-selected="true"><i class="tf-icons ti ti-home ti-xs me-1"></i> Regions <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-label-danger ms-1">{{$event->regions->count()}}</span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-profile" aria-controls="navs-justified-profile" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-user ti-xs me-1"></i> Teams in Event </span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-categories" aria-controls="navs-justified-profile" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-user ti-xs me-1"></i> Categories </span></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-messages" aria-controls="navs-justified-messages" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i> Players</button>
                </li>

                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-order" aria-controls="navs-justified-messages" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i> Player order</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="result-rank-button" type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-resultRank" aria-controls="navs-justified-resultRank" aria-selected="false" tabindex="-1"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i> Result Ranks</button>
                </li>
                @if (Auth::id() === 584 )
                <li class="nav-item" role="presentation">
                    <a href="{{route('headOffice.show',$event->id)}}" type="button" class="nav-link"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i>Dashboard </a>
                </li>
                @endif
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade " id="navs-justified-home" role="tabpanel">
                    <div class="demo-inline-spacing mt-3">
                        <ul class="list-group regionList">
                            @if($event->regions->count() == 0)
                            <div class="alert alert-primary noRegions" role="alert">
                                No Regions added to event
                            </div>

                            @else

                            @foreach($event->regions as $region)
                            <li class="list-group-item d-flex align-items-center">

                                {{$region->region_name}}
                                <a href="javascript:void(0)" class="ms-2 removeRegionEvent" data-id="{{$region->pivot->id}}">
                                    <i class="ti ti-minus ti-sm me-2 bg-label-danger rounded-pill"></i>Delete Region
                                </a>
                            </li>
                            @endforeach

                            @endif
                        </ul>

                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-target="#modalToggle" data-bs-toggle="modal">
                            <span class="ti-xs ti ti-star me-1"></span>Add Region to event
                        </button>
                    </div>
                </div>
                <div class="tab-pane fade" id="navs-justified-profile" role="tabpanel">
                    <div class="col-8"></div>
                    <div class="demo-inline-spacing mt-3">
                        <ul class="list-group regionList">
                            @if($event->regions->count() == 0)
                            <div class="alert alert-primary noRegions" role="alert">
                                No Regions added to event
                            </div>

                            @else

                            @foreach($event->regions as $region)
                            <li class="list-group-item  ">

                                <span class="badge bg-label-info m-2">{{$region->region_name}} Teams</span>

                                <div>
                                    <ul class="list-group team-list">
                                        @if($region->teams->count() == 0)
                                        <div class=" m-2 alert alert-primary" role="alert">
                                            No teams add to region!
                                        </div>
                                        @else
                                        @foreach($region->teams as $team)
                                        <li class="list-group-item">{{$team->name}}
                                            <a href="javascript:void(0)" class="ms-2 publishTeam" data-state="{{$team->published == 0 ? '0':'1'}}" data-id="{{$team->id}}">
                                                {!!$team->published == 0 ? '<span class="badge bg-label-success">Publish Team<span>':'<span class="badge bg-label-danger">Unpublish Team</span>'!!}
                                            </a>


                                            <a href="javascript:void(0)" class="ms-2 removeTeam" data-id="{{$team->id}}">
                                                <i class="ti ti-minus ti-sm me-2 bg-label-danger rounded-pill"></i>Delete team
                                            </a>

                                            <p><span class="category-{{$team->id}}"> Category: {{$team->category ? $team->category->category->name:'None'}}</span><br>
                                                <button data-id="{{$team}}" class="btn btn-sm bg-label-info edit-team-category" data-bs-toggle="modal" data-bs-target="#edit-team-category-modal">Edit Category</button>
                                            </p>

                                        </li>
                                        @endforeach

                                        @endif
                                    </ul>
                                </div>
                                <a data-regionid="{{$region->id}}" data-bs-target="#addTeamModal" data-bs-toggle="modal" href="javascript:void(0)" class="m-2 btn btn-primary btn-xs addTeam" data-id="{{$region->id}}">
                                    <i class="ti ti-plus ti-sm me-2 bg-label-success rounded-pill"></i> Add Team
                                </a>
                            </li>
                            @endforeach

                            @endif
                        </ul>

                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-target="#modalToggle" data-bs-toggle="modal">
                            <span class="ti-xs ti ti-star me-1"></span>Add Region to event
                        </button>
                    </div>


                </div>
                <div class="tab-pane fade" id="navs-justified-categories" role="tabpanel">
                    <div class="col-8">
                        <button class="btn btn-primary-small" id="add-category-button" data-bs-toggle="modal" data-bs-target="#add-category-modal">Add Category</button>
                    </div>
                    <div class="demo-inline-spacing mt-3">
                        <ul class="list-group regionList">
                            @if($event->eventCategories->count() == 0)
                            <div class="alert alert-primary noRegions" role="alert">
                                No Categories added to event
                            </div>

                            @else
                            <ul class="list-group">
                                @foreach($event->eventCategories as $category)
                                <li class="list-group-item"> {{$category->category->name}} {{$category->id}}</li>
                                @endforeach
                            </ul>





                            @endif
                        </ul>


                    </div>


                </div>
                <div class="tab-pane fade" id="navs-justified-messages" role="tabpanel">
                    <div class="nav-align-top nav-tabs-shadow mb-4">

                        <ul class="nav nav-tabs" role="tablist">
                            @foreach($event->region_in_events as $key => $region)
                            <li class="nav-item" role="presentation">
                                <button type="button" class="nav-link {{$key == 0 ? 'active':''}}" role="tab" data-bs-toggle="tab" data-bs-target="#teamOrder{{$region->id}}" aria-controls="{{$region->id}}" aria-selected="{{$key == 0 ? 'true':''}}">{{$region->region_name}}</button>
                            </li>
                            @endforeach



                        </ul>
                        <div class="tab-content">
                            @foreach($event->region_in_events as $key=> $region)
                            <div class="tab-pane fade {{$key == 0 ? 'active show':''}}" id="teamOrder{{$region->id}}" role="tabpanel">
                                <div class="card-body">

                                    <div class="col-md-12">
                                        <div class=" d-flex justify-content-between pb-2 mb-1">

                                            <div class="dropdown">
                                          
                                                <button class="btn p-0" type="button" id="salesByCountryTabs" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <span class="btn btn-primary btn-sm"> <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                                </button>
                                               
                                                <a class="btn btn-success" href="{{route('team.import.view')}}">Import Player Sheet</a>
                                                
                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('region','{{$region->id}}')">Send e-mail to all players in region</a>
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('unregistered_event','{{$region->id}}')">Send e-mail to all UNREGISTERED players in region</a>
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('event','{{$event->id}}')">Send e-mail to all players in event</a>
                                                </div>


                                                <a href="{{route('region.clothing.order',$region->id)}}" class="btn btn-info">Clothing Orders</a>
                                            </div>
                                        </div>


                                        <div class="row">

                                            @foreach($region->teams as $team)
                                            @if(!$team->noProfile == 1)
                                                @include('backend.adminPage.partials.team-profile')
                                            @else

                                            @include('backend.adminPage.partials.team-no-profile')
                                            @endif
                                         
                                            @endforeach





                                        </div>
                                    </div>


                                </div>
                            </div>
                            @endforeach

                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="navs-justified-order" role="tabpanel">
                    <div class="nav-align-top nav-tabs-shadow mb-4">
                        <ul class="nav nav-tabs" role="tablist">
                            @foreach($event->region_in_events as $key => $region)
                            <li class="nav-item" role="presentation">
                                <button type="button" class="nav-link {{$key == 0 ? 'active':''}}" role="tab" data-bs-toggle="tab" data-bs-target="#team{{$region->id}}" aria-controls="{{$region->id}}" aria-selected="{{$key == 0 ? 'true':''}}">{{$region->region_name}}</button>
                            </li>
                            @endforeach



                        </ul>
                        <div class="tab-content">
                            @foreach($event->region_in_events as $key=> $region)
                            <div class="tab-pane fade {{$key == 0 ? 'active show':''}}" id="team{{$region->id}}" role="tabpanel">
                                <div class="card-body">

                                    <div class="col-md-12">
                                        <div class=" d-flex justify-content-between pb-2 mb-1">

                                            <div class="dropdown">
                                                <button class="btn p-0" type="button" id="salesByCountryTabs" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <span class="btn btn-primary btn-sm"> <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                                    <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('region','{{$region->id}}')">Send e-mail to all players in region</a>


                                                </div>
                                            </div>
                                        </div>


                                        <div class="row">

                                            @foreach($region->teams as $team)
                                            <div class="col-12">
                                                <div class="card-header mb-0">
                                                    <h5 class="m-0 me-2 m-4"> {{$team->name}}</h5><span>
                                                        <div class="dropdown">
                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i>Options</button>
                                                            <div class="dropdown-menu">
                                                                <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="team" onclick="changeRecipants('team','{{$team->id}}')">Send e-mail to all players in {{$team->name}}</a>

                                                            </div>
                                                        </div>
                                                    </span>


                                                </div>

                                                <div class="card-body">
                                                    @if(!$team->published == 1)

                                                    <div class="mt-4 alert alert-danger" role="alert">
                                                        Team not yet published!
                                                    </div>
                                                    @endif
                                                    <div class="table-responsive">
                                                        <table class="table">
                                                            <thead>
                                                                <th>Nr</th>
                                                                <th>Name</th>
                                                                <th>Email</th>
                                                                <th>Cell</th>
                                                                <th>Pay Status</th>
                                                                <th>Actions</th>
                                                            </thead>

                                                            <tbody class="sortablePlayers">
                                                                @php

                                                                $members = $team->players ;


                                                                @endphp


                                                                @foreach($members as $i => $member)
                                                                <tr class="row-{{$member->pivot->id}} drag-item" data-playerteamid="{{$member->pivot->id}}">
                                                                    <td><span class="badge bg-label-primary">{{$i+1}}</span></td>
                                                                    <td class="name"> {{$member->id == 1248 ? '':$member->name}} {{$member->id == 1248 ? 'No Player':$member->surname}}</td>
                                                                    <td class="email"> {{$member->id == 1248 ?  '':$member->email}}</td>
                                                                    <td class="cellNr"> {{$member->id == 1248 ?  '':$member->cellNr}}</td>
                                                                    <td>{!!$member->pivot->pay_status == 1 ? '<span class="badge bg-label-success">Paid</span>':'<span class="badge bg-label-danger">Not Paid</span>'!!}</td>
                                                                    <td>
                                                                        <div class="dropdown listDropdown">
                                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                                                            <div class="dropdown-menu">

                                                                                <a class="dropdown-item insertPlayer" href="javascript:void(0);" data-pivot="{{$member->pivot->id}}" data-position="{{($i+1)}}" data-teamid="{{$team->id}}" data-bs-target="#insert-player-team-modal" data-bs-toggle="modal"><i class="ti ti-insert me-1"></i> Replace Player</a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                @endforeach




                                                            </tbody>







                                                        </table>
                                                    </div>



                                                </div>

                                            </div>
                                            @endforeach





                                        </div>
                                    </div>


                                </div>
                            </div>
                            @endforeach

                        </div>
                    </div>
                </div>

                <div class="tab-pane fade show active" id="navs-justified-resultRank" role="tabpanel">
               
                        <div class="col-2 p-6">
                            <div class="text-light small fw-medium mb-4">Default</div>
                            <div class="switches-stacked">
                                @foreach($event->eventCategories as $category)
                                <label class="switch">
                                    <input type="radio" class="switch-input" name="switches-stacked-radio" data-name='{{$category->category->name}}' data-id ='{{$category->id}}' data-event_id='{{$event->id}}' checked="">
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on"></span>
                                        <span class="switch-off"></span>
                                    </span>
                                    <span class="switch-label">{{$category->category->name}}</span>
                                </label>
                                @endforeach

                            </div>
                        </div>
                        <div class="col-12 p-1">
                            <div class="card" id="rank-table">
                                <div class="card-header"><h4 id="category-name"></h4></div>
                                <div class="card-body" id="category-table">
                                   
                                </div>
                               


                            </div>

                        </div>

                    

                </div>
            </div>



        </div>
