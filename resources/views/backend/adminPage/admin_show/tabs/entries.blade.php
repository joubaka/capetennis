<div class="d-flex mb-3 gap-3">
  <div>
    <h4 class="mb-0 mt-4"><span class="align-middle">Entries</span></h4>
    <small>Players entered in {{ $event->name }}</small>
  </div>
</div>

<div class="col-md-12 col-lg-12 col-xl-12 mb-4">
  <div class="shadow-none bg-transparent h-100">
    <div class="d-flex justify-content-between pb-2 mb-1">
      <div class="dropdown">
        <button class="btn p-0" type="button" data-bs-toggle="dropdown">
          <span class="btn btn-primary btn-sm"><i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <a class="dropdown-item createEmailButton" href="javascript:void(0);"
             data-bs-target="#createEmail" data-bs-toggle="modal"
             onclick="changeRecipants('event','{{ $event->id }}')">
            Send e-mail to all players in event
          </a>
          <a class="dropdown-item" href="{{ route('export.registrations', $event->id) }}">
            Export entry list
          </a>
        </div>
      </div>
    </div>

    <div class="nav-align-top">
      <ul class="nav nav-tabs nav-fill" role="tablist">
        <li class="nav-item">
          <button type="button" class="nav-link active btn btn-success text-black"
                  data-bs-toggle="tab" data-bs-target="#navs-justified-new">Confirmed</button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link btn btn-danger text-black"
                  data-bs-toggle="tab" data-bs-target="#navs-justified-link-preparing">Withdrawals</button>
        </li>
      </ul>

      <div class="tab-content pb-0">
        {{-- Confirmed --}}
        <div class="tab-pane fade active show" id="navs-justified-new">
          @foreach ($eventCategories as $categories)
            <div class="card shadow-none bg-transparent border border-primary mb-5">
              <div class="card-header">
                <h3>{{ $categories->category->name }}</h3>
                <button type="button" class="btn btn-success btn-sm addPlayerC"
                        data-bs-target="#addPlayerToCategory" data-bs-toggle="modal"
                        data-categoryeventid="{{ $categories->id }}">Add Player</button>
                <a class="btn btn-sm btn-primary createEmailButton"
                   href="javascript:void(0);" data-bs-target="#createEmail"
                   data-bs-toggle="modal"
                   onclick="changeRecipants('category','{{ $categories->id }}')">
                   Send e-mail to category
                </a>
              </div>

              <div class="table-responsive text-nowrap mb-4">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>#</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($categories->registrations as $k => $registration)
                      <tr>
                        <td><strong>{{ $k + 1 }}</strong></td>
                        <td>{{ $registration->players[0]->name }} {{ $registration->players[0]->surname }}</td>
                        <td>{{ $registration->players[0]->email }}</td>
                        <td><span class="badge bg-label-primary">{{ $registration->players[0]->cellNr }}</span></td>
                        <td>
                          <span class="btn btn-sm btn-secondary sendEmail"
                                data-bs-target="#createEmail" data-bs-toggle="modal"
                                data-email="{{ $registration->players[0]->email }}"
                                data-totype="one">
                                <i class="ti ti-pencil me-1"></i>Email
                          </span>
                          <button class="btn btn-sm btn-danger withdraw-player-btn"
                                  data-id="{{ $registration->id }}"
                                  data-categoryevent="{{ $categories->id }}"
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

        {{-- Withdrawals --}}
        <div class="tab-pane fade" id="navs-justified-link-preparing">
          @foreach ($eventCategories as $categories)
            <div class="card shadow-none bg-transparent border border-primary mb-5">
              <div class="card-header"><h3>{{ $categories->category->name }}</h3></div>
              <div class="table-responsive text-nowrap mb-4">
                <table class="table table-bordered">
                  <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                  <tbody>
                    @foreach ($categories->withdrawals as $k => $withdrawal)
                      <tr>
                        <td><strong>{{ $k + 1 }}</strong></td>
                        <td>{{ $withdrawal->registration->players[0]->name }} {{ $withdrawal->registration->players[0]->surname }}</td>
                        <td>{{ $withdrawal->registration->players[0]->email }}</td>
                        <td><span class="badge bg-label-primary">{{ $withdrawal->registration->players[0]->cellNr }}</span></td>
                        <td>
                          <span class="btn btn-sm btn-secondary sendEmail"
                                data-bs-target="#createEmail" data-bs-toggle="modal"
                                data-email="{{ $withdrawal->registration->players[0]->email }}">
                                <i class="ti ti-pencil me-1"></i>Email
                          </span>
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
