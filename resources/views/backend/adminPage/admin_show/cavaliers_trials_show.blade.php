<style>
    .select2-container {
        z-index: 100000;
    }
</style>
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
                                    <span class="align-middle fw-semibold">Nominations</span>
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
                            <li class="nav-item" role="presentation">
                                <a href="{{route('headOffice.show',$event->id)}}" type="button" class="nav-link"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i>Dashboard </a>
                            </li>

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
                    @include('backend.nominations.view')



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
                            @if(isset($transactions))
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
      <h6 class="mb-0">{{ $value->player->name }} {{ $value->player->surname }}</h6>
      <small class="text-muted d-block">{{ $value->category_event?->category?->name ?? '—' }}</small>
    </div>
    <div class="user-progress d-flex align-items-center gap-1">
      <h6 class="mb-0 text-secondary">R{{ $value->item_price }}</h6>
    </div>
  </div>
</li>
@endforeach
@else
<li class="d-flex mb-3 pb-1 align-items-center border p-2">
  <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
    <div class="me-2">
      <h6 class="mb-0">{{ $transaction->custom_str2 }}</h6>
      <small class="text-muted d-block">{{ $transaction->category_event?->category?->name ?? '—' }}</small>
    </div>
    <div class="user-progress d-flex align-items-center gap-1">
      <h6 class="mb-0 text-secondary">R{{ $transaction->amount_gross }}</h6>
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
                            @endif
                            </tbody>

                        </table>
                    </div>


                </div>
            </div>
        </div>
        <!-- /FAQ's -->
    </div>





</div>
@include('backend.adminPage.admin_show.modals.nominationModal')

