@extends('layouts/layoutMaster')

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

  <div class="row g-3">

    {{-- RANKINGS --}}
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-trophy ti-md text-success"></i>
          <h5 class="mb-0">Rankings</h5>
        </div>

        <div class="card-body d-grid gap-2">
          <a href="{{ route('ranking.frontend.show', $series) }}"
             class="btn btn-success">
            View Rankings
          </a>

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

  <a href="{{ route('ranking.points.update', $series) }}"
     class="btn btn-outline-primary">
    Points Allocation
  </a>

  <a href="{{ route('ranking.series.list', $series) }}"
     class="btn btn-outline-info">
    Ranking Lists
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
