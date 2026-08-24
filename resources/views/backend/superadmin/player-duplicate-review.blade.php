@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <a href="{{ route('superadmin.player-duplicates.index') }}" class="small">&larr; Duplicate candidate queue</a>
      <h4 class="mt-2 mb-1">Compare profiles #{{ $first->id }} and #{{ $second->id }}</h4>
      <span class="badge bg-label-{{ $analysis['confidence']['class'] }}">{{ $analysis['confidence']['label'] }}</span>
    </div>
    <div class="btn-group" role="group" aria-label="Choose canonical profile">
      <a href="{{ route('superadmin.player-duplicates.review', [$first, $second]) }}?keep={{ $first->id }}" class="btn btn-sm {{ $analysis['keep']->is($first) ? 'btn-primary' : 'btn-outline-primary' }}">Keep #{{ $first->id }}</a>
      <a href="{{ route('superadmin.player-duplicates.review', [$first, $second]) }}?keep={{ $second->id }}" class="btn btn-sm {{ $analysis['keep']->is($second) ? 'btn-primary' : 'btn-outline-primary' }}">Keep #{{ $second->id }}</a>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  @if($publishedWorkflow)
    <div class="alert alert-warning">
      <strong>2026 published-ranking merge workflow.</strong>
      This operation is limited to 2026 ranking collisions. The current published snapshot will be archived, all player history will be consolidated, and the corrected ranking will be rebuilt for review. It will not be published automatically.
    </div>
  @endif

  @if($analysis['blockers'])
    <div class="alert alert-danger">
      <strong>Merge blocked.</strong>
      <ul class="mb-0 mt-2">
      @foreach($analysis['blockers'] as $blocker)
        <li class="mb-2">
          <strong>{{ str_replace('_', ' ', ucfirst($blocker['domain'])) }}:</strong> {{ $blocker['message'] }}
          @if($publishedWorkflow && $blocker['domain'] === 'identity' && !$identityOverride)
            <div class="mt-2">
              <a class="btn btn-sm btn-outline-danger" href="{{ request()->fullUrlWithQuery(['identity_override' => 1]) }}">
                Confirm same player and enable identity override
              </a>
            </div>
          @endif
          @if(!empty($blocker['contexts']))
            @if(($blocker['contexts'][0]['type'] ?? null) === 'series_ranking')
              @foreach($blocker['contexts'] as $context)
                <div class="border rounded bg-white p-2 mt-2 small">
                  <strong>Series #{{ $context['series_id'] }}, category #{{ $context['category_id'] ?? 'none' }}</strong>
                  <div class="mt-1">
                    @foreach($context['rows'] as $row)
                      <span class="badge bg-label-danger me-1">row #{{ $row['id'] }} · player #{{ $row['player_id'] }} · {{ $row['status'] ?: 'blank status' }}</span>
                    @endforeach
                  </div>
                  <div class="text-muted mt-1">Series lifecycle: {{ collect($context['series_status_counts'])->map(fn($count, $status) => $status.': '.$count)->implode(', ') }}</div>
                </div>
              @endforeach
            @elseif(($blocker['contexts'][0]['type'] ?? null) === 'tournament_registration_overlap')
              @foreach($blocker['contexts'] as $context)
                <div class="border rounded bg-white p-2 mt-2 small">
                  <strong>{{ $context['event_name'] ?? 'Event #'.($context['event_id'] ?? 'unknown') }} / {{ $context['category_name'] ?? 'category #'.($context['category_id'] ?? 'unknown') }}</strong>
                  <div class="text-muted">Category event #{{ $context['category_event_id'] }}</div>
                  <div class="mt-1">
                    @foreach($context['entries'] ?? [] as $entry)
                      <span class="badge bg-label-{{ $entry['paid'] ? 'warning' : 'secondary' }} me-1">
                        entry #{{ $entry['entry_id'] }} · registration #{{ $entry['registration_id'] }} · player #{{ $entry['player_id'] }} · {{ $entry['status'] ?: 'blank status' }}{{ $entry['paid'] ? ' · paid' : '' }}
                      </span>
                    @endforeach
                  </div>
                  <div class="text-danger mt-1">The entries are not an unambiguous paid-versus-abandoned pair, so they require manual review.</div>
                </div>
              @endforeach
            @else
            <div class="table-responsive mt-2">
              <table class="table table-sm table-bordered bg-white mb-1">
                <thead><tr><th>Record</th><th>Fixture / draw</th><th>Event</th><th>Results</th><th>Action</th></tr></thead>
                <tbody>
                @foreach($blocker['contexts'] as $context)
                  <tr>
                    <td>#{{ $context['record_id'] }}</td>
                    <td>
                      <strong>Fixture #{{ $context['fixture_id'] ?: 'unknown' }}</strong>
                      @if($context['draw_id'])<div class="small">Draw #{{ $context['draw_id'] }}{{ $context['draw_name'] ? ' — '.$context['draw_name'] : '' }}</div>@endif
                      @if($context['fixture_created_at'])<div class="small text-muted">Created {{ $context['fixture_created_at'] }}</div>@endif
                    </td>
                    <td>
                      @if($context['event_id'])
                        <strong>#{{ $context['event_id'] }} {{ $context['event_name'] ?: 'Unnamed event' }}</strong>
                        <div class="small">{{ $context['event_start_date'] ?: 'No start date' }}{{ $context['event_end_date'] ? ' to '.$context['event_end_date'] : '' }}</div>
                      @else
                        <span class="badge bg-label-warning">No linked event</span>
                      @endif
                    </td>
                    <td>
                      @if($context['result_count'] > 0)
                        <span class="badge bg-label-danger">{{ $context['result_count'] }} saved result rows</span>
                      @else
                        <span class="badge bg-label-secondary">No saved results</span>
                      @endif
                    </td>
                    <td>
                      @if($context['event_id'])
                        <a href="{{ route('admin.events.individual.hq', $context['event_id']) }}" class="btn btn-outline-primary btn-sm">Open event fixtures</a>
                      @else
                        <span class="small text-muted">Inspect database record before correction</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
            @endif
          @endif
        </li>
      @endforeach
      </ul>
    </div>
  @else
    <div class="alert alert-warning"><strong>Permanent identity change.</strong> All linked history will point to #{{ $analysis['keep']->id }} and source #{{ $analysis['remove']->id }} will be removed after a zero-reference check.</div>
  @endif

  <div class="card mb-4">
    <div class="card-header"><strong>Profile field decisions</strong></div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Field</th><th>Keep #{{ $analysis['keep']->id }}</th><th>Source #{{ $analysis['remove']->id }}</th><th>Final value</th></tr></thead>
        <tbody>
        @foreach($analysis['fields'] as $field => $comparison)
          <tr class="{{ $comparison['different'] ? 'table-warning' : '' }}">
            <td>{{ str_replace('_', ' ', ucfirst($field)) }}</td>
            <td>{{ filled($comparison['keep']) ? $comparison['keep'] : 'Not set' }}</td>
            <td>{{ filled($comparison['remove']) ? $comparison['remove'] : 'Not set' }}</td>
            <td>
              @if($comparison['different'])
                <select name="field_sources[{{ $field }}]" form="merge-form" class="form-select form-select-sm" {{ $analysis['can_merge'] ? '' : 'disabled' }}>
                  <option value="keep" @selected(old("field_sources.{$field}", $comparison['recommended']) === 'keep')>Use #{{ $analysis['keep']->id }} value</option>
                  <option value="remove" @selected(old("field_sources.{$field}", $comparison['recommended']) === 'remove')>Use #{{ $analysis['remove']->id }} value</option>
                </select>
              @else
                <span class="text-muted">Same value</span>
                <input type="hidden" name="field_sources[{{ $field }}]" value="keep" form="merge-form">
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><strong>Linked-record impact</strong></div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="border rounded p-3"><strong>Canonical #{{ $analysis['keep']->id }}</strong><div class="text-muted small">{{ $analysis['impact']['keep']['usage_total'] }} current references</div></div></div>
        <div class="col-md-6"><div class="border rounded p-3"><strong>Source #{{ $analysis['remove']->id }}</strong><div class="text-muted small">{{ $analysis['impact']['remove']['usage_total'] }} references will move</div></div></div>
      </div>
      @forelse($analysis['impact']['references'] as $table => $columns)
        @php($sourceCount = collect($columns)->sum(fn($counts) => $counts['remove']))
        @if($sourceCount > 0)
          <div class="d-flex justify-content-between border-bottom py-2"><span>{{ str_replace('_', ' ', ucfirst($table)) }}</span><strong>{{ $sourceCount }}</strong></div>
        @endif
      @empty
        <p class="text-muted mb-0">No historical references were found.</p>
      @endforelse
      @php($registrationHistoryCount = collect($analysis['impact']['registration_history'])->sum(fn($columns) => collect($columns)->sum(fn($counts) => $counts['remove'])))
      @if($registrationHistoryCount > 0)
        <div class="alert alert-info mt-3 mb-0">
          <strong>{{ $registrationHistoryCount }} tournament result/draw references are registration-based.</strong>
          Their registration IDs and recorded outcomes will not be changed; ownership of those registrations moves to the canonical player so future ranking calculations retain the same results.
        </div>
      @endif
      @if(count($analysis['impact']['ranking_rebuild_series_ids'] ?? []))
        <div class="alert alert-warning mt-3 mb-0">
          <strong>Calculated ranking collision will be resolved automatically.</strong>
          Series {{ collect($analysis['impact']['ranking_rebuild_series_ids'])->map(fn($id) => '#'.$id)->implode(', ') }} will be rebuilt from the preserved registrations and tournament results after the profiles are combined. Existing published ranking snapshots are not republished or changed by this merge.
        </div>
      @endif
      @foreach($analysis['impact']['registration_overlap_resolutions'] ?? [] as $resolution)
        <div class="alert alert-success mt-3 mb-0">
          <strong>Abandoned registration will be resolved automatically.</strong>
          {{ $resolution['event_name'] ?? 'Event #'.$resolution['event_id'] }} / {{ $resolution['category_name'] ?? 'category #'.$resolution['category_id'] }}:
          unpaid registration #{{ $resolution['duplicate_registration_id'] }} (order #{{ $resolution['duplicate_order_id'] }})
          will be marked withdrawn, while registration #{{ $resolution['canonical_registration_id'] }} is retained using
          {{ implode(' and ', $resolution['canonical_evidence']) }} evidence. Both orders and all saved results remain in the audit history.
        </div>
      @endforeach
      @if(count($analysis['impact']['owners_to_transfer']))
        <div class="mt-3 small text-muted">Linked user IDs to transfer: {{ implode(', ', $analysis['impact']['owners_to_transfer']) }}</div>
      @endif
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-danger">
        <div class="card-header"><strong>Super Admin confirmation</strong></div>
        <div class="card-body">
          <form id="merge-form" method="POST" action="{{ $publishedWorkflow ? route('superadmin.player-duplicates.merge-published') : route('superadmin.player-duplicates.merge') }}">
            @csrf
            <input type="hidden" name="keep_player_id" value="{{ $analysis['keep']->id }}">
            <input type="hidden" name="remove_player_id" value="{{ $analysis['remove']->id }}">
            <input type="hidden" name="impact_digest" value="{{ $analysis['digest'] }}">
            @if($identityOverride)
              <input type="hidden" name="identity_override" value="1">
            @endif
            <div class="mb-3">
              <label class="form-label">Reason for merging</label>
              <textarea name="reason" class="form-control" rows="3" required minlength="10" maxlength="2000" {{ $analysis['can_merge'] ? '' : 'disabled' }}>{{ old('reason') }}</textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Type exactly: <code>{{ $identityOverride ? 'MERGE PUBLISHED IDENTITY OVERRIDE' : ($publishedWorkflow ? 'MERGE PUBLISHED' : $analysis['confirmation_phrase']) }}</code></label>
              <input name="confirmation" class="form-control" required autocomplete="off" value="{{ old('confirmation') }}" {{ $analysis['can_merge'] ? '' : 'disabled' }}>
            </div>
            <button class="btn {{ $publishedWorkflow ? 'btn-warning' : 'btn-danger' }}" {{ $analysis['can_merge'] ? '' : 'disabled' }}><i class="ti ti-git-merge me-1"></i>{{ $publishedWorkflow ? 'Archive, merge and rebuild 2026 ranking' : 'Confirm permanent merge' }}</button>
            <div class="small text-muted mt-2">The merge will be rejected if any linked data changed since this page loaded.</div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><strong>Do not merge</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('superadmin.player-duplicates.decision', [$first, $second]) }}">
            @csrf
            <label class="form-label">Review note</label>
            <textarea name="reason" class="form-control mb-3" rows="3" required minlength="5"></textarea>
            <div class="d-grid gap-2">
              <button name="decision" value="not_duplicate" class="btn btn-outline-danger">Mark as not duplicates</button>
              <button name="decision" value="review_later" class="btn btn-outline-secondary">Review later</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
