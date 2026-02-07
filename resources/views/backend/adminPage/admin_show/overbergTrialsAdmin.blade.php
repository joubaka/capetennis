<div class="card ">
    <div class="card-header event-header">
        <h3 class="text-center"> {{$event->name}} </h3>
    </div>


    <div class="row mt-4">
        <div class="card-body m-4 ">
            <!-- Navigation -->
            <div class="col-lg-12 col-md-12 col-12 mb-md-0 mb-3">
                <div class="d-flex justify-content-between flex-column mb-2 mb-md-0">
                    <div class="row">

                        <ul class="nav nav-pills mb-3">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#payment">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Entries</span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#results">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Results</span>
                                </button>
                            </li>


                            @can('super-user')
                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#transactions">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Transactions</span>
                                </button>
                            </li>

                            @endcan

                            @can('super-user')
                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#draws">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Draws</span>
                                </button>
                            </li>

                            @endcan
                        </ul>

                        <div class="d-none d-md-block">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Navigation -->

        <!-- FAQ's -->
        <div class="col-lg-112 col-md-12 col-12">
            <div class="tab-content py-0">
                <div class="tab-pane fade show active" id="payment" role="tabpanel">
                    <div class="d-flex mb-3 gap-3">

                        <div>
                            <h4 class="mb-0 mt-4">
                                <span class="align-middle">Entries</span>
                            </h4>
                            <small>Player entered in {{$event->name}}</small>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-12 col-xl-12 mb-4">
                        <div class=" h-100  shadow-none bg-transparent ">
                            <div class=" d-flex justify-content-between pb-2 mb-1">

                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="salesByCountryTabs" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="btn btn-primary btn-sm"> <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                        <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('event','{{$event->id}}')">Send e-mail to all players in event</a>
                                        <a class="dropdown-item" href="{{route('export.registrations',$event->id)}}" data-event="{{$event->id}}">Export entry list</a>

                                    </div>
                                </div>
                            </div>
                            <div class="shadow-none bg-transparent">
                                <div class="nav-align-top">
                                    <ul class="nav nav-tabs nav-fill" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button type="button" class="nav-link active btn btn-success text-black" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-new" aria-controls="navs-justified-new" aria-selected="true">Confirmed</button>
                                        </li>
                                        <li class=" nav-item" role="presentation">
                                            <button type="button" class="nav-link btn btn-danger text-black" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-link-preparing" aria-controls="navs-justified-link-preparing" aria-selected="false" tabindex="-1">Withdrawals</button>
                                        </li>

                                    </ul>
                                    <div class="tab-content pb-0">
                                        <div class="tab-pane fade active show" id="navs-justified-new" role="tabpanel">
                                            @foreach($eventCategories as $key => $categories)

                                            <div class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                <div class="card-header">
                                                    <h3>{{$categories->category->name}}</h3><button type="button" data-bs-target="#addPlayerToCategory" data-bs-toggle="modal" data-categoryeventid="{{$categories->id}}" class="btn btn-success btn-sm addPlayerC">Add Player</button>
                                                </div>

                                                <div class="table-responsive text-nowrap mb-4">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Name</th>
                                                                <th>Email</th>
                                                                <th>Contact</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($categories->registrations as $k => $registration)

                                                            <tr>
                                                                <td> <strong>{{$k+1}}</strong></td>
                                                                <td>{{$registration->players[0]->name}} {{$registration->players[0]->surname}}</td>
                                                                <td>
                                                                    {{$registration->players[0]->email}}
                                                                </td>
                                                                <td><span class="badge bg-label-primary me-1">{{$registration->players[0]->cellNr}}</span></td>
                                                                <td>
                                                                    <span class="btn btn-sm btn-secondary sendEmail" data-bs-target="#createEmail" data-bs-toggle="modal" data-email=" {{$registration->players[0]->email}}" data-totype="one"><i class="ti ti-pencil me-1"></i>Email Player</span>
                                                                    <span class="btn btn-sm btn-danger withdrawButton" data-categoryEventId="{{$categories->id}}" data-player="{{$registration->players[0]->name}} {{$registration->players[0]->surname}}" data-registrationId="{{$registration->id}}"><i class="ti ti-trash me-1"></i>Withdraw</span>

                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>



                                            </div>
                                            @endforeach

                                        </div>

                                        <div class="tab-pane fade " id="navs-justified-link-preparing" role="tabpanel">
                                            @foreach($eventCategories as $key => $categories)
                                            <div class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                <div class="card-header">
                                                    <h3>{{$categories->category->name}}</h3>
                                                </div>

                                                <div class="table-responsive text-nowrap mb-4">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Name</th>
                                                                <th>Email</th>
                                                                <th>Contact</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($categories->withdrawals as $k => $withdrawal)
                                                            <tr>
                                                                <td> <strong>{{$k+1}}</strong></td>
                                                                <td>{{$withdrawal->registration->players[0]->name}} {{$withdrawal->registration->players[0]->surname}}</td>
                                                                <td> {{$withdrawal->registration->players[0]->email}}
                                                                </td>
                                                                <td><span class="badge bg-label-primary me-1">{{$withdrawal->registration->players[0]->cellNr}} </span></td>
                                                                <td>
                                                                    <span class="btn btn-sm btn-secondary sendEmail" data-bs-target="#createEmail" data-bs-toggle="modal" data-email=" {{$withdrawal->registration->players[0]->email}}" data-totype="one"><i class="ti ti-pencil me-1"></i>Email Player</span>


                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>



                                            </div>
                                            @endforeach
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <div class="tab-pane fade show " id="results" role="tabpanel">
                    <div class=" mb-3 gap-3">

                        <div>

                            <span id="publishResults" data-event_id="{{$event->id}}" class="mb-2 align-middle btn btn-{{$event->results_published == 1 ? 'danger':'success'}} btn-sm">{{$event->results_published == 1 ? 'Unpublish Results':'Publish Results'}}</span>


                            @if($event->series)
                            @if($event->series->rankType->type == 'position' || $event->series->rankType->type == 'overberg')
                            @include('backend.adminPage._includes.position_type')
                            @else
                            @include('backend.adminPage._includes.participation_type')

                            @endif
                            @elseif($event->eventType == 7)


                            @else
                            @include('backend.adminPage._includes.position_type')
                            @endif
                        </div>
                    </div>



                </div>
                <div class="tab-pane fade show " id="transactions" role="tabpanel">

                    <div class=" mb-3 gap-3">
                        <table id="transactionTable" class="table">
                            <thead>
                                <th>Nr</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>User</th>
                                <th>Items</th>


                                <th>Gross</th>
                                <th>Payfast Fee</th>
                                <th>Cape Tennis Fee</th>
                                <th>Nett</th>
                            </thead>
                            <tfoot>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Totals</td>
                                    <td>Totals</td>
                                    <td>Totals</td>
                                </tr>
                            </tfoot>
                            <tbody>
                                @foreach($transactions as $key => $transaction)
                                <tr>
                                    <td>{{$transaction->pf_payment_id ? $transaction->pf_payment_id:''}}</td>
                                    <td>{{$transaction->created_at->format('j F, Y')}}</td>
                                    <td>{{$transaction->transaction_type}}</td>
                                    <td> {{$transaction->user->name}}</td>
                                    <td>
                                        <ul>
                                            @if($transaction->order)
                                            @foreach($transaction->order->items as $key => $value)


                                            <li class="d-flex mb-3 pb-1 align-items-center border p-2 ">

                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">{{$value->player->name}} {{$value->player->surname}}</h6>
                                                        <small class="text-muted d-block">{{$value->category_event->category->name}}</small>
                                                    </div>
                                                    <div class="user-progress d-flex align-items-center gap-1">
                                                        <h6 class="mb-0 text-secondary">R{{$value->item_price}}</h6>
                                                    </div>
                                                </div>
                                            </li>
                                            @endforeach
                                            @else
                                            <li class="d-flex mb-3 pb-1 align-items-center border p-2">

                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">{{$transaction->custom_str2}} </h6>
                                                        <small class="text-muted d-block">{{$transaction->category_event->category->name}}</small>
                                                    </div>
                                                    <div class="user-progress d-flex align-items-center gap-1">
                                                        <h6 class="mb-0 text-secondary">R{{$transaction->amount_gross}}</h6>
                                                    </div>
                                                </div>
                                            </li>
                                            @endif


                                        </ul>
                                        <ul class="p-0 m-0">


                                        </ul>



                                    </td>

                                    <td class="h6 mb-0 text-primary">{{$transaction->amount_gross}}</td>
                                    <td class="h6 mb-0 text-danger">{{$transaction->transaction_type == 'Withdrawal' ? ($transaction->amount_fee*-1):$transaction->amount_fee}}</td>


                                    <td class="h6 mb-0 text-danger">
                                        @if($transaction->order)
                                        {!!(($transaction->order->items->count()*-10))!!}

                                        @else
                                        @if($transaction->transaction_type == 'Withdrawal')
                                        10
                                        @else
                                        -10
                                        @endif

                                        @endif


                                    </td>
                                    <td class="h6 mb-0 text-warning">
                                        @if($transaction->order)

                                        {!! ($transaction->amount_gross - (($transaction->order->items->count()*($transaction->cape_tennis_fee)) - $transaction->amount_fee))!!}

                                        @else

                                        @if($transaction->transaction_type == 'Withdrawal')
                                        {{$transaction->amount_net}}
                                        @else
                                        {{ $transaction->amount_net - 10}}
                                        @endif

                                        @endif




                                    </td>
                                </tr>
                                @endforeach
                            </tbody>

                        </table>
                    </div>


                </div>
                <div class="tab-pane fade show " id="draws" role="tabpanel">

                    <div class=" mb-3 gap-3">
                        <div class="card">
                            <div class="card-header"></div>
                            <div class="card-body">
                                <button class=" mb-1 btn btn-success">Create Draw</button>


                                <div class="row">
                                    <div class="col-md-4 col-12 mb-3 mb-md-0">
                                        <div class="list-group">
                                            @foreach($event->draws as $key => $draw)
                                            <a class="list-group-item list-group-item-action" id="list-settings-list" data-bs-toggle="list" href="#draw-{{$draw->id}}">{{$draw->drawName}}</a>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-12">
                                        <div class="tab-content">
                                            @foreach($event->draws as $draw)
                                            <div class="tab-pane fade show" id="draw-{{$draw->id}}">
                                                <div class="col-12 col-xl-12 col-md-12">
                                                    <div class="card h-100">
                                                        <div class="card-header d-flex align-items-center justify-content-between">
                                                            <h5 class="card-title m-0 me-2">Draw Details</h5>
                                                            <div class="dropdown">
                                                                <button class="btn p-0" type="button" id="topCourses" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                    <i class="ti ti-dots-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="topCourses">
                                                                    <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
                                                                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                                                                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <ul class="list-unstyled mb-0">
                                                                <li class="d-flex mb-4 pb-1 align-items-center mt-2">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-info"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-align-box-right-middle">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M15 15h2" />
                                                                                    <path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z" />
                                                                                    <path d="M11 12h6" />
                                                                                    <path d="M13 9h4" />
                                                                                </svg></i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Draw Type</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary">{{$draw->draw_types->drawTypeName}}</div>
                                                                        </div>
                                                                        <div class="btn btn-success btn-sm col-3 m-1">Edit Draw Type</div>
                                                                    </div>
                                                                </li>
                                                                <li class="d-flex mb-4 pb-1 align-items-center">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-success"><i class=" ti-md">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ball-tennis">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                                                                    <path d="M6 5.3a9 9 0 0 1 0 13.4" />
                                                                                    <path d="M18 5.3a9 9 0 0 0 0 13.4" />
                                                                                </svg>

                                                                            </i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Published</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary">{{$draw->published == 1 ? 'Published':'Not Published'}}</div>
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                                <li class="d-flex mb-4 pb-1 align-items-center">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-warning"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-lock">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z" />
                                                                                    <path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" />
                                                                                    <path d="M8 11v-4a4 4 0 1 1 8 0v4" />
                                                                                </svg></i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Locked</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary">{{$draw->locked == 1 ? 'Locked':'Open'}}</div>
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                                <li class="d-flex mb-4 pb-1 align-items-center">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-primary"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                                                                                    <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                                                                                </svg></i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Players in draw</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary">{{$draw->registrations->count()}}</div>
                                                                        </div>
                                                                        <div class="btn btn-success btn-sm col-3 m-1" data-bs-toggle="modal" data-bs-target="#add-registrations-modal">Add Players to draw</div>
                                                                    </div>
                                                                </li>
                                                                <div class="card border border-primary mb-4">
                                                                    <div class="card-body ">
                                                                        <li class="d-flex mb-4 pb-1 align-items-center">
                                                                            <div class="avatar flex-shrink-0 me-3">
                                                                                <span class="avatar-initial rounded bg-label-primary"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard">
                                                                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                            <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                                                                                            <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                                                                                        </svg></i></span>
                                                                            </div>
                                                                            <div class="row w-100 align-items-center">
                                                                                <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                                    <p class="mb-0 fw-medium">Groups</p>
                                                                                </div>
                                                                                <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                                    <div class="badge bg-label-secondary">{{$draw->groups->count()}}</div>
                                                                                </div>
                                                                                <div class="btn btn-success btn-sm col-3 ms-1 ">Configure Groups</div>

                                                                            </div>

                                                                        </li>


                                                                        @foreach($draw->groups as $key => $group)
                                                                        <div class=" col-2 m-1 badge bg-primary pt-2 pb-2">Group {{$key+1}}</div>

                                                                        @endforeach
                                                                    </div>
                                                                </div>


                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </div>
        <!-- /FAQ's -->
    </div>





</div>
<!-- Modal -->
<div class="modal fade" id="add-registrations-modal" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Players to draw</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">


                <div class="col-12 p-4">

                    <div class="switches-stacked">
                        <label class="switch">
                            <input type="radio" class="switch-input" name="switches-stacked-radio" checked />
                            <span class="switch-toggle-slider">
                                <span class="switch-on"></span>
                                <span class="switch-off"></span>
                            </span>
                            <span class="switch-label">Add all players in category</span>
                        </label>
                        <div class="ms-5 col-sm mb-3">

                            @foreach($event->categories as $category)
                            <div class="form-check mt-3 ">
                                <input name="default-radio-1" class="form-check-input" type="radio" value="" id="defaultRadio1" />
                                <label class="form-check-label" for="defaultRadio1">
                                    {{$category->name}}
                                </label>
                            </div>
                            @endforeach


                        </div>
                    </div>
                    <label class="switch">
                        <input type="radio" class="switch-input" name="switches-stacked-radio" />
                        <span class="switch-toggle-slider">
                            <span class="switch-on"></span>
                            <span class="switch-off"></span>
                        </span>
                        <span class="switch-label">Add players from event</span>
                    </label>
                    <div class="mb-3 mt-3">

                        <select id="select2Multiple" class="select2 form-select" multiple>
                            @foreach($event->registrations as $registration)
                            <option value="{{$registration->id}}">{{$registration->registration->players[0]->name}} {{$registration->registration->players[0]->surname}}</option>

                            @endforeach

                        </select>
                    </div>
                </div>
            </div>
           <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Add Players</button>
        </div>   
        </div>
      
    </div>
</div>

