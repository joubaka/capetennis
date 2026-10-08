<?php
  $programme = \Illuminate\Support\Facades\Schema::hasTable('trial_programmes') ? \App\Models\TrialProgramme::with('currentRun')->where('event_id', $event->id)->first() : null;
  $squad = $programme ? \App\Models\TrialSquadDraft::where('event_id', $event->id)->where('status', 'finalised')->latest('id')->first() : null;
  $publishedSlots = $squad?->slots()->with(['player', 'categoryEvent.category'])->orderBy('category_event_id')->orderBy('tier')->orderBy('slot')->get() ?? collect();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($programme?->concluded_at && $programme->currentRun): ?>
<div class="card mt-4"><div class="card-body"><h5>Final Trials rankings</h5>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($programme->currentRun->positions)->groupBy('category_event_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryId => $positions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <h6><?php echo e($event->categoryEvents->firstWhere('id', $categoryId)?->category?->name ?? 'Category'); ?></h6>
    <ol><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $positions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $position): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($position['name']); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ol>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($squad): ?>
<div class="card mt-4"><div class="card-body"><h5>Selected regional teams</h5>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($squad->needs_review): ?><p class="alert alert-warning">Updated Trials results are under administrative review. The current roster remains published until changes are approved.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $publishedSlots->groupBy('category_event_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categorySlots): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <h6><?php echo e($categorySlots->first()->categoryEvent?->category?->name); ?></h6>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categorySlots->groupBy('tier'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tier => $teamSlots): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <h6>Team <?php echo e($tier); ?></h6>
      <ul class="list-group mb-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teamSlots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li id="trial-squad-slot-<?php echo e($slot->id); ?>" class="list-group-item <?php echo e(request()->integer('slot') === $slot->id ? 'bg-label-primary' : ''); ?>">
          <span><?php echo e($slot->reserve ? 'Reserve '.($slot->slot - 6) : $slot->slot); ?>. <?php echo e($slot->player?->full_name ?? 'Vacant place'); ?></span>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($slot->player_id && !$slot->reserve): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
              <form method="POST" class="d-flex flex-wrap gap-2 mt-2" action="<?php echo e(route('interprovincial-trials.squads.respond', [$event, $slot])); ?>"><?php echo csrf_field(); ?>
                <button name="response" value="confirmed" class="btn btn-sm btn-outline-success">Confirm participation</button>
                <button name="response" value="declined" class="btn btn-sm btn-outline-secondary">Decline participation</button>
              </form>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($slot->response !== 'declined'): ?>
                <form method="POST" class="mt-2" action="<?php echo e(route('interprovincial-trials.participation.begin', [$event, $slot])); ?>"><?php echo csrf_field(); ?><button class="btn btn-sm btn-primary">Register and pay participation fee</button></form>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $slot->responded_by === (int) auth()->id()): ?><span class="small">Your response: <?php echo e($slot->response); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?> <a href="<?php echo e(route('login')); ?>">Sign in to respond</a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\trial-programme.blade.php ENDPATH**/ ?>