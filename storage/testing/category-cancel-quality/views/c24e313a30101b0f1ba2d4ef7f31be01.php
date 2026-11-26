<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Cape Tennis Plus morning updates</title></head>
<body style="font-family:Arial,sans-serif;color:#20384b;line-height:1.6">
<h1>Cape Tennis Plus morning updates</h1>
<p>Here is new information you have not marked as read on Cape Tennis.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <section style="margin-bottom:24px">
    <h2><?php echo e($item['snapshot']['name']); ?></h2>
    <p><strong><?php echo e(match ($item['kind']) { 'new_event' => 'New event added', 'announcement' => 'Announcement', default => 'Event information updated' }); ?></strong></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['kind'] === 'announcement'): ?>
      <p><?php echo e($item['snapshot']['title'] ?: 'Event update'); ?></p>
      <p><?php echo e($item['snapshot']['excerpt']); ?></p>
    <?php else: ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['snapshot']['start_date']): ?><p>Starts: <?php echo e($item['snapshot']['start_date']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['snapshot']['end_date']): ?> · Ends: <?php echo e($item['snapshot']['end_date']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['snapshot']['registration_closes_at']): ?><p>Registration closes: <?php echo e($item['snapshot']['registration_closes_at']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['snapshot']['venue_notes']): ?><p><?php echo e(\Illuminate\Support\Str::limit(strip_tags($item['snapshot']['venue_notes']), 300)); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['snapshot']['information']): ?><p><?php echo e(\Illuminate\Support\Str::limit(strip_tags($item['snapshot']['information']), 500)); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p><a href="<?php echo e(route('events.show', $item['snapshot']['event_id']).($item['kind'] === 'announcement' ? '#event-announcement-'.substr($item['key'], 13) : '')); ?>">View event details and latest registration or nomination information</a></p>
  </section>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p>Check the event page for current dates, entry requirements and any later changes. Essential event information remains free.</p>
<p><a href="<?php echo e(route('home')); ?>">Browse Cape Tennis events</a></p>
<p><a href="<?php echo e(route('player-plus.notifications')); ?>">Snooze or turn off your Plus update emails</a></p>
</body></html>
<?php /**PATH D:\wamp64\www\ct\resources\views\emails\plus-morning-digest.blade.php ENDPATH**/ ?>