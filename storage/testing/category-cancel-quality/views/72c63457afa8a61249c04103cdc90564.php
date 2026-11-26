<?php
  $changes = collect($preview['matches'])->map(function ($match) use ($originalRows) {
      $before = $originalRows->get($match['fixture_key']);
      $same = $before && collect(['scheduled_at','venue_id','court','duration'])->every(fn ($key) => (string) ($before[$key] ?? '') === (string) ($match[$key] ?? ''));
      return ['match'=>$match, 'before'=>$before, 'state'=>!$before ? 'New booking' : ($same ? 'Unchanged' : 'Changed')];
  });
?>
<section class="card card-body mb-3" aria-label="Review schedule changes">
  <h4 class="h5">Review before saving privately</h4>
  <p><?php echo e($changes->where('state','New booking')->count()); ?> new bookings / <?php echo e($changes->where('state','Changed')->count()); ?> changed / <?php echo e($changes->where('state','Unchanged')->count()); ?> unchanged proposals / <?php echo e(count($preview['existing_matches'] ?? [])); ?> existing bookings kept fixed in this planning window.</p>
  <p class="small text-muted">Played matches, unselected rounds and bookings on other days stay fixed. Fixed-booking count covers this preview's venue and time window. Publishing remains a separate step.</p>
  <details><summary style="min-height:44px">Before and after times and courts</summary>
    <div class="table-responsive"><table class="table"><thead><tr><th>Draw / match</th><th>Change</th><th>Before</th><th>After</th></tr></thead><tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $changes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $change): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($match = $change['match']); ?>
        <?php ($before = $change['before']); ?>
        <tr><td><?php echo e($match['draw_name'] ?? ''); ?> / Match <?php echo e($match['match'] ?? $match['fixture_id']); ?></td><td><?php echo e($change['state']); ?></td><td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($before): ?><?php echo e(substr($before['scheduled_at'],0,16)); ?> / <?php echo e($before['venue_name']); ?> / Court <?php echo e($before['court']); ?> / <?php echo e($before['duration']); ?> min <?php else: ?> Unscheduled <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><td><?php echo e(substr($match['scheduled_at'],0,16)); ?> / <?php echo e($venues->firstWhere('id',(int)$match['venue_id'])?->name); ?> / Court <?php echo e($match['court']); ?> / <?php echo e($match['duration']); ?> min</td></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody></table></div>
  </details>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\schedule\partials\change-review.blade.php ENDPATH**/ ?>