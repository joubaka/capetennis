@extends('layouts.backend')
@section('title', $event->name.' — Regional Trials')
@section('content')
<div class="container-xl py-4">
  @include('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Regional payments, final rankings and team selection.'])
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  <div class="card border-primary mb-3"><div class="card-body"><div class="text-muted small">Current stage</div><h2 class="h5">{{ data_get($journey,'label') }}</h2><p>{{ data_get($journey,'action') }}</p>@if(data_get($journey,'href'))<a class="btn btn-primary" href="{{ data_get($journey,'href') }}">Next action</a>@endif</div></div>
  <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Programme sections">@foreach(['settings'=>'Settings','results'=>'Results','declarations'=>'Declarations','teams'=>'Teams','trial-payments'=>'Trials payments','participation'=>'Participation','eft-review'=>'EFT review','withdrawals'=>'Withdrawals'] as $anchor=>$label)<a class="btn btn-sm btn-outline-secondary" href="#trial-{{ $anchor }}">{{ $label }}</a>@endforeach</nav>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary" href="{{ route('headOffice.show',$event) }}">Draws</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.events.results.individual',$event) }}">Enter results</a>
    <a class="btn btn-outline-warning" href="{{ route('backend.interprovincial-trials.refund-recovery.index',$event) }}">PayFast refund recovery</a>
    <a class="btn btn-outline-primary" href="{{ route('backend.interprovincial-trials.invitations.index', $event) }}">Nominations</a>
    <a class="btn btn-outline-primary" href="{{ route('events.show', $event) }}">Public event page</a>
    <a class="btn btn-outline-primary" href="{{ route('backend.interprovincial-trials.communications.index', $event) }}">Invitations and reminders</a>
  </div>
  <div class="card mb-4"><div id="trial-settings" class="card-body">
    <h5>Regional payment settings</h5>
    <p>Trials use the event entry fee across categories. The participation fee below is separate and equal across selected teams.</p>
    <form method="POST" action="{{ route('backend.interprovincial-trials.programme.settings', $event) }}">@csrf @method('PUT')
      <label class="form-label" for="bank_details">Receiving account details for EFT</label>
      <textarea id="bank_details" name="bank_details" class="form-control mb-3" rows="4" maxlength="2000">{{ old('bank_details', $programme?->bank_details) }}</textarea>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label" for="participation_fee">Participation fee per player (R)</label><input id="participation_fee" name="participation_fee" type="number" min="0" step="0.01" class="form-control" value="{{ old('participation_fee', $programme?->participation_fee ?? 0) }}" required></div>
        <div class="col-md-4"><label class="form-label" for="response_deadline">Common confirmation deadline</label><input id="response_deadline" name="response_deadline" type="datetime-local" class="form-control" value="{{ old('response_deadline', $programme?->response_deadline?->format('Y-m-d\TH:i')) }}"></div>
        <div class="col-md-4"><label class="form-label" for="payment_deadline">Common participation payment deadline</label><input id="payment_deadline" name="payment_deadline" type="datetime-local" class="form-control" value="{{ old('payment_deadline', $programme?->payment_deadline?->format('Y-m-d\TH:i')) }}"></div>
        <div class="col-md-4"><label class="form-label" for="withdrawal_deadline">Participation withdrawal/refund deadline</label><input id="withdrawal_deadline" name="withdrawal_deadline" type="datetime-local" class="form-control" value="{{ old('withdrawal_deadline', $programme?->withdrawal_deadline?->format('Y-m-d\TH:i')) }}"><small>Uses the event withdrawal deadline when left empty.</small></div>
      </div><button class="btn btn-primary">Save settings</button>
    </form>
  </div></div>
  <div class="card mb-4"><div id="trial-results" class="card-body">
    <h5>Trial finishing positions</h5><div class="row g-2 mb-3">@forelse($categoryReadiness as $readiness)<div class="col-md-6"><div class="border rounded p-3"><h6>{{ data_get($readiness,'name') }}</h6><p>{{ data_get($readiness,'message') }}</p>@if(data_get($readiness,'href'))<a class="btn btn-sm btn-outline-primary" href="{{ data_get($readiness,'href') }}">Review category</a>@endif</div></div>@empty<p class="text-muted">No categories configured yet.</p>@endforelse</div>
    @if($programme?->concluded_at)
      <p class="text-success">Trials concluded. Final positions are published automatically.</p>
      @foreach(collect($programme->currentRun?->positions ?? [])->groupBy('category_event_id') as $categoryId => $positions)
        <h6>{{ $event->categoryEvents->firstWhere('id', $categoryId)?->category?->name }}</h6>
        <ol>@foreach($positions as $position)<li>{{ $position['name'] }}</li>@endforeach</ol>
      @endforeach
    @else <p>Trials remain in progress until all required matches and finishing positions are resolved.</p> @endif
    @if(!$draft)
      <form method="POST" action="{{ route('backend.interprovincial-trials.programme.generate', $event) }}">@csrf
        <label class="form-label" for="team_count">Teams to include across all categories</label>
        <select id="team_count" name="team_count" class="form-select mb-3">@for($count = 1; $count <= 6; $count++)<option value="{{ $count }}">{{ implode(', ', array_slice(range('A', 'Z'), 0, $count)) }}</option>@endfor</select>
        <button class="btn btn-primary" @disabled(!$programme?->concluded_at)>Generate draft teams</button>
      </form>
    @endif
  </div></div>
  <div class="card mb-4"><div id="trial-declarations" class="card-body"><h5>Nominee colour declarations</h5><p>Record declarations before generating teams. Changes affecting an existing selection are flagged for review.</p>
    @forelse($declarations as $nomination)<details class="border rounded p-2 mb-2"><summary>{{ $nomination->player?->full_name }} — {{ $nomination->categoryEvent?->category?->name }} — {{ $nomination->player?->is_player_of_colour ? 'Player of colour' : 'Not marked as player of colour' }}</summary>
      <form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.programme.colour', [$event, $nomination->player]) }}">@csrf<select name="is_player_of_colour" class="form-select"><option value="1" @selected($nomination->player?->is_player_of_colour)>Player of colour</option><option value="0" @selected(!$nomination->player?->is_player_of_colour)>Not marked as player of colour</option></select><input name="reason" class="form-control my-2" required maxlength="500" placeholder="Declaration change reason"><button class="btn btn-sm btn-outline-primary">Record declaration</button></form>
    </details>@empty<p class="text-muted">No linked nominee declarations to review.</p>@endforelse {{ $declarations->withQueryString()->fragment('trial-declarations')->links('pagination::bootstrap-5') }}
  </div></div>
  @if($draft)
    <div class="card mb-4"><div class="card-body">
      <h5 id="trial-teams">Teams — {{ ucfirst($draft->status) }}</h5>
      @if($draft->needs_review)
        <div class="alert alert-warning">Trials results changed. Review affected selections; the roster has been retained.</div>
        <form method="POST" action="{{ route('backend.interprovincial-trials.programme.review', [$event, $draft]) }}">@csrf
          <label class="form-label" for="review_reason">Review decision/reason</label><input id="review_reason" name="reason" class="form-control mb-2" required maxlength="500"><button class="btn btn-warning">Record review and retain roster</button>
        </form>
      @endif
      @foreach($slots->groupBy('category_event_id') as $categorySlots)
        <h6 class="mt-3">{{ $categorySlots->first()->categoryEvent?->category?->name }}</h6>
        <div class="table-responsive"><table class="table"><thead><tr><th>Team</th><th>Place</th><th>Player</th><th>Selection</th><th>Response</th></tr></thead><tbody>
        @foreach($categorySlots as $slot)<tr><td>{{ $slot->tier }}</td><td>{{ $slot->reserve ? 'Reserve '.($slot->slot - 6) : $slot->slot }}</td><td>{{ $slot->player?->full_name ?? ($slot->requires_colour ? 'Vacant required place' : 'Vacant') }}</td><td>{{ $slot->player?->is_player_of_colour ? 'Player of colour' : ($slot->requires_colour && !$slot->player_id ? 'Player of colour required' : 'Merit') }}</td><td>{{ ucfirst($slot->response) }}</td></tr>@endforeach
        </tbody></table></div>
      @endforeach
      @if(in_array($draft->status, ['draft', 'finalised']))
        <form method="POST" action="{{ route('backend.interprovincial-trials.programme.swap', [$event, $draft]) }}" class="border rounded p-3 mb-3">@csrf
          <h6>Adjust selection</h6><p>Choose two places in the same age/gender category to swap. Use replacement approval for reserve promotions after finalisation.</p>
          <div class="row g-2 mb-2">@foreach(['first_slot' => 'First place', 'second_slot' => 'Second place'] as $field => $label)<div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}" class="form-select">@foreach($slots as $slot)<option value="{{ $slot->id }}">{{ $slot->categoryEvent?->category?->name }} / {{ $slot->tier }} / {{ $slot->slot }} — {{ $slot->player?->full_name ?? 'Vacant' }}</option>@endforeach</select></div>@endforeach</div>
          <label for="swap_reason" class="form-label">Reason</label><input id="swap_reason" name="reason" class="form-control mb-2" required maxlength="500"><button class="btn btn-outline-primary">Swap and validate selection</button>
        </form>
        @if($draft->status === 'draft')<form method="POST" action="{{ route('backend.interprovincial-trials.programme.finalise', [$event, $draft]) }}">@csrf
          <label class="d-block mb-3"><input type="checkbox" name="allow_vacancies" value="1"> I acknowledge any vacant team places and will fill them with eligible players.</label>
          <button class="btn btn-success" @disabled($draft->needs_review)>Finalise and publish all teams</button>
        </form>@endif
      @endif
      @if($draft->status === 'finalised')<p class="text-success">All teams finalised. Invitations are optional.</p>@endif
      <h6 class="mt-4">Player declarations and administrative decisions</h6>
      @foreach($slots->whereNotNull('player_id') as $slot)
        <details class="border rounded p-2 mb-2"><summary>{{ $slot->player?->full_name }} — {{ $slot->tier }} / {{ $slot->slot }}
          @if(!$slot->reserve && $programme?->response_deadline?->isPast() && $slot->response === 'pending')<span class="badge bg-warning">Response overdue: follow up</span>@endif
          @if(!$slot->reserve && $slot->response !== 'declined' && $programme?->payment_deadline?->isPast() && !($paymentByPlayer->get($slot->player_id)?->isPaid() ?? false))<span class="badge bg-warning">Payment overdue: follow up</span>@endif
        </summary>
          <form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.programme.remove', [$event, $slot]) }}">@csrf<input name="reason" class="form-control mb-2" required maxlength="500" placeholder="Reason for removing this selected player"><button class="btn btn-sm btn-outline-danger">Remove player and retain vacant place</button></form>
          <form method="POST" action="{{ route('backend.interprovincial-trials.programme.colour', [$event, $slot->player]) }}" class="mt-2">@csrf
            <label class="form-label">Colour declaration</label><select name="is_player_of_colour" class="form-select"><option value="1" @selected($slot->player?->is_player_of_colour)>Player of colour</option><option value="0" @selected(!$slot->player?->is_player_of_colour)>Not marked as player of colour</option></select>
            <input name="reason" class="form-control my-2" required maxlength="500" placeholder="Reason for declaration change"><button class="btn btn-sm btn-outline-primary">Record declaration</button>
          </form>
          @if($draft->status === 'finalised' && !$slot->reserve)
            @if(!$paymentByPlayer->has($slot->player_id) && $slot->response !== 'declined')
              <form method="POST" class="mt-3" action="{{ route('backend.interprovincial-trials.participations.collect', [$event, $slot]) }}">@csrf<label class="form-label">Participation payment received reference</label><input name="reference" class="form-control mb-2" required minlength="3" maxlength="120"><button class="btn btn-success btn-sm">Create checkout and record payment received</button></form>
            @endif
            <form method="POST" action="{{ route('backend.interprovincial-trials.programme.respond', [$event, $slot]) }}" class="mt-3">@csrf
              <label class="form-label">Set or reverse response</label><select name="response" class="form-select">@foreach(['pending', 'confirmed', 'declined'] as $response)<option @selected($slot->response === $response)>{{ $response }}</option>@endforeach</select>
              <input name="reason" class="form-control my-2" required maxlength="500" placeholder="Reason for administrative decision"><button class="btn btn-sm btn-outline-primary">Record response</button>
            </form>
          @endif
        </details>
      @endforeach
      @if($draft->status === 'finalised')
        <h6 class="mt-4">Replacement review</h6>
        @foreach($slots->where('reserve', false)->filter(fn($s) => !$s->player_id || $s->response === 'declined') as $slot)
          <form method="POST" class="mb-2" action="{{ route('backend.interprovincial-trials.programme.propose', [$event, $slot]) }}">@csrf<label class="form-label">{{ $slot->categoryEvent?->category?->name }} / {{ $slot->tier }} place {{ $slot->slot }} replacement</label><select name="source_slot" class="form-select mb-2"><option value="">Automatically propose by finishing position and criteria</option>@foreach(collect(data_get($replacementCandidates, $slot->id, [])) as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->tier }} / {{ $candidate->slot }} — {{ $candidate->player?->full_name }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary" @disabled($draft->needs_review)>Prepare replacement proposal</button></form>
        @endforeach
        @foreach($proposals->where('status', 'pending') as $proposal)
          <div class="border rounded p-2 mb-2">{{ $proposal->source?->player?->full_name ?? 'No eligible replacement available' }} → {{ $proposal->target?->tier }} / {{ $proposal->target?->slot }}
          @if($proposal->source_player_id)<form method="POST" action="{{ route('backend.interprovincial-trials.programme.approve', [$event, $proposal]) }}">@csrf<input name="reason" class="form-control my-2" required maxlength="500" placeholder="Approval reason"><button class="btn btn-success btn-sm">Approve promotion</button></form>@endif</div>
        @endforeach
      @endif
    </div></div>
  @else
    <div id="trial-teams" class="card mb-4"><div class="card-body"><h5>Teams</h5><p class="text-muted mb-0">No team draft yet. Resolve finishing positions and declarations, then generate teams.</p></div></div>
  @endif
  <div class="card mb-4"><div id="trial-trial-payments" class="card-body"><h5>Trials manual collection</h5>
    @forelse($orders as $order)<div class="border rounded p-3 mb-2"><p>Order #{{ $order->id }} — R {{ number_format($order->total_fee,2) }}</p>
    @if(!$order->payfast_handed_off_at && (float)$order->wallet_reserved===0.0 && !$order->withdrawn_at && !$order->pay_status && !$order->payfast_paid)<form method="POST" action="{{ route('backend.interprovincial-trials.orders.mark-paid', [$event, $order]) }}" class="border rounded p-2 mb-2">@csrf
      <label class="form-label">Received payment reference</label><input name="reference" class="form-control mb-2" minlength="3" maxlength="120" required><button class="btn btn-sm btn-success">Mark received payment as paid</button>
    </form>@else<p class="small text-muted">Manual collection is blocked by provider handoff, reserved funds or settled/withdrawn checkout.</p>@endif</div>@empty<p class="text-muted">No Trials checkouts awaiting manual collection.</p>@endforelse {{ $orders->withQueryString()->fragment('trial-trial-payments')->links('pagination::bootstrap-5') }}
  </div></div>
  <div class="card mb-4"><div id="trial-participation" class="card-body"><h5>Participation payments</h5>
    @forelse($participations as $participation)<div class="border rounded p-2 mb-2">{{ $participation->player?->full_name }} — {{ $participation->isPaid() ? 'Paid' : 'Awaiting payment' }}
      @if(!$participation->isPaid() && $programme?->payment_deadline?->isPast())<span class="badge bg-warning">Payment overdue: follow up</span>@endif
      @if(!$participation->isPaid() && $participation->order && !$participation->order->withdrawn_at && !$participation->order->payfast_handed_off_at && !$participation->order->payfast_paid && (float)$participation->order->wallet_reserved === 0.0 && $participation->slot?->player_id === $participation->player_id)
        <form method="POST" action="{{ route('backend.interprovincial-trials.participations.mark-paid', [$event, $participation]) }}">@csrf<input name="reference" class="form-control my-2" required minlength="3" maxlength="120" placeholder="Received payment reference"><button class="btn btn-success btn-sm">Record manual participation payment</button></form>
      @endif
      @if(!$participation->isPaid() && ($participation->order?->payfast_handed_off_at || (float)($participation->order?->wallet_reserved ?? 0)>0 || $participation->order?->withdrawn_at))<p class="small text-muted">Manual collection is blocked by provider handoff, reserved funds or withdrawal. Review checkout state first.</p>@endif
      <details class="mt-2"><summary>Withdrawal and refund options</summary>
      @if($participation->order?->pay_status && !in_array($participation->order?->refund_status, ['pending', 'completed']))
        <form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.participations.refund', [$event, $participation]) }}">@csrf<select name="method" class="form-select mb-2"><option value="bank">Manual refund paid outside the app</option>@if($participation->order?->payfast_paid && $participation->order?->payfast_pf_payment_id)<option value="payfast">Refund original PayFast payment</option>@endif</select><button class="btn btn-sm btn-outline-secondary">Withdraw and request participation refund</button></form>
      @endif
      @if($participation->order?->refund_status === 'pending' && $participation->order?->refund_method === 'bank')
        <form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.participations.refund-complete', [$event, $participation]) }}">@csrf<p>Approved refund: R {{ number_format($participation->order->refund_net, 2) }}</p><input name="reference" class="form-control mb-2" required minlength="3" maxlength="120" placeholder="External refund reference"><label class="d-block"><input type="checkbox" name="externally_paid" value="1" required> Refund already paid externally.</label><button class="btn btn-sm btn-outline-secondary">Record completed participation refund</button></form>
      @endif
      </details>
      </div>@empty<p class="text-muted">No participation checkouts created yet.</p>@endforelse {{ $participations->withQueryString()->fragment('trial-participation')->links('pagination::bootstrap-5') }}
    <h6 class="mt-4">Participation EFT evidence</h6>
    @forelse($participationProofs as $proof)<div class="border rounded p-2 mb-2"><p>{{ $proof->participation?->player?->full_name }} — R {{ number_format($proof->order?->total_amount ?? 0, 2) }}</p><a class="btn btn-sm btn-outline-secondary" href="{{ route('interprovincial-trials.participation.download', [$event, $proof]) }}">Download private proof</a>
      @if($proof->status==='pending' && !$proof->order?->pay_status && !$proof->order?->withdrawn_at && !$proof->order?->payfast_handed_off_at && (float)($proof->order?->wallet_reserved ?? 0)===0.0)
      <form method="POST" action="{{ route('backend.interprovincial-trials.participations.verify', [$event, $proof]) }}">@csrf<input name="reference" class="form-control my-2" required minlength="3" maxlength="120" placeholder="Bank receipt reference"><button class="btn btn-success btn-sm">Verify EFT payment received</button></form>
      <form method="POST" action="{{ route('backend.interprovincial-trials.participations.reject-proof', [$event,$proof]) }}">@csrf<input class="form-control mt-2" name="reason" required minlength="10" maxlength="1000" placeholder="Why this proof is rejected"><button class="btn btn-outline-danger btn-sm mt-2">Reject proof</button></form>
    @else<p class="small text-muted">Evidence remains available. Await the provider callback or review reserved, settled or withdrawn checkout state before taking action.</p>@endif
    </div>@empty<p class="text-muted">No participation EFT proofs awaiting review.</p>@endforelse {{ $participationProofs->withQueryString()->fragment('trial-participation')->links('pagination::bootstrap-5') }}
  </div></div>
  <details id="trial-withdrawals" class="card mb-4"><summary class="card-header">Withdrawals, ranking decisions and refund history</summary><div class="card-body"><h5>Withdrawn Trials players</h5>
    @forelse($withdrawn as $entry)<div class="border rounded p-2 mb-2"><p>{{ $entry->registration?->players?->first()?->full_name }} — {{ $entry->categoryEvent?->category?->name }}</p>
      <form method="POST" action="{{ route('backend.interprovincial-trials.programme.disposition', [$event, $entry]) }}">@csrf<select name="disposition" class="form-select"><option value="retain">Retain format finishing position</option><option value="last">Place last</option><option value="exclude">Exclude from rankings</option></select><input name="reason" class="form-control my-2" required maxlength="500" placeholder="Ranking decision reason"><button class="btn btn-sm btn-outline-primary">Record ranking decision</button></form>
      @if($entry->is_paid && !in_array($entry->refund_status, ['pending', 'completed']))<form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.programme.refund-request', [$event, $entry]) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Request manual refund using existing rules</button></form>@endif
      @if($entry->refund_status === 'pending' && $entry->refund_method === 'bank')<form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.programme.refund-complete', [$event, $entry]) }}">@csrf<p>Approved refund: R {{ number_format($entry->refund_net, 2) }}</p><input name="reference" class="form-control mb-2" required minlength="3" maxlength="120" placeholder="External refund reference"><label class="d-block"><input type="checkbox" name="externally_paid" value="1" required> This refund has already been paid externally.</label><button class="btn btn-sm btn-outline-secondary">Record completed external refund</button></form>@endif
    </div>@empty<p class="text-muted">No withdrawn Trials players.</p>@endforelse {{ $withdrawn->withQueryString()->fragment('trial-withdrawals')->links('pagination::bootstrap-5') }}
  </div></details>
  <div class="card"><div id="trial-eft-review" class="card-body"><h5>EFT proof review</h5>
    @forelse($proofs as $proof)
      <div class="border rounded p-3 mb-2"><p>Order #{{ $proof->order_id }} — {{ ucfirst($proof->status) }} — R {{ number_format($proof->order?->total_fee ?? 0, 2) }}</p>
        <a href="{{ route('interprovincial-trials.proof.download', $proof) }}" class="btn btn-outline-secondary mb-2">Download private proof</a>
        @if($proof->status === 'pending' && !$proof->order?->pay_status && !$proof->order?->withdrawn_at && !$proof->order?->payfast_handed_off_at && (float)($proof->order?->wallet_reserved ?? 0)===0.0)<form method="POST" action="{{ route('backend.interprovincial-trials.proof.verify', [$event, $proof]) }}">@csrf
          <label class="form-label" for="reference-{{ $proof->id }}">Bank receipt reference</label><input id="reference-{{ $proof->id }}" name="reference" class="form-control mb-2" required minlength="3" maxlength="120" pattern="[A-Za-z0-9][A-Za-z0-9._:/\-]{2,119}"><button class="btn btn-success">Verify payment received</button>
        </form><form method="POST" class="mt-2" action="{{ route('backend.interprovincial-trials.proof.reject', [$event, $proof]) }}">@csrf<input name="reason" class="form-control mb-2" required maxlength="500" placeholder="Reason for rejecting this proof"><button class="btn btn-sm btn-outline-danger">Reject proof</button></form>@else<p class="small text-muted">Evidence remains available. Await the provider callback or review the checkout state before verification.</p>@endif
      </div>
    @empty <p>No payment proofs uploaded.</p> @endforelse
    {{ $proofs->withQueryString()->fragment('trial-eft-review')->links('pagination::bootstrap-5') }}
  </div></div>
</div>
@endsection
