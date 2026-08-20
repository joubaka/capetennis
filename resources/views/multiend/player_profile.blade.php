<div class="col-xl-12">
    @php
        $eventRegistrations = $player->registrations->filter(fn ($registration) => $registration->categoryEvents->isNotEmpty());
        $activeViolationCount = isset($violations) ? $violations->where('is_expired', false)->count() : 0;
    @endphp
    <div class="planning-header mb-3">
        <span class="badge bg-label-primary mb-2"><i class="ti ti-user-check me-1"></i>Player record</span>
        <h3 class="mb-1">{{ isset($violations) ? 'Events & discipline' : 'Registered events' }}</h3>
        <p class="text-muted mb-0">
            {{ isset($violations)
                ? "Review event registrations and manage {$player->full_name}'s disciplinary history."
                : "Review {$player->full_name}'s event registration history." }}
        </p>
    </div>
    <div class="nav-align-top mb-4 player-planning-card card">
        <div class="card-header pb-0">
        <div class="planning-tabs-wrap" aria-label="Player planning sections">
        <ul class="nav nav-pills planning-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-events" aria-controls="navs-pills-top-events" aria-selected="true">
                    <i class="ti ti-calendar-event me-1"></i>Registered events
                    <span class="badge bg-label-primary ms-1">{{$eventRegistrations->count()}}</span>
                </button>
            </li>
            @isset($violations)
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-top-violations" aria-controls="navs-pills-top-violations" aria-selected="false" tabindex="-1">
                    <i class="ti ti-gavel me-1"></i>Violations
                    <span class="badge bg-label-{{ $activeViolationCount > 0 ? 'danger' : 'secondary' }} ms-1">
                        {{ $activeViolationCount }}
                    </span>
                </button>
            </li>
            @endisset
        </ul>
        </div>
        </div>
        <div class="tab-content shadow-none">
            <div class="tab-pane fade" id="navs-pills-top-home" role="tabpanel">
           
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="row g-3">
                    <div class="col-lg-7 col-sm-12">
                        <section class="quick-action-panel" aria-labelledby="goal-actions-title">
                            <span class="badge bg-label-primary mb-3">Goals</span>
                            <h4 id="goal-actions-title" class="mb-1">Set a new goal</h4>
                            <p class="text-muted mb-4">Choose the area you want the player to focus on next.</p>

                            @foreach($goal_themes as $theme)
                                <h6 class="text-uppercase text-muted mt-3 mb-2">{{$theme->theme}} goals</h6>
                                <div class="goal-action-grid">
                                    @foreach($theme->goal_types as $types)
                                        @if($theme->id == 1)
                                            <a href="{{route('create.general.goal', ['id' => $player->id, 'type' => $types->id])}}" class="btn bg-label-primary"><i class="ti ti-target-arrow me-2"></i>{{$types->name}} goal</a>
                                        @elseif($theme->id == 2)
                                            <a href="{{route('create.career.goal', ['id' => $player->id, 'type' => $types->id])}}" class="btn bg-label-warning"><i class="ti ti-trophy me-2"></i>{{$types->name}} goal</a>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </section>
                    </div>
                    <div class="col-lg-5 col-sm-12">
                        <section class="quick-action-panel" aria-labelledby="training-actions-title">
                            <span class="badge bg-label-info mb-3">Training</span>
                            <h4 id="training-actions-title" class="mb-1">Record activity</h4>
                            <p class="text-muted mb-4">Keep the player's development history up to date.</p>
                            <div class="record-action-list">
                                <button type="button" class="btn btn-label-info waves-effect" data-bs-target="#addExersize" data-bs-toggle="modal"><i class="ti ti-activity me-2"></i>Add physical evaluation</button>
                                <button type="button" class="btn btn-label-secondary waves-effect" data-bs-target="#addPractice" data-bs-toggle="modal">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="tf-icons ti-xs me-2 icon icon-tabler icon-tabler-ball-tennis" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                        <path d="M6 5.3a9 9 0 0 1 0 13.4"></path>
                                        <path d="M18 5.3a9 9 0 0 0 0 13.4"></path>
                                    </svg>Add practice session
                                </button>
                            </div>
                        </section>
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
                            <th>Exercise</th>
                            <th>Score</th>
                            <th>100% score</th>
                            <th>Type</th>

                        </thead>
                        <tbody>



                            @foreach($player->exercises as $exersize)

                            <tr>
                                <td>{{$exersize->created_at->format('d M Y')}}</td>
                                <td>{{$exersize->exerciseName->name}}</td>
                                <td>{{$exersize->score}}</td>
                                <td>{{$exersize->exerciseName->max_score}}</td>
                                <td>{{$exersize->exerciseName->exerciseType->name}}</td>
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
            <div class="tab-pane fade active show" id="navs-pills-top-events" role="tabpanel">
                @if($player->subscriptions->count() > 0 || $u->id == 584)
                <div class="col-12">
                    <div class="card shadow-none border-0">
                        <div class="card-header d-flex justify-content-between align-items-center px-0 pt-0">
                            <div>
                                <h5 class="card-title mb-1"><i class="ti ti-calendar-event me-2"></i>Registered events</h5>
                                <p class="text-muted mb-0 small">Events associated with this player profile.</p>
                            </div>
                            <span class="badge bg-label-primary">{{$eventRegistrations->count()}} total</span>
                        </div>
                        <div class="card-body pt-2">
                            @if($eventRegistrations->isNotEmpty())
                            <div class="table-responsive border rounded">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Event</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($eventRegistrations as $registration)
                                        @php $registeredEvent = $registration->categoryEvents->first()?->event; @endphp
                                        <tr>
                                            <td class="text-muted">{{$loop->iteration}}</td>
                                            <td class="fw-medium">
                                                @if($registeredEvent)
                                                    <a href="{{ isset($violations) ? route('event.admin.main', $registeredEvent->id) : route('events.show', $registeredEvent->id) }}"
                                                       class="event-record-link">
                                                        <span>{{$registeredEvent->name}}</span>
                                                        <i class="ti ti-arrow-up-right" aria-hidden="true"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted">Event unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>

                                </table>

                            </div>
                            @else
                                <div class="profile-empty-state">
                                    <span class="profile-empty-icon bg-label-primary"><i class="ti ti-calendar-off"></i></span>
                                    <h6 class="mb-1">No registered events</h6>
                                    <p class="text-muted mb-0">Event registrations will appear here when they are added.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @else
                @include('templates.premium')

                @endcan

            </div>

            {{-- ── Violations Tab Pane ── --}}
            @isset($violations)
            <div class="tab-pane fade" id="navs-pills-top-violations" role="tabpanel">
                <div class="col-12">
                    <div class="card shadow-none border-0">
                        <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center px-0 pt-0">
                            <h5 class="card-title mb-0"><i class="ti ti-gavel me-2"></i>Disciplinary Violations</h5>
                            <a href="{{ route('backend.disciplinary.create', ['player_id' => $player->id]) }}"
                               class="btn btn-sm btn-primary">
                                <i class="ti ti-plus me-1"></i> Record Violation
                            </a>
                        </div>
                        <div class="card-body pt-2">

                            @if(isset($disciplinaryStatus))
                                @php $pts = $disciplinaryStatus['active_points']; $threshold = $disciplinaryStatus['threshold']; @endphp
                                <div class="discipline-summary d-flex flex-wrap align-items-center gap-3 mb-3">
                                    <div>
                                        <span class="fw-semibold">Active Points:</span>
                                        <span class="badge bg-{{ $pts >= $threshold ? 'danger' : ($pts > 0 ? 'warning' : 'success') }} ms-1">
                                            {{ $pts }} / {{ $threshold }}
                                        </span>
                                    </div>
                                    @if($disciplinaryStatus['suspended'])
                                        <span class="badge bg-danger">
                                            <i class="ti ti-ban me-1"></i>Suspended until {{ $disciplinaryStatus['suspension_ends_at'] }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @if(isset($violations) && $violations->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Penalty</th>
                                                <th>Points</th>
                                                <th>Status</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($violations as $v)
                                                <tr class="{{ $v->is_expired ? 'text-muted' : '' }}">
                                                    <td>{{ $v->violation_date->format('d M Y') }}</td>
                                                    <td>
                                                        <span class="badge bg-label-{{ match($v->violationType->category ?? '') {
                                                            'on_court'   => 'warning',
                                                            'withdrawal' => 'info',
                                                            'no_show'    => 'danger',
                                                            'abuse'      => 'danger',
                                                            default      => 'secondary'
                                                        } }}">
                                                            {{ $v->violationType->name ?? '—' }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $v->penalty_type ? ucfirst($v->penalty_type) : '—' }}</td>
                                                    <td><strong>{{ $v->points_assigned }}</strong></td>
                                                    <td>
                                                        @if($v->is_expired)
                                                            <span class="badge bg-secondary">Expired</span>
                                                        @else
                                                            <span class="badge bg-success">Active</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <small>{{ $v->notes ? \Illuminate\Support\Str::limit($v->notes, 60) : '—' }}</small>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('backend.disciplinary.violation.edit', $v->id) }}"
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="profile-empty-state">
                                    <span class="profile-empty-icon bg-label-success"><i class="ti ti-circle-check"></i></span>
                                    <h6 class="mb-1">No violations recorded</h6>
                                    <p class="text-muted mb-0">This player currently has a clear disciplinary record.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endisset
            {{-- /Violations Tab Pane --}}

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
                <h5 class="modal-title" id="exampleModalLabel">Add physical evaluation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="addExersize" action="{{route('evaluation.store')}}" method="POST">
                <div class="modal-body">
                    <div class="mb-3 ">
                        <label for="html5-date-input" class="form-label">Date</label>

                        <input name="date" class="form-control" type="date" value="{{Carbon\Carbon::now()->format('Y-m-d')}}" id="html5-date-input">

                    </div>
                </div>
                <div id="content">
                    <div class="modal-body">
                        @csrf
                        <div class="addExersizeBody">
                            <div class="row">
                                <div class="mb-3 col-6">
                                    <label for="select2Basic" class="form-label ">Exercise type</label>

                                    <select name="exersize[]" class="select2 form-select form-select-lg" data-allow-clear="true">
                                        @foreach($physical_exersizes as $exersize)
                                        <option value="{{$exersize->id}}">{{$exersize->name}}</option>
                                        @endforeach
                                    </select>

                                </div>


                                <div class="mb-3 col-6">

                                    <label for="html5-number-input" class="form-label">Score</label>

                                    <input name="score[]" class="form-control" type="number" value="18" id="html5-number-input">

                                    <input type="hidden" name="player_id" value="{{$player->id}}">
                                </div>

                            </div>


                        </div>

                    </div>

                </div>

                <div class="addAnotherExersize btn bg-label-success ms-4" id="addAnotherButton">Add another evaluation</div>
                <div class="modal-footer">

                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save evaluations</button>
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
                <h5 class="modal-title" id="exampleModalLabel">Add practice session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form action="{{route('practice.store')}}" method="POST" id="practiceStoreForm">
                <div class="modal-body">
                    <div class="col-6">
                        <div class="mb-3 ">
                            <label for="html5-date-input" class="form-label">Date</label>

                            <input name="date" class="form-control" type="date" value="{{Carbon\Carbon::now()->format('Y-m-d')}}" id="html5-date-input">

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
