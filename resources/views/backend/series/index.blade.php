@extends('layouts.backend')

@section('title', 'Series')

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Series</h4>
    <a href="{{ route('series.create') }}" class="btn btn-primary">
      Create Series
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Events</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($series as $s)
            <tr>
              <td>
                <strong>{{ $s->name }}</strong>
              </td>
              <td>
                {{ $s->events_count }}
              </td>
              <td>
                <span class="badge bg-{{ $s->active ? 'success' : 'secondary' }}">
                  {{ $s->active ? 'Active' : 'Inactive' }}
                </span>
                @php($rankingStatus = $s->ranking_status)
                <span class="badge bg-label-{{ match($rankingStatus) {
                  'published' => $s->leaderboard_published ? 'success' : 'secondary',
                  'reviewed' => 'info',
                  'calculated' => 'warning',
                  default => 'secondary'
                } }} ms-1">
                  @if($rankingStatus === 'published' && !$s->leaderboard_published)
                    Rankings hidden
                  @else
                    Rankings {{ $rankingStatus ? ucfirst($rankingStatus) : 'not built' }}
                  @endif
                </span>
              </td>
              <td class="text-end">
                <a href="{{ route('series.show', $s) }}"
                   class="btn btn-sm btn-outline-primary">
                  View
                </a>
                <a href="{{ route('series.events', $s) }}"
                   class="btn btn-sm btn-outline-secondary">
                  Events
                </a>
                <a href="{{ route('ranking.series.list', $s) }}"
                   class="btn btn-sm btn-outline-primary ms-1">
                  Rankings
                </a>
                @can('update', $s)
                  @if($rankingStatus === 'calculated')
                    <button type="button"
                            class="btn btn-sm btn-info ms-1 ranking-action"
                            data-url="{{ route('ranking.series.ranking.review', $s) }}"
                            data-confirm="Mark the calculated rankings for {{ $s->name }} as reviewed?">
                      Mark Reviewed
                    </button>
                  @elseif($rankingStatus === 'reviewed')
                    <button type="button"
                            class="btn btn-sm btn-success ms-1 ranking-action"
                            data-url="{{ route('ranking.series.ranking.publish', $s) }}"
                            data-confirm="Publish the reviewed rankings for {{ $s->name }}?">
                      Publish Rankings
                    </button>
                  @elseif($rankingStatus === 'published')
                    <button type="button"
                            class="btn btn-sm {{ $s->leaderboard_published ? 'btn-outline-warning' : 'btn-outline-success' }} ms-1 ranking-action"
                            data-url="{{ route('ranking.series.update', $s) }}"
                            data-payload='@json(["best_num_of_scores" => $s->best_num_of_scores, "leaderboard_published" => $s->leaderboard_published ? 0 : 1])'
                            data-confirm="{{ $s->leaderboard_published ? 'Hide' : 'Show' }} the published rankings for {{ $s->name }} on the public website?">
                      {{ $s->leaderboard_published ? 'Hide Rankings' : 'Show Rankings' }}
                    </button>
                  @endif
                @endcan
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-3">
                No series created yet
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
document.querySelectorAll('.ranking-action').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!window.confirm(btn.dataset.confirm)) return;

    btn.disabled = true;

    try {
      const payload = btn.dataset.payload ? JSON.parse(btn.dataset.payload) : null;
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: payload ? JSON.stringify(payload) : null
      });

      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'Ranking action failed');

      if (window.toastr) {
        toastr.success(data.message || 'Ranking status updated');
      }
      window.location.reload();
    } catch (e) {
      console.error('Ranking action failed', e);
      if (window.toastr) {
        toastr.error(e.message || 'Ranking action failed');
      } else {
        window.alert(e.message || 'Ranking action failed');
      }
    } finally {
      btn.disabled = false;
    }
  });
});
</script>
@endsection
