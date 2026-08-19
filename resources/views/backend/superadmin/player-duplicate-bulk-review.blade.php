@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-git-merge me-2 text-primary"></i>Review bulk duplicate merge</h4>
      <p class="text-muted mb-0">{{ $batch['selected_count'] }} selected: {{ count($batch['analyses']) }} ready and {{ count($batch['skipped']) }} skipped.</p>
    </div>
    <a href="{{ route('superadmin.player-duplicates.index') }}" class="btn btn-outline-secondary">Back to candidates</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="ti ti-alert-triangle fs-4"></i>
    <div><strong>Unsafe plans are skipped before confirmation.</strong> The remaining ready group follows the suggested keep/remove directions as one atomic action. Every plan is checked again before any profile is removed.</div>
  </div>

  @if($batch['skipped'])
    <div class="card border-danger mb-4">
      <div class="card-header d-flex align-items-center gap-2 text-danger">
        <i class="ti ti-player-skip-forward fs-5"></i><strong>Skipped automatically ({{ count($batch['skipped']) }})</strong>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Candidate</th><th>What would have happened</th><th>Why it was skipped</th><th>Next option</th></tr></thead>
          <tbody>
          @foreach($batch['skipped'] as $skipped)
            <tr>
              <td>
                @if($skipped['keep'] && $skipped['remove'])
                  <strong>#{{ $skipped['remove']->id }} {{ $skipped['remove']->full_name }}</strong>
                  <div class="small text-muted">paired with #{{ $skipped['keep']->id }} {{ $skipped['keep']->full_name }}</div>
                @else
                  <strong>Profiles #{{ $skipped['first_id'] }} and #{{ $skipped['second_id'] }}</strong>
                @endif
              </td>
              <td>
                @if($skipped['keep'] && $skipped['remove'])
                  <span class="small">Remove #{{ $skipped['remove']->id }} into retained profile #{{ $skipped['keep']->id }}</span>
                @else
                  <span class="small text-muted">Could not safely determine direction</span>
                @endif
              </td>
              <td>
                <ul class="mb-0 ps-3">@foreach($skipped['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
                @foreach($skipped['contexts'] ?? [] as $context)
                  <div class="border rounded p-2 mt-2 small">
                    @if(($context['type'] ?? null) === 'series_ranking')
                      <strong>Series #{{ $context['series_id'] }}, category #{{ $context['category_id'] ?? 'none' }}</strong>
                      <div class="mt-1">
                        @foreach($context['rows'] as $row)
                          <span class="badge bg-label-danger me-1">row #{{ $row['id'] }} · player #{{ $row['player_id'] }} · {{ $row['status'] ?: 'blank status' }}</span>
                        @endforeach
                      </div>
                      <div class="text-muted mt-1">Series lifecycle: {{ collect($context['series_status_counts'])->map(fn($count, $status) => $status.': '.$count)->implode(', ') }}</div>
                    @elseif(($context['type'] ?? null) === 'tournament_registration_overlap')
                      <strong>{{ $context['event_name'] ?? 'Event #'.($context['event_id'] ?? 'unknown') }} / {{ $context['category_name'] ?? 'category #'.($context['category_id'] ?? 'unknown') }}</strong>
                      <div class="text-muted">Category event #{{ $context['category_event_id'] }}</div>
                      @foreach($context['entries'] ?? [] as $entry)
                        <span class="badge bg-label-{{ $entry['paid'] ? 'warning' : 'secondary' }} me-1">registration #{{ $entry['registration_id'] }} · {{ $entry['status'] ?: 'blank' }}{{ $entry['paid'] ? ' · paid' : '' }}</span>
                      @endforeach
                    @else
                      <strong>Fixture #{{ $context['fixture_id'] ?? 'unknown' }}</strong>
                      @if($context['event_id'] ?? null)
                        — Event #{{ $context['event_id'] }} {{ $context['event_name'] ?? 'Unnamed event' }}
                      @else
                        — no linked event
                      @endif
                      <span class="badge bg-label-{{ ($context['result_count'] ?? 0) > 0 ? 'danger' : 'secondary' }} ms-1">{{ $context['result_count'] ?? 0 }} result rows</span>
                    @endif
                  </div>
                @endforeach
              </td>
              <td>
                @if($skipped['keep'] && $skipped['remove'])
                  <a href="{{ route('superadmin.player-duplicates.review', [$skipped['keep'], $skipped['remove']]) }}" class="btn btn-outline-danger btn-sm">Open full review</a>
                @else
                  <span class="badge bg-label-secondary">Review separately</span>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  @if($batch['analyses'])
  <div class="card border-success mb-4">
    <div class="card-header text-success"><i class="ti ti-circle-check me-1"></i><strong>Ready to merge ({{ count($batch['analyses']) }})</strong></div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Keep canonical profile</th><th>Merge and remove source</th><th>History protected</th><th>Automatic profile values</th></tr></thead>
        <tbody>
        @foreach($batch['analyses'] as $analysis)
          @php
            $recommendedValues = collect($analysis['fields'])
              ->filter(fn($field) => $field['different'] && $field['recommended'] === 'remove');
            $registrationHistoryCount = collect($analysis['impact']['registration_history'])
              ->sum(fn($columns) => collect($columns)->sum(fn($counts) => $counts['keep'] + $counts['remove']));
          @endphp
          <tr>
            <td>
              <strong>#{{ $analysis['keep']->id }} {{ $analysis['keep']->full_name }}</strong>
              <div class="small text-success">{{ $analysis['impact']['keep']['usage_total'] }} linked records</div>
              @if($analysis['overlap_resolution'] ?? false)
                <div class="small text-primary mt-1"><i class="ti ti-route me-1"></i>{{ $analysis['overlap_resolution'] }}</div>
              @endif
            </td>
            <td><strong>#{{ $analysis['remove']->id }} {{ $analysis['remove']->full_name }}</strong><div class="small text-muted">{{ $analysis['impact']['remove']['usage_total'] }} linked records will move</div></td>
            <td>
              <span class="badge bg-label-primary">{{ $registrationHistoryCount }} registration/result references</span>
              <div class="small text-muted mt-1">Registration IDs, tournament results and ranking attribution remain attached to #{{ $analysis['keep']->id }}.</div>
              @if(count($analysis['impact']['ranking_rebuild_series_ids'] ?? []))
                <div class="small text-warning mt-1">
                  <i class="ti ti-refresh me-1"></i>Rebuild calculated ranking for series {{ collect($analysis['impact']['ranking_rebuild_series_ids'])->map(fn($id) => '#'.$id)->implode(', ') }}. Published snapshots remain unchanged.
                </div>
              @endif
              @foreach($analysis['impact']['registration_overlap_resolutions'] ?? [] as $resolution)
                <div class="small text-success mt-1">
                  <i class="ti ti-recycle me-1"></i>{{ $resolution['event_name'] ?? 'Event #'.$resolution['event_id'] }} / {{ $resolution['category_name'] ?? 'category #'.$resolution['category_id'] }}:
                  withdraw unpaid registration #{{ $resolution['duplicate_registration_id'] }} and retain #{{ $resolution['canonical_registration_id'] }}. Orders/results stay preserved.
                </div>
              @endforeach
            </td>
            <td>
              @forelse($recommendedValues as $field => $comparison)
                <div class="small"><strong>{{ str_replace('_', ' ', ucfirst($field)) }}:</strong> {{ filled($comparison['remove']) ? $comparison['remove'] : 'Blank' }}</div>
              @empty
                <span class="small text-muted">Keep canonical values</span>
              @endforelse
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <form method="POST" action="{{ route('superadmin.player-duplicates.bulk-merge') }}" class="card">
    @csrf
    <input type="hidden" name="batch_mode" value="{{ $batch['mode'] ?? 'quick' }}">
    <input type="hidden" name="batch_digest" value="{{ $batch['digest'] }}">
    @foreach($batch['analyses'] as $index => $analysis)
      <input type="hidden" name="pairs[{{ $index }}][first_id]" value="{{ min($analysis['keep']->id, $analysis['remove']->id) }}">
      <input type="hidden" name="pairs[{{ $index }}][second_id]" value="{{ max($analysis['keep']->id, $analysis['remove']->id) }}">
    @endforeach
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Audit reason for all selected merges</label>
        <textarea name="reason" class="form-control" rows="2" minlength="10" maxlength="2000" required>{{ old('reason', 'Confirmed one-sided-history duplicates after matching identity details.') }}</textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Type exactly: <code>{{ $batch['confirmation_phrase'] }}</code></label>
        <input name="confirmation" class="form-control" value="{{ old('confirmation') }}" autocomplete="off" required>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted">Restricted to Super Admins. The reviewed batch digest is checked again before any profile is changed.</span>
        <button class="btn btn-danger"><i class="ti ti-git-merge me-1"></i>Merge all {{ count($batch['analyses']) }} selected profiles</button>
      </div>
    </div>
  </form>
  @else
    <div class="alert alert-secondary mb-0"><strong>Nothing will be merged.</strong> Every selected candidate was skipped. Use the full-review links above or return to the candidate list.</div>
  @endif
</div>
@endsection
