@php($pickerId = 'event-region-picker-'.$event->id)

<div class="region-team-picker" id="{{ $pickerId }}">
  <div class="d-md-none px-3 pb-3">
    <label class="form-label fw-semibold" for="{{ $pickerId }}-select">Choose a region</label>
    <select class="form-select region-team-select" id="{{ $pickerId }}-select">
      @foreach($regions as $idx => $region)
        <option value="#team-{{ $event->id }}-{{ $region->id }}" @selected($idx === 0)>{{ $region->region_name }}</option>
      @endforeach
    </select>
  </div>

  <ul class="nav nav-tabs region-tab-grid d-none d-md-grid px-3" role="tablist" aria-label="Event regions">
    @foreach($regions as $idx => $region)
      @php($tabId = 'team-'.$event->id.'-'.$region->id)
      <li class="nav-item" role="presentation">
        <button type="button"
                class="nav-link {{ $idx === 0 ? 'active' : '' }}"
                data-bs-toggle="tab"
                data-bs-target="#{{ $tabId }}"
                role="tab"
                aria-controls="{{ $tabId }}"
                aria-selected="{{ $idx === 0 ? 'true' : 'false' }}">
          <i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ $region->region_name }}
        </button>
      </li>
    @endforeach
  </ul>

  <div class="tab-content pt-3">
    @foreach($regions as $idx => $region)
      @php($tabId = 'team-'.$event->id.'-'.$region->id)
      <div class="tab-pane fade {{ $idx === 0 ? 'active show' : '' }}" id="{{ $tabId }}" role="tabpanel">
        <div class="row">
          @forelse($region->teams as $team)
            @if((int)($team->noProfile ?? 0) === 0)
              @include('frontend.event.partials.profile-team', ['team' => $team])
            @else
              @include('frontend.event.partials.no-profile-team', ['team' => $team])
            @endif
          @empty
            <div class="col-12">
              <div class="alert alert-secondary mb-0">No teams listed for this region yet.</div>
            </div>
          @endforelse
        </div>
      </div>
    @endforeach
  </div>
</div>

@once
  <style>
      .region-tab-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: .6rem;
        border-bottom: 0;
      }
      .region-tab-grid .nav-item { min-width: 0; }
      .region-tab-grid .nav-link {
        width: 100%;
        height: 100%;
        padding: .75rem .9rem;
        border: 1px solid var(--bs-border-color);
        border-radius: .65rem;
        background: var(--bs-body-bg);
        color: var(--bs-body-color);
        text-align: left;
        white-space: normal;
        line-height: 1.25;
      }
      .region-tab-grid .nav-link:hover { border-color: var(--bs-primary); }
      .region-tab-grid .nav-link.active {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
        box-shadow: 0 .2rem .6rem rgba(0, 0, 0, .08);
      }
  </style>
@endonce

<script>
    document.addEventListener('DOMContentLoaded', function () {
      const picker = document.getElementById(@json($pickerId));
      if (!picker) return;
      const select = picker.querySelector('.region-team-select');
      const buttons = picker.querySelectorAll('[data-bs-toggle="tab"]');

      select?.addEventListener('change', function () {
        const button = Array.from(buttons).find(candidate => candidate.dataset.bsTarget === this.value);
        if (button) bootstrap.Tab.getOrCreateInstance(button).show();
      });
      buttons.forEach(button => button.addEventListener('shown.bs.tab', function () {
        if (select) select.value = this.dataset.bsTarget;
      }));
    });
</script>
