

<div class="mb-3 d-flex justify-content-between align-items-center">
  <div>
    <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#createDrawModal">
      <i class="fas fa-plus"></i> Create Draw
    </button>
    <button class="btn btn-danger">
      <i class="fas fa-sort"></i> Change Draw Order
    </button>
  </div>
</div>


@foreach($draws as $draw)
  <div class="border rounded p-3 mb-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between flex-wrap gap-2">
      <div class="flex-grow-1">
        <h5 class="mb-2">{{ $draw->drawName }}</h5>

        {{-- Tags --}}
        <div class="mb-2">
          <span class="badge bg-warning">Individual</span>
          <span class="badge bg-primary">Tennis (Singles)</span>
          <span class="badge bg-danger">{{ $draw->registrations_count }} players</span>
          <span class="badge bg-dark">{{ $draw->gender ?? 'Mixed' }}</span>
          <span class="badge bg-{{ $draw->locked ? 'warning' : 'info' }}">
            {{ $draw->locked ? '🔒 Locked' : '🔓 Unlocked' }}
          </span>
        </div>

        {{-- Completion --}}
        <div class="text-primary small fw-bold mb-1">
          {{ $draw->completion_percent ?? '0%' }} Complete
        </div>
        <div class="progress" style="height: 6px; max-width: 300px;">
          <div class="progress-bar bg-primary" role="progressbar"
               style="width: {{ $draw->completion_percent ?? '0%' }}%;"></div>
        </div>
      </div>

      {{-- Action Buttons --}}
     {{-- Action Buttons --}}
<div class="d-flex align-items-start gap-2 flex-wrap">
  <a href="{{ route('draws.settings', $draw->id) }}" class="btn btn-sm btn-warning">
    <i class="fas fa-cog"></i> Settings
  </a>
  <a href="{{ route('draws.players', $draw->id) }}" class="btn btn-sm btn-primary">
    <i class="fas fa-users"></i> Players
  </a>
  <a href="{{ route('draws.show', $draw->id) }}" target="_blank" class="btn btn-sm btn-success">
    <i class="fas fa-eye"></i> View Draw
  </a>
</div>


    </div>
  </div>
@endforeach

<div class="modal fade" id="createDrawModal" tabindex="-1" aria-labelledby="createDrawModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('draws.generate.from.modal') }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
         <input type="hidden" name="event_id" value="{{ $event->id }}">

          <div class="mb-3">
            <label for="draw_name" class="form-label">Draw Name</label>
            <input type="text" name="draw_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="draw_format_id" class="form-label">Draw Format</label>
            <select name="draw_format_id" class="form-select" required>
              <option value="1">Knockout</option>
              <option value="2">Feed-In</option>
              <option value="3">Round Robin</option>
            </select>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Create Draw</button>
        </div>
      </div>
    </form>
  </div>
</div>
