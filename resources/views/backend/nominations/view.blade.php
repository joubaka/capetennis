<div class="mb-3 gap-3">
  <div>
    <h4>Nominations</h4>

    <!-- Actions Dropdown -->
    <div class="dropdown mb-3">
      <button class="btn p-0" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="btn btn-primary btn-sm">
          <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions
        </span>
      </button>
      <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
        <a class="dropdown-item createEmailButton"
           href="javascript:void(0);"
           data-bs-target="#createEmail"
           data-bs-toggle="modal"
           data-totype="event"
           onclick="changeRecipants('nominations','{{ $event->id }}')">
          Send e-mail to all nominated players in event
        </a>
      </div>
    </div>

    <!-- Category Loop -->
    @foreach($event->eventCategories as $eventCategory)
      <div class="card shadow-none bg-transparent border border-primary mb-5">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="mb-0">{{ $eventCategory->category->name }}</h3>

          <div class="d-flex gap-2">
            <!-- ✅ Fixed: pass ID not object -->
          <button type="button"
        class="btn btn-success btn-sm openNominateModal"
        data-bs-toggle="modal"
        data-bs-target="#nominatePlayerModal"
        data-categoryeventid="{{ $eventCategory->id }}">
  <i class="ti ti-user-plus me-1"></i> Nominate Player
</button>


            <button class="nominationPublish btn btn-sm btn-{{ $eventCategory->nominations_published ? 'danger' : 'success' }}"
                    data-id="{{ $eventCategory->id }}">
              {{ $eventCategory->nominations_published ? 'Unpublish' : 'Publish' }} list
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="table-responsive text-nowrap mb-4">
          <table class="table table-bordered nomination-table" id="nomination-table-{{ $eventCategory->id }}">
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
              @forelse($eventCategory->nominations as $k => $nomination)
                <tr data-nominationid="{{ $nomination->id }}">
                  <td><strong>{{ $k + 1 }}</strong></td>
                  <td>{{ $nomination->player->getFullNameAttribute() }} @php $info = $playerInfo[$nomination->player->id] ?? null; @endphp
@if($info)
  <small class="text-muted">{{ $info['region'] }} | Rank {{ $info['rank'] }}</small>
@endif
</td>
                  <td>{{ $nomination->player->email }}</td>
                  <td><span class="badge bg-label-primary me-1">{{ $nomination->player->cellNr }}</span></td>
                  <td>
                    <span class="btn btn-sm btn-secondary sendEmail"
                          data-bs-target="#createEmail"
                          data-bs-toggle="modal"
                          data-email="{{ $nomination->player->email }}"
                          data-totype="one">
                      <i class="ti ti-pencil me-1"></i>Email Player
                    </span>

                    <!-- ✅ Fixed: correct attribute names -->
                    <span class="btn btn-sm btn-danger nomination-remove"
                          data-id="{{ $nomination->id }}"
                          data-player="{{ $nomination->player->getFullNameAttribute() }}"
                          data-categoryeventid="{{ $eventCategory->id }}">
                      <i class="ti ti-trash me-1"></i>Remove
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted">No nominations yet</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @endforeach
  </div>
</div>
