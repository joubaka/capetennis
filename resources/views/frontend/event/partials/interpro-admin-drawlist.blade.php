{{-- 🔹 ADMIN DRAW LIST (SEPARATE) --}}
@if($isAdmin)
<div class="card mb-4 border border-warning">
  <div class="card-header bg-warning text-dark">
    <small class="card-text text-uppercase fw-bold">
      Admin Draw List
    </small>
  </div>

  <div class="card-body">

    {{-- 🔸 Unpublished Draws --}}
    <h6 class="fw-bold text-danger mb-3">Unpublished Draws</h6>

    @forelse($eventDraws->where('published', false)
        ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

      <div class="fw-bold mt-2">{{ $typeName }}</div>

      <div class="d-flex flex-wrap gap-2 mt-1">
        @foreach($draws as $draw)
          <div class="btn-group">

            {{-- Public view --}}
            <a href="{{ route('public.roundrobin.show', $draw->id) }}"
               class="btn btn-sm btn-outline-danger">
              {{ $draw->drawName }}
              <span class="badge bg-danger ms-1">Not published</span>
            </a>

            {{-- NEW: Admin score entry --}}
            <a href="{{ route('backend.roundrobin.admin.scores', $draw->id) }}"
               class="btn btn-sm btn-warning">
              Enter Scores
            </a>

          </div>
        @endforeach
      </div>

    @empty
      <div class="alert alert-secondary m-0">No unpublished draws.</div>
    @endforelse


    {{-- 🔸 Published Draws --}}
    <h6 class="fw-bold text-success mt-4 mb-3">Published Draws</h6>

    @forelse($eventDraws->where('published', true)
        ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

      <div class="fw-bold mt-2">{{ $typeName }}</div>

      <div class="d-flex flex-wrap gap-2 mt-1">
        @foreach($draws as $draw)
          <div class="btn-group">

            {{-- Public view --}}
            <a href="{{ route('public.roundrobin.show', $draw->id) }}"
               class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'primary' }}">
              {{ $draw->drawName }}
            </a>

            {{-- NEW: Admin score entry --}}
            <a href="{{ route('backend.roundrobin.admin.scores', $draw->id) }}"
               class="btn btn-sm btn-warning">
              Enter Scores
            </a>

          </div>
        @endforeach
      </div>

    @empty
      <div class="alert alert-secondary m-0">No published draws.</div>
    @endforelse

  </div>
</div>
@endif
