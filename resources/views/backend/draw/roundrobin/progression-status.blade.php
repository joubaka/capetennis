@if($draw->settings?->workflow === 'round_robin_playoffs')
  @php
    $progressTotal = $draw->drawFixtures()->where('stage', 'RR')->count();
    $progressComplete = $draw->drawFixtures()->where('stage', 'RR')->whereHas('fixtureResults')->count();
    $progressReady = $progressTotal > 0 && $progressComplete === $progressTotal;
    $progressPlayoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')->get(['registration1_id', 'registration2_id']);
    $progressHasQualifiers = $progressPlayoffs->contains(fn ($fixture) => $fixture->registration1_id || $fixture->registration2_id);
  @endphp
  <section class="card card-body my-3 no-print" aria-labelledby="playoff-progression-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h6 id="playoff-progression-heading" class="mb-1">Group stage → playoffs @if($progressHasQualifiers)<span class="badge bg-label-success ms-2">Playoffs created</span>@elseif($progressPlayoffs->isNotEmpty())<span class="badge bg-label-secondary ms-2">Qualifiers pending</span>@endif</h6>
        @if($draw->locked)
          <p class="small text-muted mb-0">This draw is locked. Unlock it before progressing the playoffs.</p>
        @elseif($progressHasQualifiers && !$progressReady)
          <p class="small mb-0">Playoffs already exist, but some group results are incomplete. Review the group results before refreshing the qualifiers.</p>
        @elseif($progressHasQualifiers)
          <p class="small text-muted mb-0">The playoffs are created. Review the current bracket; refresh the qualifiers only if the standings have changed.</p>
        @elseif($progressReady)
          <p class="small mb-0">All {{ $progressTotal }} group results are complete. Review the standings and confirm progression to the playoffs.</p>
        @elseif($progressTotal > 0)
          <p class="small text-muted mb-0">{{ $progressComplete }} of {{ $progressTotal }} group results complete. Finish the remaining results before progressing.</p>
        @else
          <p class="small text-muted mb-0">Create the group matches first, then complete their results before progressing to the playoffs.</p>
        @endif
      </div>
      @can('progress', $draw)
        @if($progressReady)
          <button type="button" class="btn {{ $progressHasQualifiers ? 'btn-outline-secondary' : 'btn-primary' }} progress-draw" data-url="{{ route('backend.draw.progress', $draw) }}" data-review-url="{{ route('backend.draw.progress-review', $draw) }}" data-draw-name="{{ $draw->drawName }}">{{ $progressHasQualifiers ? 'Review & refresh playoffs' : 'Review & progress to playoffs' }}</button>
        @endif
      @endcan
    </div>
    <details class="mt-3"><summary>Qualification and progression rules</summary>
      <div class="small mt-2">
        <p>Complete all group results, review each group's final order and confirm every group. Qualifiers come from the saved positions below; the preview does not qualify players.</p>
        <ul>
          @forelse(collect($draw->settings->playoff_config ?? [])->filter(fn ($config) => is_array($config) && ($config['enabled'] ?? false)) as $progressConfig)
            <li><strong>{{ is_string($progressConfig['name'] ?? null) ? $progressConfig['name'] : 'Playoffs' }}:</strong> {{ is_array($progressConfig['positions'] ?? null) ? 'positions '.implode(', ', array_filter($progressConfig['positions'], 'is_numeric')).' from each group' : 'qualifying positions must be configured' }}.</li>
          @empty
            <li>Choose and save the qualifying positions in playoff settings.</li>
          @endforelse
        </ul>
        <a href="{{ route('backend.draw.roundrobin.show', $draw) }}#standings">Review group standings</a> · <a href="{{ route('backend.draw.roundrobin.show', $draw) }}#settings">View saved playoff settings</a> · <a href="{{ route('backend.draw.roundrobin.show', $draw) }}#notes">Event rules and notes</a>
      </div>
    </details>
  </section>
@endif
