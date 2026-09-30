@extends('layouts.backend')

@section('title', 'Review Masters invitations')

@section('page-style')
<style>
  .masters-review .category-card { border:1px solid #ebeaf0; border-radius:.6rem; margin-bottom:1rem; }
  .masters-review .category-card summary { cursor:pointer; list-style:none; padding: .85rem 1rem; }
  .masters-review .category-card summary::-webkit-details-marker { display:none; }
  .masters-review .category-card summary::before { content:'+'; display:inline-block; width:1.4rem; color:#7651e8; font-weight:700; }
  .masters-review .category-card[open] summary::before { content:'−'; }
  .masters-review .category-content { border-top:1px solid #ebeaf0; padding:0 1rem 1rem; }
  .masters-review .category-card h5 { font-size:1rem; }
  .masters-review .category-card h5 { font-size:.88rem; }
  .masters-review .category-content { padding-left:.65rem; padding-right:.65rem; }
  .masters-review .player-row { border-top:1px solid #ebeaf0; padding:.2rem 0; min-height:2rem; font-size:.76rem; }
  .masters-review .player-row strong { font-size:.78rem; font-weight:600; }
  .masters-review .player-row .btn { width:1.45rem; height:1.35rem; padding:.05rem; line-height:1; font-size:.7rem; }
  .masters-review .player-row .small { font-size:.72rem; }
  .masters-review .player-list { overflow:visible; }
  .masters-review .category-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; }
  @media (max-width: 900px) { .masters-review .category-grid { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y masters-review">
  <div class="d-flex justify-content-between align-items-start mb-3"><div><h4 class="mb-1">Final invitation review</h4><p class="text-muted mb-0">{{ $batch->event?->name }} · confirm the checked invitees before sending.</p></div><div class="d-flex gap-2"><a href="{{ route('admin.events.overview', $batch->event_id) }}" class="btn btn-outline-primary">Back to Masters Dashboard</a><a href="{{ route('backend.masters.show', $batch) }}" class="btn btn-outline-secondary">Back to batch details</a></div></div>
  <div class="alert alert-info">{{ $batch->invitations->where('status', 'invited')->count() }} invitations selected · {{ $batch->invitations->where('status', 'reserve')->count() }} reserves. Checked players will receive invitations; reserves will be invited only if needed.</div>
  <div class="row">
    <div class="col-12 category-grid">
      @foreach($batch->invitations->groupBy('category_event_id') as $categoryId => $invitations)
        @php($categoryRemoved = $invitations->where('status', \App\Models\MastersInvitation::ADMIN_REMOVED))
        <details class="category-card"><summary><div class="d-flex justify-content-between align-items-center gap-2"><h5 class="mb-0">{{ $invitations->first()->categoryEvent?->category?->name ?? 'Masters category' }}</h5><span class="small text-muted">{{ $invitations->where('status','invited')->count() }} invitees · {{ $invitations->where('status','reserve')->count() }} reserves</span></div></summary><div class="category-content"><div class="player-list">
          @foreach($invitations->whereIn('status', [\App\Models\MastersInvitation::INVITED, \App\Models\MastersInvitation::RESERVE])->sortBy('queue_position') as $invitation)
            @php($invited = $invitation->status === \App\Models\MastersInvitation::INVITED)
            <div class="player-row d-flex align-items-center gap-2"><form class="js-invitation-wave-form" method="POST" action="{{ route('backend.masters.invitation.update', $invitation) }}" data-invited="{{ $invited ? 1 : 0 }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $invited ? 'reserve' : 'invited' }}"><button class="btn btn-sm {{ $invited ? 'btn-primary' : 'btn-outline-secondary' }}" type="submit">{{ $invited ? '✓' : '○' }}</button></form><div class="flex-grow-1"><strong>{{ $invitation->player?->full_name ?? ('Player '.$invitation->player_id) }}</strong><div class="small text-muted">Rank {{ $invitation->ranking_position }} · <span class="player-status">{{ $invited ? 'Email not yet sent' : 'Reserve — invite if needed' }}</span></div></div><span class="player-badge badge {{ $invited ? 'bg-label-primary' : 'bg-label-secondary' }}">{{ $invited ? 'Invited' : 'Reserve' }}</span></div>
          @endforeach
          @if($categoryRemoved->isNotEmpty())<div class="admin-removed-list mt-2 pt-2"><div class="small fw-semibold text-danger mb-1">Removed by admin</div>@foreach($categoryRemoved as $removed)<div class="player-row d-flex align-items-center justify-content-between text-danger"><span><strong>{{ $removed->player?->full_name ?? ('Player '.$removed->player_id) }}</strong><span class="small d-block">Rank {{ $removed->ranking_position }} · Excluded from invitations</span></span><span class="badge bg-danger">× Removed by admin</span></div>@endforeach</div>@endif
        </div></div></details>
      @endforeach
    </div>
  </div>
  <div class="card mt-3"><div class="card-body d-flex justify-content-between align-items-center"><div><strong>{{ $batch->status === 'sent' ? 'Invitations sent' : 'Ready to compose?' }}</strong><div class="small text-muted">Response deadline: {{ $batch->response_deadline->format('d M Y H:i') }} · Payment deadline: {{ $batch->payment_deadline->format('d M Y H:i') }}</div></div>@if($batch->status !== 'sent')<button class="btn btn-primary" id="masters-compose">Compose and review invitation email</button>@else<span class="badge bg-label-success">Sent</span>@endif</div></div>
</div>

<div class="modal fade" id="masters-mail-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Compose and review initial Masters invitations</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="{{ route('backend.masters.send-invitations', $batch) }}" id="masters-mail-form">@csrf<div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">From address</label><input type="email" name="from_address" class="form-control" required></div><div class="col-md-6"><label class="form-label">From name</label><input name="from_name" class="form-control" maxlength="100" required></div><div class="col-12"><label class="form-label">Reply-to address</label><input type="email" name="reply_to" class="form-control" required><div class="form-text" id="masters-sender-warning"></div></div><div class="col-12"><label class="form-label">Subject</label><input name="subject" class="form-control" maxlength="150" required></div><div class="col-12"><label class="form-label">Message</label><textarea name="body" class="form-control" rows="5" maxlength="5000" required></textarea></div></div><hr><div class="row g-3"><div class="col-lg-6"><strong>Exact recipients</strong><div class="list-group mt-2" id="masters-recipients"></div><div class="mt-3 d-none" id="masters-blockers-wrap"><strong class="text-warning">Blocked recipients</strong><div class="list-group mt-2" id="masters-blockers"></div></div></div><div class="col-lg-6"><strong>Rendered email</strong><div class="border rounded p-3 mt-2 bg-light d-none" id="masters-rendered"></div></div></div><input type="hidden" name="request_token"><input type="hidden" name="recipient_hash"><input type="hidden" name="composition_hash"><input type="hidden" name="review_proof"><input type="hidden" name="review_expires_at"></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-outline-primary" id="masters-review-email">Review rendered email</button><button type="submit" class="btn btn-primary d-none" id="masters-send-email">Send reviewed emails</button></div></form></div></div></div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.masters-review .category-card').forEach(details => { details.open = true; });
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  document.querySelectorAll('.js-invitation-wave-form').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault(); const button = form.querySelector('button'); const target = form.querySelector('[name="status"]').value; button.disabled = true;
    try { const response = await fetch(form.action, {method:'PATCH', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json'}, body:JSON.stringify({status:target})}); const data = await response.json(); if(!response.ok) throw new Error(data.message || 'Could not update invitee.'); const invited=data.status==='invited'; form.querySelector('[name="status"]').value=invited?'reserve':'invited'; button.textContent=invited?'✓':'○'; button.classList.toggle('btn-primary',invited); button.classList.toggle('btn-outline-secondary',!invited); const row=form.closest('.player-row'); row.querySelector('.player-status').textContent=invited?'Email not yet sent':'Reserve — invite if needed'; const badge=row.querySelector('.player-badge'); badge.textContent=invited?'Invited':'Reserve'; badge.classList.toggle('bg-label-primary',invited); badge.classList.toggle('bg-label-secondary',!invited); AppFeedback.success(data.message || 'Invitation wave updated.'); } catch(error) { AppFeedback.fromError(error, 'Could not update invitee.'); } finally { button.disabled=false; }
  }));
  const mailForm=document.getElementById('masters-mail-form'); const modalElement=document.getElementById('masters-mail-modal'); const modal=modalElement?bootstrap.Modal.getOrCreateInstance(modalElement):null; const escape=value=>$('<div>').text(value??'').html();
  async function preview(includeComposition=false){ const payload=includeComposition?Object.fromEntries(new FormData(mailForm).entries()):{}; const response=await fetch(@json(route('backend.masters.send-invitations.preview',$batch)),{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify(payload)}); const data=await response.json(); if(!response.ok) throw new Error(data.message||Object.values(data.errors||{}).flat()[0]||'Preview could not be loaded.'); return data; }
  document.getElementById('masters-compose')?.addEventListener('click',async()=>{try{const data=await preview(); ['subject','body','from_address','from_name','reply_to'].forEach(name=>mailForm.elements[name].value=data[name]); document.getElementById('masters-sender-warning').textContent=data.sender_warning; document.getElementById('masters-recipients').innerHTML=data.recipients.map(row=>`<div class="list-group-item"><strong>${escape(row.name)}</strong><div class="small text-muted">${escape(row.email)} · ${escape(row.category||'')}</div></div>`).join('')||'<div class="text-muted">No eligible recipients.</div>'; document.getElementById('masters-blockers').innerHTML=data.blockers.map(row=>`<div class="list-group-item text-warning">${escape(row.name)} — ${escape(row.reason)}</div>`).join(''); document.getElementById('masters-blockers-wrap').classList.toggle('d-none',!data.blockers.length); modal.show();}catch(error){AppFeedback.fromError(error,'Preview could not be loaded.');}});
  document.getElementById('masters-review-email')?.addEventListener('click',async()=>{try{const data=await preview(true); mailForm.elements.request_token.value=data.request_token; mailForm.elements.recipient_hash.value=data.recipient_hash; mailForm.elements.composition_hash.value=data.composition_hash; mailForm.elements.review_proof.value=data.review_proof; mailForm.elements.review_expires_at.value=data.review_expires_at; document.getElementById('masters-recipients').innerHTML=data.recipients.map(row=>`<div class="list-group-item"><strong>${escape(row.name)}</strong><div class="small text-muted">${escape(row.email)} · ${escape(row.category||'')}</div></div>`).join('')||'<div class="text-muted">No eligible recipients.</div>'; document.getElementById('masters-blockers').innerHTML=data.blockers.map(row=>`<div class="list-group-item text-warning">${escape(row.name)} — ${escape(row.reason)}</div>`).join(''); document.getElementById('masters-blockers-wrap').classList.toggle('d-none',!data.blockers.length); document.getElementById('masters-rendered').innerHTML=data.rendered_body; document.getElementById('masters-rendered').classList.remove('d-none'); document.getElementById('masters-send-email').classList.remove('d-none');}catch(error){AppFeedback.fromError(error,'Email could not be reviewed.');}});
  mailForm?.querySelectorAll('input:not([type=hidden]),textarea').forEach(input=>input.addEventListener('input',()=>document.getElementById('masters-send-email').classList.add('d-none')));
});
</script>
@endsection
