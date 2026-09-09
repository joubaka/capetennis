@extends('layouts.backend')

@section('title', 'Team Selection & Invitations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div><h4 class="mb-1">Team Selection & Invitations</h4><p class="text-muted mb-0">{{ $event->name }}</p></div>
    <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary">Back to event</a>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="alert alert-info">Link each ranking-fed region to its own published series. Imported outside-region rosters can remain unlinked and will not be changed.</div>

  <div class="row g-3">
    @foreach($eventRegions as $eventRegion)
      @php($source = $eventRegion->rankingSource)
      @php($sourceReady = $source && $readySeriesIds->contains($source->series_id))
      @php($regionTeams = $teams->get($eventRegion->region_id, collect()))
      @php($categorySetup = $source ? $categorySetups->get($source->id) : null)
      @php($activeImport = $source?->imports?->whereIn('status', ['draft','sent'])->sortByDesc('id')->first())
      @php($recipientEmailFor = fn($invitation) => collect([$invitation->player?->user?->email])->merge($invitation->player?->users?->pluck('email') ?? collect())->first(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)))
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <div><h5 class="mb-1">{{ $eventRegion->region?->region_name }}</h5><span class="text-muted small">{{ $regionTeams->count() }} teams · {{ $regionTeams->sum('num_team_members') }} configured places</span></div>
            @if($activeImport)
              <span class="badge bg-label-{{ $activeImport->status === 'sent' ? 'success' : 'warning' }}">{{ ucfirst($activeImport->status) }}</span>
            @elseif($source)
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-label-primary">Series linked</span>
                <form method="POST" action="{{ route('backend.team-selection.unlink', [$event, $source]) }}" onsubmit="return confirm('Unlink this ranking series? Existing event categories and teams will be kept.');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-unlink me-1"></i>Unlink series</button>
                </form>
              </div>
            @else
              <span class="badge bg-label-secondary">Manual/imported or not linked</span>
            @endif
          </div>
          <div class="card-body">
            <form method="POST" action="{{ route('backend.team-selection.link', [$event, $eventRegion]) }}" class="row g-2 align-items-end">@csrf
              <div class="col-lg-7"><label class="form-label">Ranking series</label><select name="series_id" class="form-select" {{ $activeImport ? 'disabled' : '' }} required><option value="">Choose {{ $event->start_date?->format('Y') }} series…</option>@foreach($series as $item)<option value="{{ $item->id }}" @selected($source?->series_id === $item->id)>{{ $item->name }}{{ $readySeriesIds->contains($item->id) ? ' · latest ranking published' : ' · ranking not ready' }}</option>@endforeach</select></div>
              <div class="col-sm-5 col-lg-2"><label class="form-label">Reserves per team</label><input type="number" name="reserve_count" min="0" max="20" value="{{ $source?->reserve_count ?? 2 }}" class="form-control" {{ $activeImport ? 'disabled' : '' }} required></div>
              <div class="col-sm-7 col-lg-3 d-grid"><button class="btn btn-outline-primary" {{ $activeImport ? 'disabled' : '' }}>Link series</button></div>
            </form>

            @if($source && !$activeImport)
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ranking-category-setup-{{ $source->id }}">
                  <i class="ti ti-category-plus me-1"></i>Set up categories &amp; teams
                </button>
                @if($sourceReady && $regionTeams->isNotEmpty())
                  <a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}"><i class="ti ti-download me-1"></i>Import ranked players</a>
                @endif
              </div>
              <div class="form-text">Create the event teams from the ranking categories, then review the ranked-player import.</div>
              @if(!$sourceReady)
                <div class="alert alert-warning mt-3 mb-0"><strong>Player import unavailable:</strong> review and publish a canonical ranking for {{ $source->series?->name }} first. You can still create its categories and teams now.</div>
              @endif
            @elseif($activeImport)
              <div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Selected</th><th>Reserves</th><th>Registered</th><th>Missing account/email</th><th>Ranking snapshot</th></tr></thead><tbody><tr><td>{{ $activeImport->invitations->whereIn('status',['invited','accepted_pending_payment','paid_confirmed'])->count() }}</td><td>{{ $activeImport->invitations->where('status','reserve')->count() }}</td><td>{{ $activeImport->invitations->where('status','paid_confirmed')->count() }}</td><td>{{ $activeImport->invitations->filter(fn($i) => !$recipientEmailFor($i))->count() }}</td><td><code>{{ $activeImport->ranking_run_id }}</code></td></tr></tbody></table></div>
              <details class="mt-2">
                <summary class="fw-semibold">Review selected players, reserves and email delivery</summary>
                <div class="table-responsive mt-2"><table class="table table-sm align-middle"><thead><tr><th>Player</th><th>Team</th><th>Ranking</th><th>Selection</th><th>Recipient</th><th>Email</th></tr></thead><tbody>
                  @foreach($activeImport->invitations->sortBy([['team_id','asc'],['queue_position','asc']]) as $invitation)
                    @php($recipientEmail = $recipientEmailFor($invitation))
                    @php($delivery = $invitation->emailLogs->sortByDesc('id')->first())
                    <tr><td>{{ $invitation->player?->full_name }}</td><td>{{ $invitation->team?->name }}</td><td>#{{ $invitation->ranking_position }}</td><td>{{ str($invitation->status)->replace('_',' ')->title() }}</td><td>{{ $recipientEmail ?: 'Account link required' }}</td><td>{{ $delivery ? ucfirst($delivery->status) : 'Not sent' }}</td></tr>
                  @endforeach
                </tbody></table></div>
              </details>
              @php($emailLogs = $activeImport->invitations->flatMap->emailLogs)
              @if($activeImport->status === 'sent')
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                  <span class="badge bg-label-secondary">Email queued: {{ $emailLogs->where('status','queued')->count() }}</span>
                  <span class="badge bg-label-success">Sent: {{ $emailLogs->where('status','sent')->count() }}</span>
                  <span class="badge bg-label-danger">Failed: {{ $emailLogs->where('status','failed')->count() }}</span>
                  <span class="badge bg-label-warning">Skipped: {{ $emailLogs->where('status','skipped')->count() }}</span>
                  @if($emailLogs->where('status','failed')->isNotEmpty())
                    <form method="POST" action="{{ route('backend.team-selection.emails.retry', [$event, $activeImport]) }}">@csrf<button class="btn btn-sm btn-outline-danger">Retry failed emails</button></form>
                  @endif
                </div>
                <form method="POST" action="{{ route('backend.team-selection.deadlines.extend', [$event, $activeImport]) }}" class="row g-2 align-items-end mt-2">@csrf @method('PATCH')
                  <div class="col-md-4"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="{{ $activeImport->response_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                  <div class="col-md-4"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="{{ $activeImport->payment_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                  <div class="col-md-4 d-grid"><button class="btn btn-outline-primary">Extend deadlines</button></div>
                </form>
              @endif
              @if($activeImport->status === 'draft')
                <form method="POST" action="{{ route('backend.team-selection.send', [$event, $activeImport]) }}" class="row g-2 align-items-end mt-2">@csrf
                  <div class="col-md-4"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" class="form-control" required></div>
                  <div class="col-md-4"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" class="form-control" required></div>
                  <div class="col-md-4 d-grid"><button class="btn btn-success">Send invitations</button></div>
                </form>
                <form method="POST" action="{{ route('backend.team-selection.restart', [$event, $activeImport]) }}" class="mt-3" onsubmit="return confirm('Remove this unsent import and clear its generated roster places?');">@csrf<button class="btn btn-sm btn-outline-danger">Restart draft import</button></form>
              @endif
            @endif
          </div>
        </div>
      </div>

      @if($source && !$activeImport && $categorySetup)
        @php($setupRows = $categorySetup['rows'])
        @php($missingSetupRows = $setupRows->reject(fn($row) => $row['ready']))
        <div class="modal fade" id="ranking-category-setup-{{ $source->id }}" tabindex="-1" aria-labelledby="ranking-category-setup-title-{{ $source->id }}" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title" id="ranking-category-setup-title-{{ $source->id }}">Create teams from ranking categories</h5>
                  <div class="text-muted small">{{ $eventRegion->region?->region_name }} · {{ $source->series?->name }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  Select the categories this region will enter. Existing event teams are preserved; only missing categories, team links and empty roster places are created.
                </div>
                @if($setupRows->isEmpty())
                  <div class="alert alert-warning mb-0">This series has no ranking categories yet. Add its ranking lists first.</div>
                @else
                  <form id="ranking-category-form-{{ $source->id }}" method="POST" action="{{ route('backend.team-selection.teams.create', [$event, $source]) }}">
                    @csrf
                    <input type="hidden" name="setup_source" value="{{ $source->id }}">
                    <div class="table-responsive">
                      <table class="table align-middle mb-0">
                        <thead><tr><th style="width:48px">Use</th><th>Ranking category</th><th>Ranked</th><th>Event status</th><th>Team name</th><th style="width:140px">Players in team</th></tr></thead>
                        <tbody>
                          @foreach($setupRows as $rowIndex => $row)
                            @php($ready = $row['ready'])
                            <tr>
                              <td>
                                @if($ready)
                                  <i class="ti ti-circle-check text-success" aria-label="Ready"></i>
                                @else
                                  <input type="hidden" name="categories[{{ $rowIndex }}][selected]" value="0">
                                  <input class="form-check-input" type="checkbox" name="categories[{{ $rowIndex }}][selected]" value="1" checked aria-label="Create {{ $row['category_name'] }} team">
                                @endif
                              </td>
                              <td><strong>{{ $row['category_name'] }}</strong></td>
                              <td>{{ $categorySetup['published_ready'] ? $row['ranked_count'] : 'Pending publication' }}</td>
                              <td>
                                @if($ready)
                                  <span class="badge bg-label-success">Team ready</span>
                                @elseif($row['team'])
                                  <span class="badge bg-label-info">Existing team will be linked</span>
                                @elseif($row['event_category'])
                                  <span class="badge bg-label-warning">Category exists · team missing</span>
                                @else
                                  <span class="badge bg-label-secondary">New category &amp; team</span>
                                @endif
                              </td>
                              <td>
                                @if($ready)
                                  {{ $row['team']->name }}
                                @else
                                  <input type="hidden" name="categories[{{ $rowIndex }}][ranking_list_id]" value="{{ $row['ranking_list_id'] }}">
                                  <input type="text" class="form-control" name="categories[{{ $rowIndex }}][team_name]" value="{{ old("categories.$rowIndex.team_name", $row['team']?->name ?: $row['suggested_team_name']) }}" required maxlength="255">
                                @endif
                              </td>
                              <td>
                                @if($ready)
                                  {{ $row['team']->num_team_members }}
                                @else
                                  <input type="number" class="form-control" name="categories[{{ $rowIndex }}][num_players]" value="{{ old("categories.$rowIndex.num_players", $row['team']?->num_team_members) }}" min="1" max="50" placeholder="Required" required>
                                @endif
                              </td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </form>
                @endif
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                @if($missingSetupRows->isNotEmpty())
                  <button type="submit" form="ranking-category-form-{{ $source->id }}" class="btn btn-primary">Create selected teams</button>
                @elseif($categorySetup['published_ready'])
                  <a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}">Continue to ranked-player preview</a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif
    @endforeach
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const sourceId = @json(session('open_team_setup_source') ?: old('setup_source'));
  if (sourceId && typeof bootstrap !== 'undefined') {
    const modal = document.getElementById(`ranking-category-setup-${sourceId}`);
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  document.querySelectorAll('[id^="ranking-category-form-"]').forEach(function (form) {
    form.querySelectorAll('input[type="checkbox"][name$="[selected]"]').forEach(function (checkbox) {
      const row = checkbox.closest('tr');
      const toggleInputs = function () {
        row.querySelectorAll('input[name$="[team_name]"], input[name$="[num_players]"]').forEach(function (input) {
          input.disabled = !checkbox.checked;
        });
      };
      checkbox.addEventListener('change', toggleInputs);
      toggleInputs();
    });
  });
});
</script>
@endsection
