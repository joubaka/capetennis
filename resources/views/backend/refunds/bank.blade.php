@extends('layouts/layoutMaster')

@section('title', 'Bank Refunds')

@section('content')
<div class="container-xl">

  <h4 class="mb-3">Pending Bank Refunds</h4>

  @if(app()->environment('local'))
    <div class="mb-2">
      <small class="text-muted">Debug - registration pending: {{ $refunds->count() ?? 0 }} | team pending: {{ $pendingTeamRefunds->count() ?? 0 }}</small>
      @if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count())
        <div class="small mt-1">Team IDs: {{ $pendingTeamRefunds->pluck('id')->join(', ') }}</div>
      @endif
    </div>
  @endif

  @if((empty($refunds) || $refunds->isEmpty()) && (empty($pendingTeamRefunds) || $pendingTeamRefunds->isEmpty()))
    <div class="alert alert-success">
      No pending bank refunds 🎉
    </div>
  @else

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Event</th>
            <th>Player(s)</th>
            <th>User</th>
            <th>Gross</th>
            <th>Fee</th>
            <th>Net</th>
            <th>Withdrawn</th>
            <th></th>
          </tr>
        </thead>
        <tbody>

        @foreach($refunds as $reg)
          <tr>
            <td>
              <strong>{{ $reg->categoryEvent->event->name }}</strong><br>
              <small class="text-muted">
                {{ $reg->categoryEvent->name ?? '' }}
              </small>
            </td>

            <td>{{ $reg->display_name }}</td>

            <td>
              {{ $reg->registration->user->name ?? '—' }}<br>
              <small class="text-muted">
                {{ $reg->registration->user->email ?? '' }}
              </small>
            </td>

            <td>R{{ number_format($reg->refund_gross, 2) }}</td>
            <td class="text-danger">
              R{{ number_format($reg->refund_fee, 2) }}
            </td>
            <td class="fw-bold text-success">
              R{{ number_format($reg->refund_net, 2) }}
            </td>

            <td>
              {{ optional($reg->withdrawn_at)->format('Y-m-d') }}
            </td>

            <td class="text-end">
              <a href="{{ route('admin.refunds.bank.show', $reg) }}" class="btn btn-sm btn-outline-primary me-1">
                👁 View
              </a>
              <form method="POST"
                    action="{{ route('admin.refunds.bank.complete', $reg) }}"
                    onsubmit="return confirm('Mark this bank refund as paid?');"
                    class="d-inline">
                @csrf
                <button class="btn btn-sm btn-success">
                  ✔ Mark Paid
                </button>
              </form>
            </td>
          </tr>
        @endforeach

        {{-- Team refunds appended below --}}
        @if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count())
          <tr>
            <td colspan="8"><strong>Team Refunds</strong></td>
          </tr>
          @foreach($pendingTeamRefunds as $t)
            <tr>
              <td>
                <strong>{{ optional($t->event)->name ?? 'Event #' . ($t->event_id ?? '') }}</strong><br>
                <small class="text-muted">Team ID: {{ $t->team_id }}</small>
              </td>
              <td>{{ optional($t->player)->name ?? 'Player #' . ($t->player_id ?? '') }}</td>
              <td>
                {{ $t->user->name ?? '—' }}<br>
                <small class="text-muted">{{ $t->user->email ?? '' }}</small>
              </td>
              <td>R{{ number_format($t->refund_gross, 2) }}</td>
              <td class="text-danger">R{{ number_format($t->refund_fee, 2) }}</td>
              <td class="fw-bold text-success">R{{ number_format($t->refund_net, 2) }}</td>
              <td>{{ optional($t->updated_at)->format('Y-m-d') }}</td>
              <td class="text-end">
                <form method="POST" action="{{ route('bank.complete.team', $t) }}" onsubmit="return confirm('Mark this team bank refund as paid?');">
                  @csrf
                  <button class="btn btn-sm btn-success">✔ Mark Paid</button>
                </form>
              </td>
            </tr>
          @endforeach
        @endif

        </tbody>
      </table>
    </div>
  </div>

  @endif

</div>
@endsection
