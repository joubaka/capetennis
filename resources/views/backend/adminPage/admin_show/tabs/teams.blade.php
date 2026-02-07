<div class="container-fluid mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-semibold mb-0">Team Assignment</h4>

    <div class="d-flex align-items-center gap-2">

      <!-- FILTER BY CATEGORY -->
      <select id="categoryFilter" class="form-select form-select-sm" style="width:auto;">
        <option value="all">All Categories</option>
        @foreach ($eventCategories as $categoryEvent)
          @php $slug = Str::slug($categoryEvent->category->name); @endphp
          <option value="{{ $slug }}">{{ $categoryEvent->category->name }}</option>
        @endforeach
      </select>

      <!-- ADD TEAM -->
      <button id="addTeamBtn" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> Add Team
      </button>

      <!-- SAVE -->
      <button id="saveTeams" class="btn btn-sm btn-success">
        <i class="ti ti-device-floppy"></i> Save Teams
      </button>
    </div>
  </div>

  <div class="alert alert-info py-2 small mb-3">
    Drag players into teams (category-restricted). Drag inside a team to change ranking order.
  </div>

  <div class="row">

    <!-- LEFT: PLAYER POOLS -->
    <div class="col-lg-3">
      <div class="accordion" id="categoryAccordion">
        <h2>{{$categoryEvent->category->name}}</h2>
        @foreach ($eventCategories as $categoryEvent)

          @php $catSlug = Str::slug($categoryEvent->category->name); @endphp

          <div class="accordion-item category-block" data-category="{{ $catSlug }}">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed py-2" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse-{{ $catSlug }}">
                {{ $categoryEvent->category->name }}
              </button>
            </h2>

            <div id="collapse-{{ $catSlug }}" class="accordion-collapse collapse show">
              <div class="accordion-body p-2">

                <ul class="list-group sortable-player-pool"
                    id="pool-{{ $catSlug }}"
                    data-category="{{ $catSlug }}"
                    style="min-height:150px;">

                  @foreach ($categoryEvent->registrations as $registration)
                    @php $p = $registration->players->first(); @endphp

                    <li class="list-group-item d-flex justify-content-between align-items-center"
                      data-registration-id="{{ $registration->id }}"
                      data-player-id="{{ $p->id }}"
                      data-category="{{ $catSlug }}">

                      <span>{{ $p->name }} {{ $p->surname }}</span>
                      <small class="text-muted">{{ $categoryEvent->category->name }}</small>
                    </li>

                  @endforeach

                </ul>

              </div>
            </div>
          </div>

        @endforeach

      </div>
    </div>

    <!-- RIGHT: TEAM AREA -->
    <div class="col-lg-9">
      <div id="teamArea" class="row g-3">@foreach ($eventCategories as $categoryEvent)
  @php $catSlug = Str::slug($categoryEvent->category->name); @endphp
     
  @foreach ($categoryEvent->draws as $draw)
    @foreach ($draw->groups as $group)
      @php
        $groupId = $group->id;
        $groupName = $group->name ?? "Group {$groupId}";
      @endphp

      <div class="col-md-4 team-wrapper" data-category="{{ $catSlug }}">
        <div class="card shadow-sm border border-primary team-card" data-team-id="{{ $groupId }}">
          
          <!-- Header -->
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">{{ $groupName }}</span>
            <button class="btn btn-sm btn-outline-light remove-team">×</button>
          </div>

          <!-- Group player list -->
          <ul class="list-group list-group-flush sortable-team"
              id="team-{{ $groupId }}"
              data-category="{{ $catSlug }}"
              style="min-height:200px;">

            @foreach ($group->groupRegistrations as $grpReg)
              @php $player = $grpReg->registration->players->first(); @endphp

              <li class="list-group-item d-flex justify-content-between align-items-center"
                  data-registration-id="{{ $grpReg->registration_id }}"
                  data-player-id="{{ $player->id }}"
                  data-category="{{ $catSlug }}">

                <span>{{ $player->name }} {{ $player->surname }}</span>
                <small class="text-muted">{{ $categoryEvent->category->name }}</small>
              </li>
            @endforeach

          </ul>
        </div>
      </div>

    @endforeach
  @endforeach
@endforeach
</div>
    </div>

  </div>
</div>


<!-- CREATE TEAM MODAL -->
<div class="modal fade" id="createTeamModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Create New Team</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="createTeamForm">

          <div class="mb-3">
            <label class="form-label">Team Name</label>
            <input type="text" id="teamName" class="form-control" placeholder="Blue Team" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Category</label>
            <select id="teamCategory" class="form-select" required>
              <option value="">Select Category</option>
              @foreach ($eventCategories as $categoryEvent)
                <option value="{{ Str::slug($categoryEvent->category->name) }}">
                  {{ $categoryEvent->category->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Team Color</label>
            <select id="teamColor" class="form-select">
              <option value="primary">Blue</option>
              <option value="success">Green</option>
              <option value="warning">Yellow</option>
              <option value="danger">Red</option>
              <option value="info">Cyan</option>
              <option value="secondary">Grey</option>
            </select>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="createTeamConfirm">Create Team</button>
      </div>

    </div>
  </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

  /* SORTABLE SETUP */
  const initSortable = () => {

    // Player Pool Areas
    document.querySelectorAll('.sortable-player-pool').forEach(pool => {
      new Sortable(pool, {
        group: 'teams',
        animation: 150,
        sort: false,
        ghostClass: 'bg-light'
      });
    });

    // Teams
    document.querySelectorAll('.sortable-team').forEach(teamList => {
      new Sortable(teamList, {
        group: 'teams',
        animation: 150,
        ghostClass: 'bg-light',

        onAdd(evt) {
          const teamCategory = evt.to.dataset.category;
          const playerCategory = evt.item.dataset.category;

          if (teamCategory !== playerCategory) {
            toastr.warning('Player does not belong to this category');
            evt.from.appendChild(evt.item);
          }
        }
      });
    });
  };


  /* OPEN MODAL */
  $('#addTeamBtn').on('click', () => {
    $('#createTeamModal').modal('show');
  });


  /* CREATE TEAM */
  $('#createTeamConfirm').on('click', function () {

    const name = $('#teamName').val().trim();
    const category = $('#teamCategory').val();
    const color = $('#teamColor').val();

    if (!name || !category) {
      toastr.error('Enter team name and category');
      return;
    }

    const id = $('.team-card').length + 1;

    const html = `
      <div class="col-md-4 team-wrapper" data-category="${category}">
        <div class="card shadow-sm border border-${color} team-card" data-team-id="${id}">
          <div class="card-header bg-${color} text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">${name}</span>
            <button class="btn btn-sm btn-outline-light remove-team">×</button>
          </div>
          <ul class="list-group list-group-flush sortable-team"
              id="team-${id}" data-category="${category}"
              style="min-height:200px;"></ul>
        </div>
      </div>
    `;

    $('#teamArea').append(html);
    $('#createTeamModal').modal('hide');
    $('#createTeamForm')[0].reset();
    initSortable();
  });


  /* REMOVE TEAM */
  $(document).on('click', '.remove-team', function () {
    $(this).closest('.team-wrapper').remove();
  });


 /* SAVE TEAMS */
$('#saveTeams').on('click', () => {

  const teams = [];

  console.log('=== SAVE TEAMS CLICKED ===');

  $('.team-card').each(function () {

    const name = $(this).find('.card-header span').text().trim();
    const category = $(this).closest('.team-wrapper').data('category');

    const players = [];

    $(this).find('li').each(function (i) {
      const regId = $(this).data('registration-id');
      const rank = i + 1;

      players.push({
        id: regId,
        rank: rank
      });

      console.log(`Player Added → RegID: ${regId}, Rank: ${rank}, Team: ${name}`);
    });

    const teamObj = {
      name,
      category,
      players: players ?? []
    };

    console.log('Team Built:', teamObj);

    teams.push(teamObj);
  });

  console.log('FINAL TEAMS PAYLOAD:', JSON.parse(JSON.stringify(teams)));

  $.post('{{ route("backend.event.saveTeams") }}', {
    event_id: '{{ $event->id }}',
    teams,
    _token: '{{ csrf_token() }}',
  })
    .done((res) => {
      console.log('SERVER RESPONSE:', res);
      toastr.success(res.message);
    })
    .fail((err) => {
      console.error('SAVE FAILED:', err);
      toastr.error('Save failed');
    });

});


  /* CATEGORY FILTER */
  $('#categoryFilter').on('change', function () {
    const selected = $(this).val();

    if (selected === 'all') {
      $('.category-block, .team-wrapper').show();
      return;
    }

    $('.category-block').hide();
    $(`.category-block[data-category="${selected}"]`).show();

    $('.team-wrapper').hide();
    $(`.team-wrapper[data-category="${selected}"]`).show();
  });

  initSortable();
});
</script>
