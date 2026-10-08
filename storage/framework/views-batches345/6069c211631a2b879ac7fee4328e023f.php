
<?php $__env->startSection('title', 'Interprovincial Trials invitation'); ?>
<?php $__env->startSection('content'); ?>
<?php ($registrationOpen = $invitation->event->published && $invitation->event->hasOpenRegistrationLifecycle() && (int) $invitation->event->signUp === 1 && (!$invitation->event->registrationClosesAt() || now()->lte($invitation->event->registrationClosesAt()->endOfDay()))); ?>
<div class="card"><div class="card-body">
<h4><?php echo e($invitation->event->name); ?></h4><p><?php echo e($invitation->player->name); ?> <?php echo e($invitation->player->surname); ?> has been invited for <?php echo e($invitation->categoryEvent->category->name); ?>.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation->status === \App\Models\InterprovincialTrialInvitation::PAID_CONFIRMED): ?><div class="alert alert-success">Registration confirmed.</div>
<?php elseif($invitation->status === \App\Models\InterprovincialTrialInvitation::WITHDRAWN): ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?><div class="alert alert-secondary">Not registered.</div><form class="d-inline-block" method="POST" action="<?php echo e(route('interprovincial-trials.nominations.register', [$invitation->event, $invitation->categoryEvent, $invitation->nomination])); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Register <?php echo e($invitation->player->name); ?></button></form>
  <?php else: ?><div class="alert alert-info">Registration closed.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php elseif($invitation->status === \App\Models\InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT): ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?><form class="d-inline-block" method="POST" action="<?php echo e(route('interprovincial-trials.invitations.register', $invitation)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Resume registration</button></form>
  <?php else: ?><div class="alert alert-info">Registration closed.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php elseif(in_array($invitation->status, ['queued', 'sent'], true) && $registrationOpen): ?>
  <form class="d-inline-block" method="POST" action="<?php echo e(route('interprovincial-trials.invitations.register', $invitation)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Register <?php echo e($invitation->player->name); ?></button></form>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canDecline): ?><form class="d-inline-block" method="POST" action="<?php echo e(route('interprovincial-trials.invitations.decline', $invitation)); ?>"><?php echo csrf_field(); ?><button class="btn btn-outline-danger" type="submit">Decline</button></form><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php elseif(!$registrationOpen): ?><div class="alert alert-info">Registration closed.</div>
<?php else: ?><div class="alert alert-info">This invitation is no longer available.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\interprovincial-trials\invitations\show.blade.php ENDPATH**/ ?>