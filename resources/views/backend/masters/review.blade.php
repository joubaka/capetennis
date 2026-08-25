@extends('layouts/contentNavbarLayout')

@section('title', 'Review Masters invitations')

@section('page-style')
<style>
  .masters-review .category-card { border:1px solid #ebeaf0; border-radius:.6rem; margin-bottom:1rem; }
  .masters-review .category-card h5 { font-size:1rem; }
  .masters-review .player-row { border-top:1px solid #ebeaf0; padding:.55rem 0; }
  .masters-review .player-list { overflow:visible; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y masters-review">
  <div class="d-flex justify-content-between align-items-start mb-3"><div><h4 class="mb-1">Final invitation review</h4><p class="text-muted mb-0">{{ $batch->event?->name }} · confirm the checked invitees before sending.</p></div><div class="d-flex gap-2"><a href="{{ route('admin.events.overview', $batch->event_id) }}" class="btn btn-outline-primary">Back to Masters Dashboard</a><a href="{{ route('backend.masters.show', $batch) }}" class="btn btn-outline-secondary">Back to batch details</a></div></div>
  <div class="alert alert-info">{{ $batch->invitations->where('status', 'invited')->count() }} invitations selected · {{ $batch->invitations->where('status', 'reserve')->count() }} reserves. Checked players will receive invitations; reserves will be invited only if needed.</div>
  <div class="row">
    <div class="col-xl-9">
      @foreach($batch->invitations->groupBy('category_event_id') as $categoryId => $invitations)
        <div class="category-card p-3"><div class="d-flex justify-content-between align-items-center mb-2"><h5 class="mb-0">{{ $invitations->first()->categoryEvent?->category?->name ?? 'Masters category' }}</h5><span class="small text-muted">{{ $invitations->where('status','invited')->count() }} invitees · {{ $invitations->where('status','reserve')->count() }} reserves</span></div><div class="player-list">
          @foreach($invitations->sortBy('queue_position') as $invitation)
            @php($invited = $invitation->status === \App\Models\MastersInvitation::INVITED)
            <div class="player-row d-flex align-items-center gap-2"><form class="js-invitation-wave-form" method="POST" action="{{ route('backend.masters.invitation.update', $invitation) }}" data-invited="{{ $invited ? 1 : 0 }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $invited ? 'reserve' : 'invited' }}"><button class="btn btn-sm {{ $invited ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit">{{ $invited ? '✓' : '○' }}</button></form><div class="flex-grow-1"><strong>{{ $invitation->player?->full_name ?? ('Player '.$invitation->player_id) }}</strong><div class="small text-muted">Rank {{ $invitation->ranking_position }} · <span class="player-status">{{ $invited ? 'Invitation will be sent' : 'Reserve — invite if needed' }}</span></div></div><span class="player-badge badge {{ $invited ? 'bg-label-primary' : 'bg-label-secondary' }}">{{ $invited ? 'Invited' : 'Reserve' }}</span></div>
          @endforeach
        </div></div>
      @endforeach
    </div>
  </div>
  <div class="card mt-3"><div class="card-body d-flex justify-content-between align-items-center"><div><strong>{{ $batch->status === 'sent' ? 'Invitations sent' : 'Ready to send?' }}</strong><div class="small text-muted">Response deadline: {{ $batch->response_deadline->format('d M Y H:i') }} · Payment deadline: {{ $batch->payment_deadline->format('d M Y H:i') }}</div></div>@if($batch->status !== 'sent')<form method="POST" action="{{ route('backend.masters.send-invitations', $batch) }}" onsubmit="return confirm('Send invitations to all checked invitees now?');">@csrf<button class="btn btn-primary">Send invitations to all invitees</button></form>@else<span class="badge bg-label-success">Sent</span>@endif</div></div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  document.querySelectorAll('.js-invitation-wave-form').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault(); const button = form.querySelector('button'); const target = form.querySelector('[name="status"]').value; button.disabled = true;
    try { const response = await fetch(form.action, {method:'PATCH', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json'}, body:JSON.stringify({status:target})}); const data = await response.json(); if(!response.ok) throw new Error(data.message || 'Could not update invitee.'); const invited=data.status==='invited'; form.querySelector('[name="status"]').value=invited?'reserve':'invited'; button.textContent=invited?'✓':'○'; button.classList.toggle('btn-primary',invited); button.classList.toggle('btn-outline-secondary',!invited); const row=form.closest('.player-row'); row.querySelector('.player-status').textContent=invited?'Invitation will be sent':'Reserve — invite if needed'; const badge=row.querySelector('.player-badge'); badge.textContent=invited?'Invited':'Reserve'; badge.classList.toggle('bg-label-primary',invited); badge.classList.toggle('bg-label-secondary',!invited); } catch(error) { alert(error.message); } finally { button.disabled=false; }
  }));
});
</script>
@endsection
