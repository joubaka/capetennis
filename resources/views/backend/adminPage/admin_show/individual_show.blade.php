<style>
    .select2-container {
        z-index: 100000;
    }
</style>
<div class="card ">
    <div class="card-header event-header">
        <h3 class="text-center"> {{ $event->name }}  </h3>
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



                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#transactions">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Transactions</span>
                                </button>
                            </li>

                           @auth
    @if (auth()->user()->id == 584)
        <li class="nav-item">
            <a href="{{ route('event.admin.main', $event->id) }}" class="nav-link">
                <i class="ti ti-trophy me-1 ti-sm"></i>
                <span class="align-middle fw-semibold">Tournament Admin</span>
            </a>
        </li>
    @endif
@endauth





                        </ul>

                        <div class="d-none d-md-block">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 col-md-12 col-12">
            <div class="tab-content py-0">
                <!-- registrations -->
                <div class="tab-pane fade show active" id="payment" role="tabpanel">
                    <div class="d-flex mb-3 gap-3">

                        <div>
                            <h4 class="mb-0 mt-4">
                                <span class="align-middle">Entries</span>
                            </h4>
                            <small>Player entered in {{ $event->name }}</small>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-12 col-xl-12 mb-4">
                        <div class=" h-100  shadow-none bg-transparent ">
                            <div class=" d-flex justify-content-between pb-2 mb-1">

                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="salesByCountryTabs"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="btn btn-primary btn-sm"> <i
                                                class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                        <a class="dropdown-item createEmailButton" href="javascript:void(0);"
                                            data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event"
                                            onclick="changeRecipants('event','{{ $event->id }}')">Send e-mail to all
                                            players in event</a>
                                        <a class="dropdown-item" href="{{ route('export.registrations', $event->id) }}"
                                            data-event="{{ $event->id }}">Export entry list</a>

                                    </div>
                                </div>
                            </div>
                            <div class="shadow-none bg-transparent">
                                <div class="nav-align-top">
                                    <ul class="nav nav-tabs nav-fill" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button type="button" class="nav-link active btn btn-success text-black"
                                                role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-new"
                                                aria-controls="navs-justified-new"
                                                aria-selected="true">Confirmed</button>
                                        </li>
                                        <li class=" nav-item" role="presentation">
                                            <button type="button" class="nav-link btn btn-danger text-black"
                                                role="tab" data-bs-toggle="tab"
                                                data-bs-target="#navs-justified-link-preparing"
                                                aria-controls="navs-justified-link-preparing" aria-selected="false"
                                                tabindex="-1">Withdrawals</button>
                                        </li>

                                    </ul>
                                    <div class="tab-content pb-0">
                                        <div class="tab-pane fade active show" id="navs-justified-new" role="tabpanel">
                                            @foreach ($eventCategories as $key => $categories)
                                                <div
                                                    class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                    <div class="card-header">
                                                        <h3>{{ $categories->category->name }}</h3><button type="button"
                                                            data-bs-target="#addPlayerToCategory" data-bs-toggle="modal"
                                                            data-categoryeventid="{{ $categories->id }}"
                                                            class="btn btn-success btn-sm addPlayerC">Add
                                                            Player</button>
                                                        <a class="btn btn-sm btn-primary createEmailButton"
                                                            href="javascript:void(0);" data-bs-target="#createEmail"
                                                            data-bs-toggle="modal" data-totype="event"
                                                            onclick="changeRecipants('category','{{ $event->id }}')">Send
                                                            e-mail </a>

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
                                                                @foreach ($categories->registrations as $k => $registration)
                                                                    <tr>
                                                                        <td> <strong>{{ $k + 1 }}</strong></td>
                                                                        <td>{{ $registration->players[0]->name }}
                                                                            {{ $registration->players[0]->surname }}
                                                                        </td>
                                                                        <td>
                                                                            {{ $registration->players[0]->email }}
                                                                        </td>
                                                                        <td><span
                                                                                class="badge bg-label-primary me-1">{{ $registration->players[0]->cellNr }}</span>
                                                                        </td>
                                                                        <td>
                                                                            <span
                                                                                class="btn btn-sm btn-secondary sendEmail"
                                                                                data-bs-target="#createEmail"
                                                                                data-bs-toggle="modal"
                                                                                data-email=" {{ $registration->players[0]->email }}"
                                                                                data-totype="one"><i
                                                                                    class="ti ti-pencil me-1"></i>Email
                                                                                Player</span>
       <button 
  class="btn btn-sm btn-danger withdraw-player-btn"
  data-id="{{ $registration->id }}"
  data-categoryevent="{{ $registration->categoryEvents->first()->id ?? ($categoryEvent->id ?? '') }}"
  data-name="{{ $registration->players[0]->name }} {{ $registration->players[0]->surname }}">
  <i class="ti ti-user-x me-1"></i> Withdraw
</button>




                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>



                                                </div>
                                            @endforeach

                                        </div>

                                        <div class="tab-pane fade " id="navs-justified-link-preparing"
                                            role="tabpanel">
                                            @foreach ($eventCategories as $key => $categories)
                                                <div
                                                    class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                    <div class="card-header">
                                                        <h3>{{ $categories->category->name }}</h3>
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
                                                                @foreach ($categories->withdrawals as $k => $withdrawal)
                                                                    <tr>
                                                                        <td> <strong>{{ $k + 1 }}</strong></td>
                                                                        <td>{{ $withdrawal->registration->players[0]->name }}
                                                                            {{ $withdrawal->registration->players[0]->surname }}
                                                                        </td>
                                                                        <td> {{ $withdrawal->registration->players[0]->email }}
                                                                        </td>
                                                                        <td><span
                                                                                class="badge bg-label-primary me-1">{{ $withdrawal->registration->players[0]->cellNr }}
                                                                            </span></td>
                                                                        <td>
                                                                            <span
                                                                                class="btn btn-sm btn-secondary sendEmail"
                                                                                data-bs-target="#createEmail"
                                                                                data-bs-toggle="modal"
                                                                                data-email=" {{ $withdrawal->registration->players[0]->email }}"
                                                                                data-totype="one"><i
                                                                                    class="ti ti-pencil me-1"></i>Email
                                                                                Player</span>


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
                <!-- results -->
                <div class="tab-pane fade show " id="results" role="tabpanel">
                    <div class=" mb-3 gap-3">

                        <div>

                            <span id="publishResults" data-event_id="{{ $event->id }}"
                                class="mb-2 align-middle btn btn-{{ $event->results_published == 1 ? 'danger' : 'success' }} btn-sm">{{ $event->results_published == 1 ? 'Unpublish Results' : 'Publish Results' }}</span>


                            @if ($event->series)
                                @if ($event->series->rankType->type == 'position' || $event->series->rankType->type == 'overberg')
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
                <!-- transcations -->
                <div class="tab-pane fade show " id="transactions" role="tabpanel">

                    <div class="table-responsive mb-5"><a href="{{ route('transactions.pdf', $event->id) }}"
                            target="_blank" class="btn btn-sm btn-outline-danger mb-3">
                            Download PDF
                        </a>
                        <table id="transactionTable"
                            class="table table-sm table-bordered table-hover align-middle text-sm">
                            <thead class="table-light sticky-top">
                                <tr class="align-middle text-center">
                                    <th>Nr</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>User</th>
                                    <th>Items</th>
                                    <th style="min-width: 80px;">Gross</th>
                                    <th style="min-width: 80px;">Payfast Fee</th>
                                    <th style="min-width: 80px;">Cape Tennis Fee</th>
                                    <th style="min-width: 80px;">Nett</th>
                                    <th style="min-width: 80px;">Balance</th>
                                </tr>
                            </thead>

                            <tbody class="table-group-divider">
                                @foreach ($transactions as $transaction)
                                    <tr>
                                        <td class="text-center">{{ $transaction->pf_payment_id ?? '-' }}</td>
                                        <td>{{ $transaction->created_at->format('d M Y') }}</td>
                                        <td>{{ $transaction->transaction_type }}</td>
                                        <td>{{ $transaction->user->name ?? '-' }}</td>

                                        {{-- Items --}}
                                        <td>

                                            <div class="d-flex flex-column gap-2">
                                                @if ($transaction->order && $transaction->order->items)
                                                    @foreach ($transaction->order->items as $item)
                                                        <div
                                                            class="d-flex justify-content-between align-items-center border rounded p-2 shadow-sm bg-white">
                                                            <div>
                                                                <div class="fw-semibold">{{ $item->player->name }}
                                                                    {{ $item->player->surname }}</div>
                                                                <small
                                                                    class="text-muted">{{ $item->category_event->category->name }}</small>
                                                            </div>
                                                            <div class="text-end">
                                                                <span class="text-secondary fw-bold">
                                                                    R{{ number_format($item->item_price ?? $event->entryFee, 2) }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div
                                                        class="d-flex justify-content-between align-items-center border rounded p-2 shadow-sm bg-white">
                                                        <div>
                                                            <div class="fw-semibold">{{ $transaction->custom_str2 }}
                                                            </div>
                                                            <small
                                                                class="text-muted">{{ $transaction->category_event->category->name }}</small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="text-secondary fw-bold">
                                                                R{{ number_format($transaction->amount_gross, 2) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>


                                        </td>


                                        {{-- Financials --}}
                                        <td class="text-center text-primary">
                                            R{{ number_format($transaction->calculated_gross, 2) }}</td>
                                        <td class="text-center text-danger">
                                            R{{ number_format($transaction->calculated_payfast_fee, 2) }}</td>
                                        <td class="text-center text-danger">
                                            R{{ number_format($transaction->calculated_cape_fee, 2) }}</td>
                                        <td class="text-center text-warning">
                                            R{{ number_format($transaction->calculated_nett, 2) }}</td>
                                        <td
                                            class="text-center fw-bold {{ $transaction->calculated_balance < 0 ? 'text-danger' : 'text-success' }}">

                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr class="fw-bold align-middle">
                                    <td colspan="5" class="text-end">Totals:</td>
                                    <td></td> <!-- Gross -->
                                    <td></td> <!-- Payfast -->
                                    <td></td> <!-- Cape -->
                                    <td></td> <!-- Nett -->
                                    <td></td> <!-- Balance -->
                                </tr>
                            </tfoot>

                        </table>
                    </div>






                </div>
                <div class="tab-pane fade show active" id="tournament-admin" role="tabpanel">
                  <div class="mb-3">
                      <div class="d-flex justify-content-between mb-4">
                          <div>
                            <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#generateDrawModal">
                              <i class="fas fa-plus"></i> Create Draw
                          </button>

                              <button class="btn btn-danger">
                                  <i class="fas fa-sort"></i> Change Draw Order
                              </button>
                          </div>
                      </div>

                      @foreach ($eventCategories as $categoryEvent)
                          @foreach ($categoryEvent->draws as $draw)
                              <div class="border rounded p-3 mb-4 bg-white shadow-sm">
                                  <div class="d-flex justify-content-between">
                                      <div>
                                          <h5 class="mb-2">{{ $draw->drawName }}</h5>

                                          {{-- Tags --}}
                                          <div class="mb-2">
                                              <span class="badge bg-warning">Individual</span>
                                              <span class="badge bg-primary">Tennis (Singles)</span>
                                              <span class="badge bg-danger">{{ $draw->registrations_count ?? '0' }} players</span>
                                              <span class="badge bg-dark">{{ $draw->gender ?? 'Mixed' }}</span>
                                              <span class="badge bg-{{ $draw->locked ? 'warning' : 'info' }}">
                                                  {{ $draw->locked ? '🔒 Locked' : '🔓 Unlocked' }}
                                              </span>
                                          </div>

                                          {{-- Completion Progress --}}
                                          <div class="text-primary small fw-bold mb-1">
                                              {{ $draw->completion_percent ?? '0%' }} Complete
                                          </div>
                                          <div class="progress" style="height: 6px; max-width: 300px;">
                                              <div class="progress-bar bg-primary" role="progressbar"
                                                  style="width: {{ $draw->completion_percent ?? '0%' }};"></div>
                                          </div>
                                      </div>

                                      {{-- Buttons --}}
                                      <div class="d-flex align-items-start gap-2 flex-wrap">
                                        <a href="{{ route('category.manage', $draw->category_event_id) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-cog"></i> Settings
                                        </a>
                                        {{-- You can define a route to show players if you want --}}
                                        <a href="#" class="btn btn-sm btn-orange">
                                            <i class="fas fa-users"></i> Players
                                        </a>
                                        <a href="{{ route('draws.show', $draw->id) }}" target="_blank" class="btn btn-sm btn-success">
                                            <i class="fas fa-eye"></i> View Draw
                                        </a>
                                        <form method="POST" action="{{ route('draws.destroy', $draw->id) }}" onsubmit="return confirm('Delete this draw?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>

                                  </div>
                              </div>
                          @endforeach
                      @endforeach
                  </div>
              </div>


            </div>
        </div>
        <!-- /FAQ's -->
    </div>





</div>
@include('backend.adminPage.admin_show.modals.generateDrawOptionsModal')
