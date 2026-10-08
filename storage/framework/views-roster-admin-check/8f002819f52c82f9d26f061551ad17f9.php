<?php $__env->startSection('title', 'Masters invitations'); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .masters-player-list { overflow:visible; }
  .masters-player-row { border-top:1px solid #ebeaf0; padding:.55rem 0; }
  .masters-player-row .form-check-input { margin-top:.25rem; }
  .masters-entry-table { width:100%; border:1px solid #ebeaf0; border-radius:.35rem; overflow:visible; }
  .masters-entry-head, .masters-entry-row { display:grid; grid-template-columns:1.5rem minmax(6rem,1.15fr) minmax(7rem,1.35fr) minmax(5rem,.85fr) minmax(6.3rem,1fr) 6rem; align-items:center; gap:.35rem; padding:.5rem .45rem; }
  .masters-entry-head { background:#e4e4e9; color:#625f6d; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
  .masters-entry-row { border-top:1px solid #ebeaf0; font-size:.82rem; }
  .masters-entry-row:first-child { border-top:0; }
  .masters-entry-row a { word-break:break-word; }
  .masters-entry-row .contact-cell { min-width:0; }
  .masters-entry-row .contact-cell small { display:block; color:#8b8794; }
  .masters-entry-row .contact-cell .email-link { display:block; overflow-wrap:anywhere; }
  .masters-entry-row .action-cell form { display:inline-block; }
  .masters-entry-row .action-cell { white-space:nowrap; display:flex; align-items:center; gap:.15rem; }
  .masters-entry-row .action-cell .btn { padding:.25rem .4rem; }
  .masters-action-menu .dropdown-item { font-size:.82rem; padding:.45rem .75rem; }
  .masters-action-menu form { display:block !important; margin:0; }
  @media (max-width: 900px) { .masters-entry-head { display:none; } .masters-entry-row { grid-template-columns:2rem minmax(0,1fr) auto; } .masters-entry-row .email-cell, .masters-entry-row .cell-cell, .masters-entry-row .payment-cell { grid-column:2; } .masters-entry-row .action-cell { grid-column:3; grid-row:1; } }
  .masters-groups { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:1rem; }
  .masters-groups .card { margin-bottom:0 !important; }
  .masters-groups .card-body { padding:1rem 1.1rem; }
  .masters-groups h5 { font-size:1rem; margin-bottom:.35rem; }
  .masters-groups p { margin-bottom:.35rem; font-size:.85rem; }
  .masters-declined-summary { cursor:pointer; list-style:none; }
  .masters-declined-summary::-webkit-details-marker { display:none; }
  .masters-declined-panel { max-height:19rem; overflow-y:auto; padding-top:.35rem; }
  .masters-declined-panel .masters-player-row { padding:.45rem .55rem; font-size:.82rem; }
  .masters-declined-panel .masters-player-row strong { font-size:.84rem; }
  @media (max-width: 900px) { .masters-groups { grid-template-columns:1fr; } }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2"><div><h4>Masters invitation batch</h4><p class="text-muted"><?php echo e($batch->event->name ?? 'Event'); ?> · ranking run <?php echo e($batch->ranking_run_id); ?></p></div><div class="d-flex gap-2 flex-wrap"><a href="<?php echo e(route('admin.events.entries.new', $batch->event_id)); ?>" class="btn btn-primary"><i class="ti ti-users me-1"></i>Confirmed Entries</a><a href="<?php echo e(route('admin.events.overview', $batch->event_id)); ?>" class="btn btn-outline-primary">Back to Masters Dashboard</a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->series_id): ?><a href="<?php echo e(route('series.events', $batch->series_id)); ?>" class="btn btn-outline-secondary">Back to Series</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
  <div class="alert <?php echo e($readiness['status'] === 'blocked' ? 'alert-danger' : ($readiness['status'] === 'warning' ? 'alert-warning' : 'alert-success')); ?>">
    Readiness: <?php echo e(ucfirst($readiness['status'])); ?>

  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->status !== 'sent'): ?>
    <div class="alert alert-light border mb-3"><strong>What to do on this page:</strong> Set the deadlines players will see in their invitation, confirm the selected invitees below, then save these details before sending.</div>
    <div class="card mb-3"><div class="card-header"><h5 class="mb-1">Step 2: review and send invitations</h5><p class="text-muted small mb-0">Review the invitee names below, adjust the invitation wave if needed, then save all details before composing the email.</p></div><div class="card-body"><form method="POST" action="<?php echo e(route('backend.masters.details.update', $batch)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><div class="row g-3"><div class="col-md-4"><label class="form-label">Response deadline</label><input name="response_deadline" type="datetime-local" class="form-control" value="<?php echo e($batch->response_deadline?->format('Y-m-d\\TH:i')); ?>" required></div><div class="col-md-4"><label class="form-label">Payment deadline</label><input name="payment_deadline" type="datetime-local" class="form-control" value="<?php echo e($batch->payment_deadline?->format('Y-m-d\\TH:i')); ?>" required></div><div class="col-md-4"><label class="form-label">Replacement payment deadline</label><input name="replacement_payment_deadline" type="datetime-local" class="form-control" value="<?php echo e($batch->replacement_payment_deadline?->format('Y-m-d\\TH:i')); ?>" required></div></div><button class="btn btn-outline-primary mt-3">Save invitation details</button></form><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->response_deadline && $batch->payment_deadline && $batch->replacement_payment_deadline): ?><a href="<?php echo e(route('backend.masters.review', $batch)); ?>" class="btn btn-primary mt-3">Compose and review invitation email</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
  <?php else: ?>
    <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2"><span>Invitations have been sent. The checked players below are now active invitees in their Masters categories.</span><form method="POST" action="<?php echo e(route('backend.masters.public-list.toggle', $batch)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="published" value="<?php echo e($batch->public_list_published ? 0 : 1); ?>"><button class="btn btn-sm <?php echo e($batch->public_list_published ? 'btn-outline-warning' : 'btn-outline-success'); ?>"><?php echo e($batch->public_list_published ? 'Unpublish player list' : 'Publish player list'); ?></button></form></div>
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-1">Extend invitation deadlines</h5>
        <p class="text-muted small mb-0">Move one or more deadlines later without reopening or resending the invitation batch.</p>
      </div>
      <div class="card-body">
        <div class="alert alert-warning py-2 small">Deadlines can only be extended. The response deadline must not be after payment, and replacement payment must remain last. Existing invitation emails are not resent automatically.</div>
        <form method="POST" action="<?php echo e(route('backend.masters.deadlines.extend', $batch)); ?>" onsubmit="return confirm('Extend these live invitation deadlines? Existing invitation emails will not be resent.');">
          <?php echo csrf_field(); ?>
          <?php echo method_field('PATCH'); ?>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Response deadline</label><input name="response_deadline" type="datetime-local" class="form-control" value="<?php echo e(old('response_deadline', $batch->response_deadline?->format('Y-m-d\\TH:i'))); ?>" required></div>
            <div class="col-md-4"><label class="form-label">Payment deadline</label><input name="payment_deadline" type="datetime-local" class="form-control" value="<?php echo e(old('payment_deadline', $batch->payment_deadline?->format('Y-m-d\\TH:i'))); ?>" required></div>
            <div class="col-md-4"><label class="form-label">Replacement payment deadline</label><input name="replacement_payment_deadline" type="datetime-local" class="form-control" value="<?php echo e(old('replacement_payment_deadline', $batch->replacement_payment_deadline?->format('Y-m-d\\TH:i'))); ?>" required></div>
          </div>
          <button class="btn btn-outline-primary mt-3"><i class="ti ti-calendar-plus me-1"></i>Extend deadlines</button>
        </form>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->status !== 'sent' && !$batch->public_list_published): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center gap-2"><span>Publish the invited player names publicly before sending emails?</span><form method="POST" action="<?php echo e(route('backend.masters.publish-names', $batch)); ?>" onsubmit="return confirm('Publish the invitation list publicly without sending invitation emails?');"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-info"><i class="ti ti-world me-1"></i>Publish invitation list</button></form></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="masters-groups">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $readiness['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="card">
    <div class="card-body">
      <h5><?php echo e($group['label'] ?? 'Age group'); ?></h5>
      <p><?php echo e($group['candidate_count']); ?> candidates · <?php echo e($group['reserve_count']); ?> reserves · <?php echo e(ucfirst($group['status'])); ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $group['blocking']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="text-danger"><?php echo e($message); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $group['warnings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="text-warning"><?php echo e($message); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php ($groupPlayers = $batch->invitations->where('category_event_id', $group['category_event_id'])->sortBy('queue_position')); ?>
      <?php ($invitees = $groupPlayers->filter(fn ($invitation) => in_array($invitation->status, [\App\Models\MastersInvitation::INVITED, \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\MastersInvitation::PAID_CONFIRMED], true))); ?>
      <?php ($reserves = $groupPlayers->filter(fn ($invitation) => $invitation->status === \App\Models\MastersInvitation::RESERVE)); ?>
      <?php ($declined = $groupPlayers->filter(fn ($invitation) => in_array($invitation->status, [\App\Models\MastersInvitation::DECLINED, \App\Models\MastersInvitation::WITHDRAWN, \App\Models\MastersInvitation::ADMIN_REMOVED], true))); ?>
      <div class="d-flex justify-content-between align-items-center mt-3 mb-1"><div class="small fw-semibold">Invitees — visible to be sent</div><span class="small text-muted"><?php echo e($invitees->count()); ?> entries</span></div>
      <div class="masters-entry-table">
        <div class="masters-entry-head"><span>#</span><span>Player</span><span>Email</span><span>Cell</span><span>Status / payment</span><span>Action</span></div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invitees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($willInvite = $playerInvitation->status === \App\Models\MastersInvitation::INVITED); ?>
            <?php ($playerStatus = match ($playerInvitation->status) { \App\Models\MastersInvitation::PAID_CONFIRMED => 'Registered', \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT => 'Payment pending', default => $batch->status === 'sent' ? 'Queued' : 'Not sent' }); ?>
            <div class="masters-entry-row masters-player-row">
              <span class="text-muted"><?php echo e($loop->iteration); ?></span><div><strong><?php echo e($playerInvitation->player?->full_name ?? ('Player '.$playerInvitation->player_id)); ?></strong><small class="d-block text-muted">Rank <?php echo e($playerInvitation->ranking_position); ?></small></div>
              <?php ($contactEmail = $playerInvitation->player?->email ?: $playerInvitation->player?->user?->email ?: $playerInvitation->player?->users?->first()?->email); ?>
              <div class="contact-cell email-cell"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contactEmail): ?><a class="email-link" href="mailto:<?php echo e($contactEmail); ?>"><?php echo e($contactEmail); ?></a><?php else: ?><span class="text-muted">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><div class="contact-cell cell-cell"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->player?->cellNr): ?><a href="tel:<?php echo e($playerInvitation->player->cellNr); ?>"><?php echo e($playerInvitation->player->cellNr); ?></a><?php else: ?><span class="text-muted">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><div class="payment-cell"><span class="badge <?php echo e($playerInvitation->status === \App\Models\MastersInvitation::PAID_CONFIRMED ? 'bg-label-success' : ($playerInvitation->status === \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT ? 'bg-label-warning' : 'bg-label-primary')); ?>"><?php echo e($playerStatus); ?></span></div><div class="action-cell"><form class="js-invitation-wave-form" method="POST" action="<?php echo e(route('backend.masters.invitation.update', $playerInvitation)); ?>" data-player-row data-invited="<?php echo e($willInvite ? 1 : 0); ?>" data-invitation-status="<?php echo e($playerInvitation->status); ?>" data-mark-paid-url="<?php echo e(route('backend.masters.invitation.mark-paid', $playerInvitation)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><input type="hidden" name="status" value="<?php echo e($willInvite ? 'reserve' : 'invited'); ?>"><button class="btn btn-sm <?php echo e($willInvite ? 'btn-primary' : 'btn-outline-secondary'); ?>" type="submit" title="<?php echo e($willInvite ? 'Move to reserve' : 'Add to invitation wave'); ?>"><?php echo e($willInvite ? '✓' : '○'); ?></button></form></div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="small text-muted p-3">No players currently selected for invitation.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reserves->isNotEmpty()): ?><details class="mt-2"><summary class="small fw-semibold">Show <?php echo e($reserves->count()); ?> reserve players</summary><div class="masters-player-list mt-2">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $reserves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php ($willInvite = false); ?>
            <?php ($contactEmail = $playerInvitation->player?->email ?: $playerInvitation->player?->user?->email ?: $playerInvitation->player?->users?->first()?->email); ?>
            <div class="masters-player-row d-flex align-items-start gap-2"><form class="js-invitation-wave-form" method="POST" action="<?php echo e(route('backend.masters.invitation.update', $playerInvitation)); ?>" data-player-row data-invited="0"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><input type="hidden" name="status" value="invited"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Add to invitation wave">○</button></form><div class="flex-grow-1"><strong><?php echo e($playerInvitation->player?->full_name ?? ('Player '.$playerInvitation->player_id)); ?></strong><div class="small text-muted">Rank <?php echo e($playerInvitation->ranking_position); ?> · Reserve — invite if needed</div><div class="small mt-1 d-flex flex-wrap gap-2"><span class="text-muted">Contact:</span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contactEmail): ?><a href="mailto:<?php echo e($contactEmail); ?>"><?php echo e($contactEmail); ?></a><?php else: ?><span class="text-muted">No email</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->player?->cellNr): ?><a href="tel:<?php echo e($playerInvitation->player->cellNr); ?>"><?php echo e($playerInvitation->player->cellNr); ?></a><?php else: ?><span class="text-muted">No telephone</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div><span class="badge bg-label-secondary">Reserve</span></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($declined->isNotEmpty()): ?><details class="mt-3 masters-declined"><summary class="masters-declined-summary small fw-semibold text-danger">Declined / unavailable · <?php echo e($declined->count()); ?> player<?php echo e($declined->count() === 1 ? '' : 's'); ?></summary><div class="small text-muted mb-1">The next reserve can be invited when automatic replacement is enabled.</div><div class="masters-player-list masters-declined-panel">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $declined; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php ($contactEmail = $playerInvitation->player?->email ?: $playerInvitation->player?->user?->email ?: $playerInvitation->player?->users?->first()?->email); ?>
            <div class="masters-player-row d-flex align-items-start gap-2 bg-light"><div class="flex-grow-1"><strong><?php echo e($playerInvitation->player?->full_name ?? ('Player '.$playerInvitation->player_id)); ?></strong><div class="small text-muted">Rank <?php echo e($playerInvitation->ranking_position); ?> · <?php echo e($playerInvitation->status === \App\Models\MastersInvitation::WITHDRAWN ? 'Withdrawn after registration' : ($playerInvitation->status === \App\Models\MastersInvitation::ADMIN_REMOVED ? 'Removed by admin' : 'Declined / unavailable')); ?></div><div class="small mt-1 d-flex flex-wrap gap-2"><span class="text-muted">Contact:</span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($contactEmail): ?><a href="mailto:<?php echo e($contactEmail); ?>"><?php echo e($contactEmail); ?></a><?php else: ?><span class="text-muted">No email</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->player?->cellNr): ?><a href="tel:<?php echo e($playerInvitation->player->cellNr); ?>"><?php echo e($playerInvitation->player->cellNr); ?></a><?php else: ?><span class="text-muted">No telephone</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->status === \App\Models\MastersInvitation::DECLINED): ?><div class="small text-danger mt-2"><strong>Player decline</strong> · <?php echo e($playerInvitation->declined_at?->format('d M Y H:i') ?? 'time unavailable'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->declinedBy): ?> · <?php echo e($playerInvitation->declinedBy->name ?? $playerInvitation->declinedBy->email); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> · <?php echo e(str_replace('_', ' ', ucfirst($playerInvitation->decline_method ?? 'recorded'))); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->decline_confirmed_at): ?> · confirmed <?php echo e($playerInvitation->decline_confirmed_at->format('d M Y H:i')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->decline_reason): ?> · “<?php echo e($playerInvitation->decline_reason); ?>”<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php elseif($playerInvitation->status === \App\Models\MastersInvitation::ADMIN_REMOVED): ?><div class="small text-warning mt-2"><strong>Admin removal</strong> · <?php echo e($playerInvitation->admin_removed_at?->format('d M Y H:i') ?? $playerInvitation->declined_at?->format('d M Y H:i') ?? 'time unavailable'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerInvitation->adminRemovedBy): ?> · <?php echo e($playerInvitation->adminRemovedBy->name ?? $playerInvitation->adminRemovedBy->email); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> · admin dashboard</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><span class="badge bg-label-danger"><?php echo e($playerInvitation->status === \App\Models\MastersInvitation::WITHDRAWN ? 'Withdrawn' : ($playerInvitation->status === \App\Models\MastersInvitation::ADMIN_REMOVED ? 'Admin removed' : 'Player declined')); ?></span></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->status !== 'sent' || $declined->isNotEmpty()): ?><div class="mt-2 d-flex flex-wrap gap-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $declined; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $playerInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->status !== 'sent' || in_array($playerInvitation->status, [\App\Models\MastersInvitation::DECLINED, \App\Models\MastersInvitation::ADMIN_REMOVED], true)): ?><form method="POST" action="<?php echo e(route('backend.masters.invitation.restore', $playerInvitation)); ?>" onsubmit="return confirm('Restore this player to the reserve list?');"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-success" type="submit">Restore <?php echo e($playerInvitation->player?->full_name ?? 'player'); ?> to reserve</button></form><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Ranking lists in this batch</h5><span class="text-muted small">Remove only before payment starts</span></div><div class="card-body p-0">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $batch->invitations->groupBy('ranking_list_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rankingListId => $invitations): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php ($category = $invitations->first()->categoryEvent?->category?->name ?? 'Ranking list '.$rankingListId); ?>
      <div class="d-flex justify-content-between align-items-center border-bottom p-3"><div><strong><?php echo e($category); ?></strong><div class="small text-muted"><?php echo e($invitations->count()); ?> invitation records</div></div><form method="POST" action="<?php echo e(route('backend.masters.remove-ranking-list', [$batch, $rankingListId])); ?>" onsubmit="return confirm('Remove this ranking list and its invitations from the batch so it can be generated again? This cannot be undone.');"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-sm btn-outline-danger">Remove &amp; restart</button></form></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="p-3 text-muted">No ranking lists remain in this batch.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
  <div class="d-flex justify-content-center gap-2 py-3"><a href="<?php echo e(route('admin.events.overview', $batch->event_id)); ?>" class="btn btn-primary">Back to Masters Dashboard</a><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch->series_id): ?><a href="<?php echo e(route('series.events', $batch->series_id)); ?>" class="btn btn-outline-secondary">Back to Series</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<div class="modal fade" id="invitationPreviewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Invitation email preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="invitationPreviewBody"><div class="text-center text-muted py-4">Loading preview…</div></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const deadlineHints = {
    response_deadline: 'The last date and time for the selected player to register or report that they are unavailable.',
    payment_deadline: 'The last date and time for a player who started registration to complete payment and secure their place.',
    replacement_payment_deadline: 'The last date and time a replacement player may complete payment after being invited.'
  };
  Object.entries(deadlineHints).forEach(function ([name, hint]) {
    const input = document.querySelector('[name="' + name + '"]');
    if (!input) return;
    const wrapper = input.closest('.col-md-4');
    const label = wrapper?.querySelector('label');
    if (label) { label.setAttribute('title', hint); label.setAttribute('data-bs-toggle', 'tooltip'); label.insertAdjacentHTML('beforeend', ' <span class="text-muted" aria-hidden="true">ⓘ</span>'); }
    input.insertAdjacentHTML('afterend', '<div class="form-text">' + hint + '</div>');
  });
  if (window.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
  const previewModal = document.getElementById('invitationPreviewModal');
  document.querySelectorAll('.masters-entry-row .action-cell').forEach(function (cell) {
    const form = cell.querySelector('.js-invitation-wave-form');
    if (!form) return;
    const menuWrap = document.createElement('div'); menuWrap.className = 'dropdown masters-action-menu';
    const menuToggle = document.createElement('button'); menuToggle.type = 'button'; menuToggle.className = 'btn btn-sm btn-outline-secondary dropdown-toggle'; menuToggle.textContent = '☰'; menuToggle.title = 'Player actions'; menuToggle.setAttribute('data-bs-toggle', 'dropdown'); menuToggle.setAttribute('aria-label', 'Player actions');
    const menu = document.createElement('div'); menu.className = 'dropdown-menu dropdown-menu-end';
    menuWrap.append(menuToggle, menu);
    const remove = document.createElement('button');
    remove.type = 'button'; remove.className = 'dropdown-item text-danger'; remove.textContent = 'Remove by admin'; remove.title = 'Remove this player from the Masters list';
    remove.addEventListener('click', async function () {
      if (!confirm('Remove this player from the Masters invitation list?')) return;
      remove.disabled = true;
      try {
        const response = await fetch(form.action + '/remove', {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
        if (!response.ok) { const data = await response.json(); throw new Error(data.message || 'Could not remove the player.'); }
        AppFeedback.afterReload('Player removed from the Masters invitation list.');
        window.location.reload();
      } catch (error) { AppFeedback.fromError(error, 'Could not remove the player.'); remove.disabled = false; }
    });
    menu.appendChild(remove);
    if (form.dataset.invitationStatus === 'accepted_pending_payment') {
      const markPaid = document.createElement('button');
      markPaid.type = 'button'; markPaid.className = 'dropdown-item text-success'; markPaid.textContent = 'Mark paid privately (not reconciled)'; markPaid.title = 'Record a private collection and complete this registration';
      markPaid.addEventListener('click', async function () {
        if (!confirm('Mark this player as paid by admin? This will cancel the pending online checkout and record a private collection that is not financially reconciled.')) return;
        markPaid.disabled = true;
        try {
          const response = await fetch(form.dataset.markPaidUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
          const data = await response.json();
          if (!response.ok) throw new Error(data.message || 'Could not mark the player as paid.');
          AppFeedback.afterReload(data.message || 'Player marked as paid by admin.');
          window.location.reload();
        } catch (error) { AppFeedback.fromError(error, 'Could not mark the player as paid.'); markPaid.disabled = false; }
      });
      menu.appendChild(markPaid);
    }
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'dropdown-item'; button.textContent = 'Preview invitation email'; button.title = 'Preview invitation email'; button.setAttribute('aria-label', 'Preview invitation email');
    button.addEventListener('click', async function () {
      const body = document.getElementById('invitationPreviewBody');
      body.innerHTML = '<div class="text-center text-muted py-4">Loading preview…</div>';
      bootstrap.Modal.getOrCreateInstance(previewModal).show();
      try { const response = await fetch(form.action + '/preview', {headers: {'Accept': 'text/html'}}); if (!response.ok) throw new Error('Could not load the preview.'); body.innerHTML = await response.text(); }
      catch (error) { body.innerHTML = '<div class="alert alert-danger mb-0">' + error.message + '</div>'; }
    });
    menu.appendChild(button);
    menu.appendChild(form);
    const waveButton = form.querySelector('button');
    waveButton.className = 'dropdown-item';
    waveButton.textContent = form.dataset.invited === '1' ? 'Move player to reserve' : 'Add player to invitation wave';
    cell.appendChild(menuWrap);
  });
  document.querySelectorAll('.masters-player-list .js-invitation-wave-form').forEach(function (form) {
    const remove = document.createElement('button');
    remove.type = 'button'; remove.className = 'btn btn-sm btn-outline-danger ms-1'; remove.textContent = '×'; remove.title = 'Remove reserve player by admin';
    remove.addEventListener('click', async function () {
      if (!confirm('Remove this reserve player from the Masters list?')) return;
      remove.disabled = true;
      try {
        const response = await fetch(form.action + '/remove', {method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}});
        if (!response.ok) { const data = await response.json(); throw new Error(data.message || 'Could not remove the player.'); }
        AppFeedback.afterReload('Reserve player removed from the Masters invitation list.');
        window.location.reload();
      } catch (error) { AppFeedback.fromError(error, 'Could not remove the player.'); remove.disabled = false; }
    });
    form.parentElement.appendChild(remove);
  });
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  document.querySelectorAll('.js-invitation-wave-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('button');
      const statusInput = form.querySelector('[name="status"]');
      const row = form.closest('.masters-player-row');
      const targetStatus = statusInput.value;
      button.disabled = true;
      try {
        const response = await fetch(form.action, {method: 'PATCH', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify({status: targetStatus})});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not update the invitation wave.');
        const invited = data.status === 'invited';
        form.dataset.invited = invited ? '1' : '0';
        statusInput.value = invited ? 'reserve' : 'invited';
        button.textContent = invited ? 'Move player to reserve' : 'Add player to invitation wave';
        button.title = invited ? 'Move to reserve' : 'Add to invitation wave';
        button.classList.toggle('btn-primary', invited);
        button.classList.toggle('btn-outline-secondary', !invited);
        const description = row.querySelector('.small.text-muted');
        if (description && description.classList.contains('player-status')) {
          description.textContent = invited ? 'Email not yet sent' : 'Reserve — invite if needed';
        }
        const badge = row.querySelector('.badge');
        badge.textContent = invited ? 'Invited' : 'Reserve';
        badge.classList.toggle('bg-label-primary', invited);
        badge.classList.toggle('bg-label-secondary', !invited);
        // The invitee and reserve rows have different markup and containers.
        // Reload the rendered batch so the player is immediately shown in the
        // correct stage, with refreshed counts and queue ordering.
        AppFeedback.afterReload(data.message || 'Invitation wave updated.');
        window.location.reload();
      } catch (error) { AppFeedback.fromError(error, 'Could not update the invitation wave.'); }
      finally { button.disabled = false; }
    });
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\masters\show.blade.php ENDPATH**/ ?>