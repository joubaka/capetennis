@extends('layouts.backend')

@section('title', $series->name)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-1">{{ $series->name }}</h3>
        <div class="text-muted">
          {{ $series->year }} • Best {{ $stats['best_of'] }} results
        </div>
      </div>

      <span class="badge {{ $stats['published'] ? 'bg-success' : 'bg-secondary' }}">
        {{ $stats['published'] ? 'Published' : 'Draft' }}
      </span>
    </div>
  </div>

  @php
    $seriesWorkflowStep = match (true) {
      $stats['events'] === 0 => 1,
      ! $activeRankingStatus => 2,
      $activeRankingStatus === 'calculated' => 3,
      $series->leaderboard_published => 5,
      default => 4,
    };
    $seriesNextStep = match (true) {
      $stats['events'] === 0 => [
        'title' => 'Add the first event',
        'message' => 'Use Manage Events to add the tournaments that must count towards this series.',
      ],
      $activeRankingStatus === 'calculated' => [
        'title' => 'Review the ranking, then accept it as correct',
        'message' => 'Use Review Rankings to inspect the totals, scores and tie decisions. Only use Mark Rankings Reviewed after you are satisfied that it is correct.',
      ],
      $activeRankingStatus === 'reviewed' => [
        'title' => $reviewCampaign ? 'Finalize and publish the ranking' : 'Share the ranking for participant review',
        'message' => $reviewCampaign
          ? 'Open Participant Review to check replies and delivery, then finalize and publish when the review is complete.'
          : 'Open Ranking Lists and use Share for Review. Publishing does not send ranking emails automatically.',
      ],
      $activeRankingStatus === 'published' && ! $series->leaderboard_published => [
        'title' => 'Make the published ranking visible',
        'message' => 'The ranking is published but its public leaderboard is off. Open Series Settings to switch on public visibility when you are ready.',
      ],
      $series->leaderboard_published => [
        'title' => 'The ranking is live',
        'message' => 'Use View Published Rankings to check the public page. Recalculate only when event results or ranking rules have changed.',
      ],
      default => [
        'title' => 'Complete the setup, then calculate',
        'message' => 'Confirm the events, series settings and points allocation. Then use Recalculate Rankings to build the first ranking.',
      ],
    };
  @endphp

  <div class="card mb-4 border-primary-subtle">
    <div class="card-body">
      <div class="d-flex align-items-start gap-3 mb-3">
        <span class="avatar avatar-sm flex-shrink-0">
          <span class="avatar-initial rounded bg-label-primary">
            <i class="ti ti-route"></i>
          </span>
        </span>
        <div>
          <h5 class="mb-1">Ranking progress</h5>
          <p class="text-muted mb-0">Your current stage and the available next actions are shown below.</p>
        </div>
      </div>

      <div class="row g-3 small">
        @php
          $stepBadgeClass = fn (int $step) => match (true) {
            $step < $seriesWorkflowStep => 'bg-label-success',
            $step === $seriesWorkflowStep => 'bg-primary',
            default => 'bg-label-secondary',
          };
          $stepState = fn (int $step) => match (true) {
            $step < $seriesWorkflowStep => 'Done',
            $step === $seriesWorkflowStep => 'Current',
            default => 'Next',
          };
        @endphp

        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 {{ $seriesWorkflowStep === 1 ? 'border-primary bg-label-primary' : '' }}">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge {{ $stepBadgeClass(1) }} me-1">1</span> Set up</div>
            <span class="badge {{ $stepBadgeClass(1) }}">{{ $stepState(1) }}</span>
          </div>
          <div class="text-muted">Add the events, choose how many results count, and confirm the points allocation.</div>
          @if($seriesWorkflowStep === 1)
            <div class="d-grid gap-1 mt-3">
              <a href="{{ route('series.events', $series) }}" class="btn btn-sm btn-primary">Manage Events</a>
              <a href="{{ route('series.settings', $series) }}" class="btn btn-sm btn-outline-primary">Series Settings</a>
              <a href="{{ route('ranking.points', $series) }}" class="btn btn-sm btn-outline-primary">Points Allocation</a>
            </div>
          @endif
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 {{ $seriesWorkflowStep === 2 ? 'border-primary bg-label-primary' : '' }}">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge {{ $stepBadgeClass(2) }} me-1">2</span> Calculate</div>
            <span class="badge {{ $stepBadgeClass(2) }}">{{ $stepState(2) }}</span>
          </div>
          <div class="text-muted">Build the ranking after the event results and ranking rules are ready.</div>
          @if($seriesWorkflowStep === 2)
            <div class="alert alert-primary py-2 px-3 mt-3 mb-0">Use <strong>Recalculate Rankings</strong> in the Rankings panel below.</div>
          @endif
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 {{ $seriesWorkflowStep === 3 ? 'border-primary bg-label-primary' : '' }}">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge {{ $stepBadgeClass(3) }} me-1">3</span> Review</div>
            <span class="badge {{ $stepBadgeClass(3) }}">{{ $stepState(3) }}</span>
          </div>
          <div class="text-muted">Inspect totals, scores, exclusions and tie decisions, then accept the ranking as correct.</div>
          @if($seriesWorkflowStep === 3)
            <div class="d-grid gap-1 mt-3">
              <a href="{{ route('ranking.series.list', $series) }}" class="btn btn-sm btn-primary">Review Rankings</a>
              <a href="{{ route('ranking.series.audit', $series) }}" class="btn btn-sm btn-outline-primary">Audit Rankings</a>
              <div class="text-primary mt-1"><i class="ti ti-arrow-down me-1"></i>Then mark it reviewed below.</div>
            </div>
          @endif
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 {{ $seriesWorkflowStep === 4 ? 'border-primary bg-label-primary' : '' }}">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge {{ $stepBadgeClass(4) }} me-1">4</span> Publish</div>
            <span class="badge {{ $stepBadgeClass(4) }}">{{ $stepState(4) }}</span>
          </div>
          <div class="text-muted">Share for participant review if required, finalize, publish and control public visibility.</div>
          @if($seriesWorkflowStep >= 4)
            <div class="d-grid gap-1 mt-3">
              @if($activeRankingStatus === 'reviewed')
                <a href="{{ route('ranking.series.list', $series) }}" class="btn btn-sm btn-primary">
                  {{ $reviewCampaign ? 'Open Participant Review' : 'Share for Review' }}
                </a>
                <div class="text-primary mt-1"><i class="ti ti-arrow-down me-1"></i>Publish from the Rankings panel below.</div>
              @elseif($series->leaderboard_published)
                <a href="{{ route('frontend.ranking.show', $series) }}" class="btn btn-sm btn-success">View Published Rankings</a>
              @else
                <a href="{{ route('series.settings', $series) }}" class="btn btn-sm btn-primary">Open Series Settings</a>
              @endif
            </div>
          @endif
          </div>
        </div>
      </div>

      <div class="alert alert-primary d-flex align-items-start gap-2 mt-3 mb-0 py-2" role="status">
        <i class="ti ti-arrow-right mt-1"></i>
        <div><strong>What to do next: {{ $seriesNextStep['title'] }}.</strong> {{ $seriesNextStep['message'] }}</div>
      </div>
    </div>
  </div>

  <div class="row g-3">

    {{-- RANKINGS --}}
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-trophy ti-md text-success"></i>
          <h5 class="mb-0">Rankings</h5>
        </div>

        <div class="card-body d-grid gap-2">
          @if($series->leaderboard_published)
            <a href="{{ route('frontend.ranking.show', $series) }}"
               class="btn btn-success">
              View Published Rankings
            </a>
          @elseif($activeRankingStatus === 'reviewed')
            <a href="{{ route('ranking.series.list', $series) }}" class="btn btn-outline-success">
              <i class="ti ti-mail-forward me-1"></i>{{ $reviewCampaign ? 'View Participant Review' : 'Share Rankings for Review' }}
            </a>
            <button type="button"
                    class="btn btn-success ranking-lifecycle-action"
                    data-url="{{ route('ranking.series.ranking.publish', $series) }}"
                    data-confirm="{{ $reviewCampaign ? 'Close the participant review and publish these rankings? No ranking email will be sent.' : 'Publish this reviewed ranking? No ranking email will be sent.' }}">
              <i class="ti ti-world-upload me-1"></i>{{ $reviewCampaign ? 'Finalize & Publish Rankings' : 'Publish Rankings' }}
            </button>
          @elseif($activeRankingStatus === 'calculated')
            <a href="{{ route('ranking.series.list', $series) }}"
               class="btn btn-outline-info">
              <i class="ti ti-list-check me-1"></i>Review Rankings
            </a>
            <button type="button"
                    class="btn btn-info ranking-lifecycle-action"
                    data-url="{{ route('ranking.series.ranking.review', $series) }}"
                    data-modal-title="Accept ranking as correct?"
                    data-confirm="This confirms that you have reviewed the ranking totals, scores and tie decisions and accept them as correct. You can publish the ranking after this step."
                    data-confirm-label="Yes, Mark as Reviewed">
              <i class="ti ti-check me-1"></i>Mark Rankings Reviewed
            </button>
            <small class="text-muted">Review the details first. Marking reviewed records your acceptance and unlocks publication.</small>
          @else
            <button type="button" class="btn btn-outline-secondary" disabled>
              No Rankings Ready to Publish
            </button>
          @endif

          <form method="POST" action="{{ route('ranking.calculate', $series) }}">
            @csrf
            <button class="btn btn-outline-success w-100">
              Recalculate Rankings
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- SERIES SETUP --}}
    <div class="col-xl-4 col-md-6">
      <div class="card h-100 border-start border-warning border-3">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-adjustments ti-md text-warning"></i>
          <h5 class="mb-0">Series Setup</h5>
        </div>

      <div class="card-body d-grid gap-2">

  <a href="{{ route('series.events', $series) }}"
     class="btn btn-outline-secondary">
    Manage Events
  </a>

  <a href="{{ route('series.settings', $series) }}"
     class="btn btn-outline-warning">
    Series Settings
  </a>

  <a href="{{ route('ranking.points', $series) }}"
     class="btn btn-outline-primary">
    Points Allocation
  </a>

  <a href="{{ route('ranking.series.list', $series) }}"
     class="btn btn-outline-info">
    Ranking Lists
  </a>

  <a href="{{ route('ranking.series.audit', $series) }}"
     class="btn btn-outline-secondary">
    <i class="ti ti-clipboard-check me-1"></i>
    Audit Rankings
  </a>

  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#seriesEmailModal">
    <i class="ti ti-mail me-1"></i> Email All Players
  </button>

</div>

      </div>
    </div>

    {{-- QUICK STATS --}}
    <div class="col-xl-4 col-md-12">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-chart-bar ti-md text-info"></i>
          <h5 class="mb-0">Series Info</h5>
        </div>

        <div class="card-body">
          <ul class="list-unstyled mb-0 d-grid gap-1">
            <li>
              Events
              <span class="fw-semibold float-end">{{ $stats['events'] }}</span>
            </li>
            <li>
              Rank Type
              <span class="fw-semibold float-end">{{ $stats['rank_type'] }}</span>
            </li>
            <li>
              Best Results Counted
              <span class="fw-semibold float-end">{{ $stats['best_of'] }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

  </div>

  {{-- EVENTS --}}
  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0">Events in Series</h5>
    </div>

    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($series->events as $event)
            <tr>
              <td>{{ $event->name }}</td>
              <td>{{ optional($event->start_date)->format('d M Y') }}</td>
              <td class="text-end">
                <a href="{{ route('admin.events.overview', $event) }}"
                   class="btn btn-sm btn-outline-primary">
                  Open Event
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- RANKING LIFECYCLE CONFIRMATION MODAL --}}
<div class="modal fade" id="rankingLifecycleModal" tabindex="-1" aria-labelledby="rankingLifecycleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rankingLifecycleModalLabel">Confirm ranking action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="rankingLifecycleModalMessage"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
        <button type="button" class="btn btn-info" id="confirmRankingLifecycleAction">
          Confirm
        </button>
      </div>
    </div>
  </div>
</div>

{{-- EMAIL ALL PLAYERS MODAL --}}
<div class="modal fade" id="seriesEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Email All Players in {{ $series->name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">From Name</label>
          <input type="text" id="seriesEmailFromName" class="form-control" value="Cape Tennis Admin">
        </div>
        <div class="mb-3">
          <label class="form-label">Reply To</label>
          <input type="email" id="seriesEmailReplyTo" class="form-control"
                 value="{{ auth()->user()->email ?? '' }}" placeholder="your@email.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Subject <span class="text-danger">*</span></label>
          <input type="text" id="seriesEmailSubject" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Message <span class="text-danger">*</span></label>
          <div id="seriesEmailEditor" style="min-height: 200px;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="btnSendSeriesEmail">
          <i class="ti ti-send me-1"></i> Send to All Players
        </button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const lifecycleModalElement = document.getElementById('rankingLifecycleModal');
  const lifecycleModal = new bootstrap.Modal(lifecycleModalElement);
  const lifecycleModalTitle = document.getElementById('rankingLifecycleModalLabel');
  const lifecycleModalMessage = document.getElementById('rankingLifecycleModalMessage');
  const lifecycleConfirmButton = document.getElementById('confirmRankingLifecycleAction');
  let pendingLifecycleButton = null;

  document.querySelectorAll('.ranking-lifecycle-action').forEach(button => {
    button.addEventListener('click', function () {
      pendingLifecycleButton = button;
      lifecycleModalTitle.textContent = button.dataset.modalTitle || 'Confirm ranking action';
      lifecycleModalMessage.textContent = button.dataset.confirm;
      lifecycleConfirmButton.textContent = button.dataset.confirmLabel || 'Confirm';
      lifecycleConfirmButton.className = button.classList.contains('btn-success')
        ? 'btn btn-success'
        : 'btn btn-info';
      lifecycleModal.show();
    });
  });

  lifecycleConfirmButton.addEventListener('click', async function () {
      const button = pendingLifecycleButton;
      if (!button) return;

      button.disabled = true;
      lifecycleConfirmButton.disabled = true;

      try {
        const response = await fetch(button.dataset.url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
          },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Ranking action failed.');

        toastr.success(payload.message);
        lifecycleModal.hide();
        window.location.reload();
      } catch (error) {
        toastr.error(error.message || 'Ranking action failed.');
        button.disabled = false;
        lifecycleConfirmButton.disabled = false;
      }
  });

  lifecycleModalElement.addEventListener('hidden.bs.modal', function () {
    pendingLifecycleButton = null;
    lifecycleConfirmButton.disabled = false;
  });

  const quill = new Quill('#seriesEmailEditor', {
    theme: 'snow',
    placeholder: 'Compose your message...',
  });

  document.getElementById('btnSendSeriesEmail').addEventListener('click', function () {
    const btn = this;
    const subject = document.getElementById('seriesEmailSubject').value.trim();
    const message = quill.root.innerHTML.trim();

    if (!subject) {
      toastr.error('Subject is required.');
      return;
    }
    if (!message || message === '<p><br></p>') {
      toastr.error('Message is required.');
      return;
    }

    if (!confirm('Are you sure you want to email ALL players in this series?')) {
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

    fetch("{{ route('series.email.players', $series) }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        emailSubject: subject,
        message: message,
        fromName: document.getElementById('seriesEmailFromName').value.trim(),
        replyTo: document.getElementById('seriesEmailReplyTo').value.trim(),
      }),
    })
    .then(r => {
      if (!r.ok) throw new Error('Server returned ' + r.status);
      return r.json();
    })
    .then(data => {
      if (data.success) {
        toastr.success(data.message, 'Emails Queued', { timeOut: 5000, closeButton: true });
        bootstrap.Modal.getInstance(document.getElementById('seriesEmailModal')).hide();

        // Show persistent success alert on page
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show mt-3';
        alert.innerHTML = '<i class="ti ti-check me-2"></i><strong>Done!</strong> ' + data.message +
          '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.querySelector('.container-xl').prepend(alert);
      } else {
        toastr.error(data.message || 'Failed to send emails.', 'Error');
      }
    })
    .catch(err => {
      console.error(err);
      toastr.error('An error occurred while sending emails. Check the console for details.', 'Error', { timeOut: 5000 });
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-send me-1"></i> Send to All Players';
    });
  });
});
</script>
@endsection
