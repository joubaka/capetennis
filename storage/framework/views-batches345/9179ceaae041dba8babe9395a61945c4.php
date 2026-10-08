<table>
  <thead>
    <tr>
      <th>Region</th>
      <th>Team</th>
      <th>Team Published</th>
      <th>Rank</th>
      <th>Player Type</th>
      <th>Player Name</th>
      <th>Email</th>
      <th>Cell</th>
      <th>Pay Status</th>
    </tr>
  </thead>
  <tbody>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team->players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><?php echo e($region->region_name); ?></td>
            <td><?php echo e($team->name); ?></td>
            <td><?php echo e($team->published ? 'Published' : 'Not Published'); ?></td>
            <td><?php echo e($player->pivot->rank ?? '—'); ?></td>
            <td>Profile</td>
            <td><?php echo e($player->name); ?> <?php echo e($player->surname); ?></td>
            <td><?php echo e($player->email ?? '—'); ?></td>
            <td><?php echo e($player->cellNr ?? '—'); ?></td>
            <td><?php echo e($player->pivot->pay_status ? 'Paid' : 'Not Paid'); ?></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </tbody>
</table>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\exports\event_players_excel.blade.php ENDPATH**/ ?>