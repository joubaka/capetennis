<?php $__env->startSection('title', 'CTR event planner'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceActive' => 'draws', 'eventWorkspaceSubtitle' => 'CTR event planner'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/ctr-planner.css')); ?>?v=<?php echo e(filemtime(public_path('css/ctr-planner.css'))); ?>">
<div id="ctr-planner" class="mx-auto" style="max-width:1100px">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
    <div><h1 class="h3 mb-1">Plan your CTR competitions</h1><p class="text-muted mb-0">Nearby ratings, small groups, one winner and one sportsmanship prize per competition.</p></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($certified): ?><span class="badge bg-success">CTR Certified</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$certified): ?>
    <div class="card card-body"><h2 class="h5">CTR certification required</h2><p>This planner is available for CTR Certified individual events.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user')): ?>
      <form method="POST" action="<?php echo e(route('backend.ctr-planner.certify', $event)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="certified" value="1"><button class="btn btn-primary" type="submit">Mark this event CTR Certified</button><p class="small text-muted mt-2 mb-0">This records your certification of this event. Publication and result eligibility remain separate.</p></form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php else: ?>
    <section class="card card-body mb-3" aria-labelledby="ctr-plan-heading">
      <h2 class="h5" id="ctr-plan-heading">Plan all draws</h2>
      <p><strong><?php echo e(count($source['rows'])); ?></strong> available entries · <strong><?php echo e($source['excluded']); ?></strong> already assigned · <strong><?php echo e($source['existing']->count()); ?></strong> existing draws</p>
      <p class="small text-muted">Categories and ball levels stay separate. Groups play round robins, usually four players and at most five. A linked six-player competition adds straight-over finals and shares one winner. Review the match counts and prizes below. Existing draws are preserved.</p>
      <form method="POST" action="<?php echo e(route('backend.ctr-planner.preview', $event)); ?>" class="d-flex gap-3 align-items-end flex-wrap"><?php echo csrf_field(); ?>
        <div><label class="form-label" for="ctr-target">Preferred players per group</label><select id="ctr-target" class="form-select" name="target"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [3,4,5]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($size); ?>" <?php if($size === (int) ($plan?->proposal['target'] ?? 4)): echo 'selected'; endif; ?>><?php echo e($size); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
        <button id="ctr-plan-all" class="btn btn-primary" type="submit" <?php if(count($source['rows']) === 0): echo 'disabled'; endif; ?>>Plan all draws</button>
      </form>
    </section>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan): ?>
      <?php
        $competitions = $planner->competitions($plan->proposal);
      ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan->applied_at): ?>
        <section class="card card-body" id="ctr-created"><h2 class="h5">Your draws are ready</h2><p><?php echo e(count($plan->draw_ids)); ?> competitions · <?php echo e(count($plan->draw_ids)); ?> winner prizes · <?php echo e(count($plan->draw_ids)); ?> sportsmanship prizes</p>
          <ul><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\Draw::where('event_id', $event->id)->whereIn('id', $plan->draw_ids)->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $created): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><a href="<?php echo e(route('backend.draw.roundrobin.show', $created)); ?>"><?php echo e($created->drawName); ?></a></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
          <a class="btn btn-outline-primary align-self-start" href="<?php echo e(route('headOffice.show', $event)); ?>">View all event draws</a>
        </section>
      <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($plan->proposal['version'] ?? null) !== \App\Services\Draw\CtrDrawPlanner::VERSION): ?>
          <p class="alert alert-warning" id="ctr-old-preview">This saved preview uses an earlier grouping rule. Press Plan all draws for a fresh preview. Your saved preview has not been changed.</p>
        <?php else: ?>
          <?php echo $__env->make('backend.draw.ctr-group-board', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user')): ?><details class="mb-4" data-super-admin-only><summary>Certification settings</summary><form method="POST" action="<?php echo e(route('backend.ctr-planner.certify', $event)); ?>" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="certified" value="0"><button class="btn btn-outline-secondary" type="submit">Remove CTR certification</button></form></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<script src="<?php echo e(asset('js/ctr-planner.js')); ?>?v=<?php echo e(filemtime(public_path('js/ctr-planner.js'))); ?>" defer></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\ctr-planner.blade.php ENDPATH**/ ?>