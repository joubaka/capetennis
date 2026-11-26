<!doctype html><html lang="en"><body>
<h1><?php echo e($notification->revision > 1 ? 'Corrected match result' : 'A new match result has been entered'); ?></h1>
<p>Event: <?php echo e($notification->snapshot['event']); ?><br>Draw: <?php echo e($notification->snapshot['draw']); ?></p>
<p><?php echo e(implode(' / ', $notification->snapshot['home_names'])); ?> versus <?php echo e(implode(' / ', $notification->snapshot['away_names'])); ?></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($notification->snapshot['result_type'] ?? null) === 'walkover'): ?>
<p>Result: <?php echo e(implode(' / ', $notification->snapshot['winner_names'])); ?> won by walkover (W/O).</p>
<?php elseif(($notification->snapshot['result_type'] ?? null) === 'retired'): ?>
<p>Result: <?php echo e(collect($notification->snapshot['sets'])->map(fn ($set) => implode('–', $set))->implode(', ')); ?> RET. <?php echo e(implode(' / ', $notification->snapshot['winner_names'])); ?> won by retirement.</p>
<?php else: ?>
<p>Result: <?php echo e(collect($notification->snapshot['sets'])->map(fn ($set) => implode('–', $set))->implode(', ')); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p><a href="<?php echo e(route('frontend.fixtures.index', $notification->snapshot['draw_id'])); ?>">View the match result</a></p>
<p>Please check that this result is correct. If anything is wrong, contact the convener at the court.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replyTo): ?>
<p>You can also reply to this email to contact the event administrators. The reply addresses are <?php echo e(implode(', ', $replyTo)); ?>; please check that your email app includes all of them before sending.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedParentNames): ?>
<p>You received this email because you are linked to <?php echo e(implode(' / ', $linkedParentNames)); ?> as a parent.
To remove yourself, <a href="<?php echo e(route('backend.dashboard', ['manage_players' => 1])); ?>#dashboard-account">click here</a> and remove the player from your linked players.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body></html>
<?php /**PATH D:\wamp64\www\ct\resources\views\emails\match-result.blade.php ENDPATH**/ ?>