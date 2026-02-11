


@php
  // Quick counts for badges (TEAM EVENTS)
  $regionCount   = $event->regions->count();
  $teamCount     = $event->regions->sum(fn ($r) => $r->teams->count());
  $categoryCount = $event->eventCategories->count();
  $playerCount   = $event->regions->sum(
    fn ($r) => $r->teams->sum(fn ($t) => $t->players->count())
  );
@endphp


<style>
  .tabs-wrap {
    position: sticky; top: 72px; z-index: 100;
    background: var(--bs-body-bg);
    border-bottom: 1px solid var(--bs-border-color);
  }
  .tabs-wrap .nav-tabs {
    flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden;
    gap: .25rem; scrollbar-width: thin;
  }
  .tabs-wrap .nav-link {
    white-space: nowrap; display: inline-flex; align-items: center; gap: .4rem;
    padding: .5rem .75rem;
  }
  .tabs-wrap .nav-link .badge {
    transform: translateY(-1px);
  }
  .subtabs-sticky {
    position: sticky; top: 124px; z-index: 90;
    background: var(--bs-body-bg); border-bottom: 1px solid var(--bs-border-color);
  }
  .subtabs-sticky .nav-tabs { overflow-x: auto; flex-wrap: nowrap; }
  .tab-pane .card-header { display: flex; align-items: center; justify-content: space-between; }
</style>

<div class="col-xl-12">
  <h3 class="mb-3">Team Event: {{ $event->name }}</h3>

  <div class="col-xl-12">
    <div class="nav-tabs-shadow mb-4">

      {{-- ✅ Top nav --}}
      <div class="tabs-wrap">
        <ul class="nav nav-tabs nav-fill px-2" role="tablist">
          @if (Auth::id() === 584)
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link" role="tab"
                data-bs-toggle="tab" data-bs-target="#tab-regions"
                aria-controls="tab-regions" aria-selected="false">
                <i class="ti ti-home ti-xs me-1"></i>
                Regions
                <span class="badge rounded-pill bg-label-primary ms-1">{{ $regionCount }}</span>
                <span class="badge rounded-pill bg-label-info ms-1">{{ $teamCount }}</span>
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link" role="tab"
                data-bs-toggle="tab" data-bs-target="#tab-categories"
                aria-controls="tab-categories" aria-selected="false" tabindex="-1">
                <i class="ti ti-category ti-xs me-1"></i>
                Categories
                <span class="badge rounded-pill bg-label-warning ms-1">{{ $categoryCount }}</span>
              </button>
            </li>
          @endif

          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" role="tab"
              data-bs-toggle="tab" data-bs-target="#tab-players"
              aria-controls="tab-players" aria-selected="true">
              <i class="ti ti-users-group ti-xs me-1"></i>
              Players
              <span class="badge rounded-pill bg-label-success ms-1">{{ $playerCount }}</span>
            </button>
          </li>

          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" role="tab"
              data-bs-toggle="tab" data-bs-target="#tab-order"
              aria-controls="tab-order" aria-selected="false" tabindex="-1">
              <i class="ti ti-list-ordered ti-xs me-1"></i>
              Player order
            </button>
          </li>

          @if (Auth::id() === 584)
            <li class="nav-item" role="presentation">
              <button id="result-rank-button" type="button" class="nav-link" role="tab"
                data-bs-toggle="tab" data-bs-target="#tab-result-rank"
                aria-controls="tab-result-rank" aria-selected="false">
                <i class="ti ti-award ti-xs me-1"></i>
                Result Ranks
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <a href="{{ route('headOffice.show', $event->id) }}" class="nav-link">
                <i class="ti ti-gauge ti-xs me-1"></i> Dashboard
              </a>
            </li>
          @endif
        </ul>
      </div>

      <div class="tab-content p-3">

       {{-- ✅ Regions + Teams merged --}}
{{-- ============================= --}}
{{-- REGIONS TAB --}}
{{-- ============================= --}}
  @include('backend.adminPage.admin_show.tabs.regions') {{-- ✅ use your working version --}}

        {{-- Categories tab --}}
        <div class="tab-pane fade" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories">
          <div class="card">
            <div class="card-header">
              <h5 class="m-0">Event Categories</h5>
              <button class="btn btn-primary btn-sm" id="add-category-button"
                data-bs-toggle="modal" data-bs-target="#add-category-modal">
                <i class="ti ti-plus me-1"></i> Add Category
              </button>
            </div>
            <div class="card-body">
              @if ($event->eventCategories->isEmpty())
                <div class="alert alert-primary noRegions" role="alert">No Categories added to event</div>
              @else
                <ul class="list-group">
                  @foreach ($event->eventCategories as $category)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <span>{{ $category->category->name }}</span>
                      <span class="text-muted">#{{ $category->id }}</span>
                    </li>
                  @endforeach
                </ul>
              @endif
            </div>
          </div>
        </div>

  {{-- 🧍‍♂️ PLAYERS TAB --}}
  @include('backend.adminPage.admin_show.tabs.players') {{-- ✅ use the working version you built above --}}

  {{-- 🔢 PLAYER ORDER TAB --}}
  @include('backend.adminPage.admin_show.tabs.player-order') {{-- ✅ use your working version --}}



        {{-- Result Ranks (active) --}}
        <div class="tab-pane fade" id="tab-result-rank" role="tabpanel" aria-labelledby="tab-result-rank">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="text-muted small fw-medium mb-2">Categories</div>
              <div class="switches-stacked">
                @foreach($event->eventCategories as $idx => $category)
                  <label class="switch d-block mb-2">
                    <input type="radio"
                           class="switch-input category-radio"
                           name="category-radio"
                           value="{{ $category->id }}"
                           data-name="{{ $category->category->name }}"
                           data-event_id="{{ $event->id }}"
                           {{ $idx === 0 ? 'checked' : '' }}>
                    <span class="switch-toggle-slider">
                      <span class="switch-on"></span>
                      <span class="switch-off"></span>
                    </span>
                    <span class="switch-label">{{ $category->category->name }}</span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="col-md-9">
              <div class="card" id="rank-table">
                <div class="card-header">
                  <h5 id="category-name" class="m-0"></h5>
                </div>
                <div class="card-body" id="category-table"><!-- AJAX loads here --></div>
              </div>
            </div>
          </div>
        </div>

      </div> {{-- /.tab-content --}}
    </div>
  </div>
</div>
{{-- 🟠 Edit No-Profile Modal --}}
<div class="modal fade" id="editNoProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editNoProfileForm">@csrf
        <div class="modal-header">
          <h5 class="modal-title">Edit Dummy Player</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="noProfileId">
          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="name" id="noProfileName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Surname</label>
            <input type="text" class="form-control" name="surname" id="noProfileSurname" required>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>



