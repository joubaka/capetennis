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
      @php($activeImport = $source?->imports?->whereIn('status', ['draft','sent'])->sortByDesc('id')->first())
      @php($recipientEmailFor = fn($invitation) => collect([$invitation->player?->user?->email])->merge($invitation->player?->users?->pluck('email') ?? collect())->first(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)))
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <div><h5 class="mb-1">{{ $eventRegion->region?->region_name }}</h5><span class="text-muted small">{{ $regionTeams->count() }} teams · {{ $regionTeams->sum('num_team_members') }} configured places</span></div>
            @if($activeImport)<span class="badge bg-label-{{ $activeImport->status === 'sent' ? 'success' : 'warning' }}">{{ ucfirst($activeImport->status) }}</span>@elseif($source)<span class="badge bg-label-primary">Series linked</span>@else<span class="badge bg-label-secondary">Manual/imported or not linked</span>@endif
          </div>
          <div class="card-body">
            <form method="POST" action="{{ route('backend.team-selection.link', [$event, $eventRegion]) }}" class="row g-2 align-items-end">@csrf
              <div class="col-lg-7"><label class="form-label">Ranking series</label><select name="series_id" class="form-select" {{ $activeImport ? 'disabled' : '' }} required><option value="">Choose {{ $event->start_date?->format('Y') }} series…</option>@foreach($series as $item)<option value="{{ $item->id }}" @selected($source?->series_id === $item->id)>{{ $item->name }}{{ $readySeriesIds->contains($item->id) ? ' · latest ranking published' : ' · ranking not ready' }}</option>@endforeach</select></div>
              <div class="col-sm-5 col-lg-2"><label class="form-label">Reserves per team</label><input type="number" name="reserve_count" min="0" max="20" value="{{ $source?->reserve_count ?? 2 }}" class="form-control" {{ $activeImport ? 'disabled' : '' }} required></div>
              <div class="col-sm-7 col-lg-3 d-grid"><button class="btn btn-outline-primary" {{ $activeImport ? 'disabled' : '' }}>Link series</button></div>
            </form>

            @if($source && !$activeImport && $sourceReady)
              <div class="mt-3"><a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}"><i class="ti ti-download me-1"></i>Import ranked players</a><div class="form-text">A review preview opens before any team roster is changed.</div></div>
            @elseif($source && !$activeImport)
              <div class="alert alert-warning mt-3 mb-0"><strong>Import unavailable:</strong> review and publish a canonical ranking for {{ $source->series?->name }} first.</div>
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
    @endforeach
  </div>
</div>
@endsection
