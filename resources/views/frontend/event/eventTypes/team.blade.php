



<style>
.team-public-event {
  --team-navy: #173f7a;
  --team-blue: #2f6fd0;
  --team-orange: #f28c28;
  --team-teal: #159b91;
  --team-gold: #f2be3e;
  background:
    radial-gradient(circle at 8% 3%, rgba(47, 111, 208, .14), transparent 28rem),
    radial-gradient(circle at 96% 18%, rgba(242, 140, 40, .13), transparent 24rem),
    linear-gradient(180deg, rgba(23, 63, 122, .035), rgba(21, 155, 145, .035));
  border: 1px solid rgba(47, 111, 208, .12);
  border-radius: 1.25rem;
  padding: clamp(.75rem, 2vw, 1.5rem);
}

.team-public-event .event-section-card,
.team-public-event .team-region-directory,
.team-public-event .team-documents-card {
  border: 0;
  border-top: 4px solid var(--team-teal);
  box-shadow: 0 .65rem 1.6rem rgba(23, 63, 122, .10) !important;
  overflow: hidden;
}

.team-public-event .event-section-icon {
  background: linear-gradient(135deg, var(--team-teal), var(--team-blue));
  box-shadow: 0 .35rem .8rem rgba(21, 155, 145, .22);
  color: #fff;
}

.team-public-event .team-region-directory {
  background: linear-gradient(180deg, rgba(242, 140, 40, .08), #fff 9rem);
  border-top-color: var(--team-orange);
}

.team-public-event .team-region-kicker {
  background: linear-gradient(135deg, var(--team-orange), #f5aa3f);
  box-shadow: 0 .3rem .75rem rgba(242, 140, 40, .22);
  color: #fff;
  letter-spacing: .01em;
}

.team-public-event .team-documents-card { border-top-color: var(--team-blue); }
.team-public-event .team-documents-card .card-header {
  background: linear-gradient(135deg, rgba(47, 111, 208, .11), rgba(21, 155, 145, .08));
}

.team-public-event .file-item:hover {
  background-color: rgba(47, 111, 208, .07);
  border-radius: 6px;
  transition: background-color 0.2s ease;
}

.team-public-event .card .btn-outline-primary {
  border-radius: 50px;
  font-size: 0.875rem;
  padding: 0.25rem 0.75rem;
}

.team-public-event .card .btn-outline-primary:hover {
  background-color: var(--team-blue);
  color: #fff;
}

.team-public-event .region-tab-grid .nav-link {
  border-left: 4px solid var(--team-blue);
  box-shadow: 0 .25rem .75rem rgba(23, 63, 122, .07);
}

.team-public-event .region-tab-grid .nav-item:nth-child(3n+2) .nav-link { border-left-color: var(--team-orange); }
.team-public-event .region-tab-grid .nav-item:nth-child(3n+3) .nav-link { border-left-color: var(--team-teal); }
.team-public-event .region-tab-grid .nav-link:hover {
  border-color: var(--team-orange);
  transform: translateY(-1px);
}
.team-public-event .region-tab-grid .nav-link.active {
  background: linear-gradient(135deg, var(--team-navy), var(--team-blue));
  border-color: var(--team-navy);
  box-shadow: 0 .45rem 1rem rgba(23, 63, 122, .2);
  color: #fff;
}

.team-public-event .tab-pane > .row > div:nth-child(4n+1) .card-header { border-top: 3px solid var(--team-blue); }
.team-public-event .tab-pane > .row > div:nth-child(4n+2) .card-header { border-top: 3px solid var(--team-orange); }
.team-public-event .tab-pane > .row > div:nth-child(4n+3) .card-header { border-top: 3px solid var(--team-teal); }
.team-public-event .tab-pane > .row > div:nth-child(4n+4) .card-header { border-top: 3px solid var(--team-gold); }
.team-public-event .tab-pane > .row > div .card {
  border: 1px solid rgba(23, 63, 122, .14);
  box-shadow: 0 .45rem 1rem rgba(23, 63, 122, .08) !important;
  transition: box-shadow .18s ease, transform .18s ease;
}
.team-public-event .tab-pane > .row > div .card:hover {
  box-shadow: 0 .7rem 1.4rem rgba(23, 63, 122, .14) !important;
  transform: translateY(-2px);
}

@media (max-width: 767.98px) {
  .team-public-event { border-radius: .9rem; padding: .65rem; }
}
</style>

{{-- resources/views/frontend/event/eventTypes/team.blade.php --}}
@php
  $regions = $event->regions ?? collect();
@endphp

<div class="team-public-event col-xl-12">
  <div class="row mb-4">

    <!-- ================= LEFT COLUMN ================= -->
    <div class="col-xl-8 col-lg-7 col-md-7">

      @include('frontend.event.partials.event-information')
      @include('frontend.event.partials.event-announcements')

      {{-- 🔹 Draws and Order of Play (Mobile / Tablet only) --}}
      <div class="card d-block d-md-none mb-4">
        <div class="card-body">
          @include('frontend.event.partials._draws_and_order_of_play')
        </div>
      </div>

      {{-- 🔹 Regions & Teams --}}
      <div class="card team-region-directory p-3 p-sm-4">
        <div class="card-body p-0 pb-2">
          <div class="badge team-region-kicker mb-3">
            Click on a Region below to register
          </div>
        </div>

        @if($regions->isNotEmpty())
          @include('frontend.event.partials._region_team_picker', ['regions' => $regions])
        @else
          <div class="alert alert-secondary mt-3">
            Regions are not configured for this event.
          </div>
        @endif
      </div>
    </div>

    <!-- ================= RIGHT COLUMN ================= -->
    <div class="col-xl-4 col-lg-5 col-md-5">

      @include('frontend.event.partials.event-about')

      {{-- 🔹 Documents --}}
      <div class="card team-documents-card mb-4 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> Documents
          </h6>

          @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
            <form action="{{ route('file.store') }}" method="POST"
                  enctype="multipart/form-data" class="mb-0">
              @csrf
              <input type="hidden" name="event_id" value="{{ $event->id }}">
              <label class="btn btn-sm btn-outline-primary mb-0">
                Upload
                <input type="file" name="myFile" class="d-none"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                       onchange="this.form.submit()">
              </label>
            </form>
          @endif
        </div>

        <div class="card-body pb-2">
          @forelse($event->files as $file)
            <div class="file-item border-bottom py-2 d-flex justify-content-between align-items-center">
              <a href="{{ route('events.documents.show', [$event, $file]) }}" target="_blank"
                 class="fw-semibold text-dark text-decoration-none">
                {{ $file->name }}
              </a>

              @if(auth()->user()?->is_admin($event->id) || auth()->id() == 584)
                <button class="btn btn-sm btn-outline-danger deleteFileButton"
                        data-id="{{ $file->id }}">
                  <i class="ti ti-trash"></i>
                </button>
              @endif
            </div>
          @empty
            <div class="text-muted">No documents uploaded yet.</div>
          @endforelse
        </div>
      </div>

      {{-- 🔹 Draws and Order of Play (Desktop only) --}}
      <div class="d-none d-md-block">
        @include('frontend.event.partials._draws_and_order_of_play')
      </div>

    </div>
  </div>
</div>
{{-- 🔹 Clothing Order Modal --}}
{{-- Clothing Order Modal --}}
@include('frontend.event.partials._clothing_order_modal')




