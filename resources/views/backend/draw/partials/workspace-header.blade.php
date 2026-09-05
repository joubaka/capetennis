@php
  $workspaceUrl = route('backend.draw.roundrobin.show', $draw);
  $flexibleWorkspace = $draw->usesFlexibleMonrad();
  $workspaceDraws = $draw->event->draws;
@endphp
<x-backend.page-header :title="$draw->drawName" :subtitle="$draw->event->name" eyebrow="Draw workspace" icon="ti-tournament" class="draw-workspace-header" data-backend-wide data-workspace-context="{{ $workspaceContext ?? '' }}">
  <x-slot:meta>
    <span class="badge bg-label-secondary" data-workspace-status>{{ $draw->locked ? 'Locked' : ($draw->published ? 'Published' : 'Draft') }}</span>
    <span class="small text-muted" role="status" data-share-status></span>
  </x-slot:meta>
  <x-slot:actions>
      @if(!empty($rankingReference))
        <div class="dropdown draw-ranking-reference">
          <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="ti ti-list-numbers me-1" aria-hidden="true"></i>{{ $rankingReference['category']->name }} rankings
          </button>
          <div class="dropdown-menu dropdown-menu-end draw-ranking-menu p-0">
            <div class="draw-ranking-heading">
              <div>
                <span class="draw-ranking-eyebrow">Series ranking reference</span>
                <h2>{{ $rankingReference['category']->name }}</h2>
                <p>{{ $rankingReference['series']->name }}@if($rankingReference['series']->year) · {{ $rankingReference['series']->year }}@endif</p>
              </div>
              <span class="badge bg-label-{{ $rankingReference['status'] === 'published' ? 'success' : ($rankingReference['status'] === 'reviewed' ? 'info' : ($rankingReference['status'] === 'calculated' ? 'warning' : 'secondary')) }}">
                {{ $rankingReference['status'] ? ucfirst($rankingReference['status']) : 'No ranking run' }}
              </span>
            </div>
            @if($rankingReference['rows']->isNotEmpty())
              <div class="draw-ranking-scroll">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead><tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col" class="text-end">Points</th></tr></thead>
                  <tbody>
                    @foreach($rankingReference['rows'] as $ranking)
                      <tr>
                        <td class="draw-ranking-position">{{ $ranking->rank_position }}</td>
                        <td>{{ $ranking->player?->full_name ?? 'Unknown player' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($ranking->total_points) }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <p class="draw-ranking-empty">No ranking entries are available for this category in the active series run.</p>
            @endif
            <div class="draw-ranking-footer">
              <span>Reference only · current {{ $rankingReference['status'] ?? 'unavailable' }} run</span>
              <a href="{{ $rankingReference['url'] }}" target="_blank" rel="noopener">Open full rankings</a>
            </div>
          </div>
        </div>
      @endif
      @if(($workspaceSurface ?? '') === 'roundrobin')
        <button class="btn btn-sm btn-outline-primary" type="button" id="rr-open-print">Print</button>
      @else
        <a class="btn btn-sm btn-outline-primary" href="{{ $workspaceUrl }}#print">Print</a>
      @endif
      <a class="btn btn-sm btn-outline-secondary" href="{{ $flexibleWorkspace ? route('public.flexible-monrad.show', $draw) : route('public.roundrobin.show', $draw) }}" target="_blank" rel="noopener" data-workspace-public @if(!$draw->published && !$draw->oop_published) hidden @endif>{{ $draw->published ? 'Public view' : 'Preview times' }}</a>
      <button class="btn btn-sm btn-outline-secondary" type="button" data-share-draw="{{ route('public.roundrobin.show', $draw) }}" @disabled(!$draw->published) title="{{ $draw->published ? 'Share public draw link' : 'Publish the draw before sharing' }}">Share</button>
      @if(($workspaceSurface ?? '') === 'roundrobin')
        @can('publish', $draw)
          <div class="dropdown"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Publication</button><div class="dropdown-menu dropdown-menu-end p-2">
            <button type="button" class="dropdown-item" id="rr-publish-draw" data-url="{{ route('draw.toggle.publish', $draw) }}">{{ $draw->published ? 'Unpublish draw' : 'Publish draw' }}</button>
            <button type="button" class="dropdown-item" id="rr-publish-schedule" data-url="{{ route('draw.toggle.publish.schedule', $draw) }}">{{ $draw->oop_published ? 'Unpublish schedule' : 'Publish schedule' }}</button>
          </div></div>
        @endcan
        @can('lockToggle', $draw)
          <button type="button" class="btn btn-sm {{ $draw->locked ? 'btn-danger' : 'btn-outline-secondary' }}" id="btn-toggle-lock"><span id="lock-label">{{ $draw->locked ? 'Unlock' : 'Lock' }}</span></button>
        @endcan
        <span id="badge-locked" class="d-none"></span><span id="badge-published" class="d-none"></span>
      @endif
      @if($flexibleWorkspace)
        @can('lockToggle', $draw)
          <button type="button" class="btn btn-sm btn-outline-secondary" data-workspace-lock="{{ route('backend.draw.toggle-lock', $draw) }}">{{ $draw->locked ? 'Unlock draw' : 'Lock draw' }}</button>
        @endcan
      @endif
      @if($workspaceDraws->count() > 1)
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">Switch Draw ({{ $workspaceDraws->count() }})</button>
          <ul class="dropdown-menu dropdown-menu-end">
            @foreach($workspaceDraws as $otherDraw)
              <li><a class="dropdown-item {{ $otherDraw->id === $draw->id ? 'active' : '' }}" href="{{ route('backend.draw.roundrobin.show', $otherDraw) }}" data-workspace-switch>{{ $otherDraw->drawName }}</a></li>
            @endforeach
          </ul>
        </div>
      @endif
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('headOffice.show', $draw->event_id) }}">Back to Event</a>
  </x-slot:actions>
</x-backend.page-header>
@once
  <link rel="stylesheet" href="{{ asset('css/draw-workspace-navigation.css') }}?v={{ filemtime(public_path('css/draw-workspace-navigation.css')) }}">
  <script src="{{ asset('js/draw-workspace-navigation.js') }}?v={{ filemtime(public_path('js/draw-workspace-navigation.js')) }}" defer></script>
@endonce
