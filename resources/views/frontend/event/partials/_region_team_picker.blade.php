@php($pickerId = 'event-region-picker-'.$event->id)

<div class="region-team-picker" id="{{ $pickerId }}">
  <div class="region-picker-label fw-semibold mb-2">Choose a region</div>
  <ul class="nav nav-tabs region-tab-grid" role="tablist" aria-label="Event regions">
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
          <i class="ti ti-map-pin me-1" aria-hidden="true"></i>
          <span class="region-name">
            @foreach(explode('/', $region->region_name) as $namePart)
              {{ $namePart }}@unless($loop->last)/<wbr>@endunless
            @endforeach
          </span>
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
        grid-template-columns: 1fr;
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
        overflow-wrap: anywhere;
        line-height: 1.25;
      }
      .region-tab-grid .nav-link:hover { border-color: var(--bs-primary); }
      .region-tab-grid .nav-link.active {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
        box-shadow: 0 .2rem .6rem rgba(0, 0, 0, .08);
      }
      @media (max-width: 767.98px) {
        .region-tab-grid .nav-link {
          min-height: 48px;
          display: flex;
          align-items: center;
        }
      }
      @media (min-width: 768px) {
        .region-tab-grid {
          grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
      }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const target = document.querySelector('[data-team-registration-target="true"]');
      if (!target) return;

      const pane = target.closest('.tab-pane');
      const tab = pane ? document.querySelector('[data-bs-target="#' + pane.id + '"]') : null;
      if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();

      window.setTimeout(function () {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const register = target.querySelector('.team-registration-button');
        if (register) register.focus({ preventScroll: true });
      }, 150);
    });
  </script>
@endonce
