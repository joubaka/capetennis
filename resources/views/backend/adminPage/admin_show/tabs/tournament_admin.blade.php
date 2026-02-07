<div class="mb-3">
  <div class="d-flex justify-content-between mb-4">
    <div>
      <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#generateDrawModal">
        <i class="fas fa-plus"></i> Create Draw
      </button>
      <button class="btn btn-danger"><i class="fas fa-sort"></i> Change Draw Order</button>
    </div>
  </div>

  @foreach ($eventCategories as $categoryEvent)
    @foreach ($categoryEvent->draws as $draw)
      <div class="border rounded p-3 mb-4 bg-white shadow-sm">
        <div class="d-flex justify-content-between">
          <div>
            <h5 class="mb-2">{{ $draw->drawName }}</h5>
            <div class="mb-2">
              <span class="badge bg-warning">Individual</span>
              <span class="badge bg-primary">Tennis (Singles)</span>
              <span class="badge bg-danger">{{ $draw->registrations_count ?? '0' }} players</span>
              <span class="badge bg-dark">{{ $draw->gender ?? 'Mixed' }}</span>
              <span class="badge bg-{{ $draw->locked ? 'warning' : 'info' }}">
                {{ $draw->locked ? '🔒 Locked' : '🔓 Unlocked' }}
              </span>
            </div>
            <div class="text-primary small fw-bold mb-1">
              {{ $draw->completion_percent ?? '0%' }} Complete
            </div>
            <div class="progress" style="height: 6px; max-width: 300px;">
              <div class="progress-bar bg-primary" role="progressbar"
                   style="width: {{ $draw->completion_percent ?? '0%' }};"></div>
            </div>
          </div>

          <div class="d-flex align-items-start gap-2 flex-wrap">
            <a href="{{ route('category.manage', $draw->category_event_id) }}" class="btn btn-sm btn-warning">
              <i class="fas fa-cog"></i> Settings
            </a>
            <a href="#" class="btn btn-sm btn-orange"><i class="fas fa-users"></i> Players</a>
            <a href="{{ route('draws.show', $draw->id) }}" target="_blank" class="btn btn-sm btn-success">
              <i class="fas fa-eye"></i> View Draw
            </a>
            <form method="POST" action="{{ route('draws.destroy', $draw->id) }}" onsubmit="return confirm('Delete this draw?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash-alt"></i></button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  @endforeach
</div>
