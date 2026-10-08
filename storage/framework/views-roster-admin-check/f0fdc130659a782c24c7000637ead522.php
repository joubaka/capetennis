<?php
  $publicDrawWorkflow = $draw->settings?->workflow;
  $publicDrawFormat = \App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$publicDrawWorkflow][0]
    ?? ($draw->usesFlexibleMonrad() ? 'Custom Monrad' : 'Format not specified');
  $publicDrawVenues = $draw->venues->pluck('name')->filter()->unique()->values();
  $publicDrawIsPreview = ! (bool) $draw->published;
  $publicDrawEventDate = optional($draw->event?->start_date)?->format('D d M Y');
?>

<header class="ct-public-draw-header">
  <a class="ct-public-draw-back" href="<?php echo e(route('events.show', $draw->event_id)); ?>">
    <span aria-hidden="true">&larr;</span> Back to tournament
  </a>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicDrawIsPreview): ?>
    <div class="ct-public-draw-preview" role="status">
      <strong>Draft preview</strong>
      <span>This draw is visible to authorised staff only and has not been released publicly.</span>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="ct-public-draw-heading">
    <div>
      <p class="ct-public-draw-eyebrow">Cape Tennis tournament draw</p>
      <h1><?php echo e($draw->drawName); ?></h1>
      <p class="ct-public-draw-event"><?php echo e($draw->event?->name); ?></p>
    </div>
    <div class="ct-public-draw-actions">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($publicDrawPrintButtonId)): ?>
        <button id="<?php echo e($publicDrawPrintButtonId); ?>" type="button" class="ct-public-draw-action">Print</button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view', $draw)): ?>
        <a class="ct-public-draw-action ct-public-draw-manage" href="<?php echo e(route('backend.draw.roundrobin.show', $draw)); ?>">Manage this draw</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="ct-public-draw-meta" aria-label="Draw details">
    <span><?php echo e($publicDrawFormat); ?></span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicDrawEventDate): ?><span><?php echo e($publicDrawEventDate); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicDrawVenues->isNotEmpty()): ?><span><?php echo e($publicDrawVenues->join(', ')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <span class="<?php echo e($draw->published ? 'is-ready' : 'is-pending'); ?>"><?php echo e($draw->published ? 'Draw published' : 'Draw not published'); ?></span>
    <span class="<?php echo e($draw->oop_published ? 'is-ready' : 'is-pending'); ?>"><?php echo e($draw->oop_published ? 'Match times published' : 'Match times to follow'); ?></span>
  </div>
</header>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\draw\partials\public-header.blade.php ENDPATH**/ ?>