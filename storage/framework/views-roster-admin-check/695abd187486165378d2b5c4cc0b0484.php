<div class="table-responsive">
  <table class="table table-sm">
    <thead>
    <tr>
      <th scope="col">Fixture Id</th>
      <th  scope="col">Time</th>

      <th scope="col">Registration 1</th>
      <th  scope="col"></th>
      <th scope="col">Registration 2</th>
      <th  scope="col">Result</th>
      <th  scope="col">Actions</th>
    </tr>
    </thead>



    <tbody class="table-border-bottom-0">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bracket->fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr data-id="<?php echo e($fixture->id); ?>" id="<?php echo e($fixture->id); ?>">
        <td> #<?php echo e($fixture->id); ?><br><?php echo e($fixture->bracket->name); ?> </td>
        <td>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
            <button data-bs-toggle="modal" data-bs-target="#change-schedule-modal" class="btn timeVenue" data-id="<?php echo e($fixture->oop); ?>" data-fixture="<?php echo e($fixture); ?>" >
              <div class="badge bg-label-secondary">
                <?php echo e($fixture->oop ? $fixture->oop->time:'not Scheduled'); ?><br>
                <span class="badge bg-label-primary"><?php echo e($fixture->oop ? $fixture->oop->venue->name:'not Scheduled'); ?></span>

              </div>
            </button>

          <?php else: ?>
            <div class="notAuth">
            <div class="badge bg-label-secondary">
              <?php echo e($fixture->oop ? $fixture->oop->time:'not Scheduled'); ?><br>
              <span class="badge bg-label-primary"><?php echo e($fixture->oop ? $fixture->oop->venue->name:'not Scheduled'); ?></span>

            </div>
</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>



        </td>


        <td  class=" p1 bg-label-<?php echo e($bracket->getWinnerRegistration($fixture->id,$fixture->registration1_id)); ?> registration1" data-id="<?php echo e($fixture->registrations1 ? $fixture->registrations1->id:''); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration1_id > 0): ?>
            <?php echo e($fixture->registrations1['players'][0]['name'].' '.$fixture->registrations1['players'][0]['surname']); ?>


          <?php elseif(is_null($fixture->registration1_id)): ?>

          <?php echo e($bracket->getFixtureFrom($fixture)); ?>

          <?php else: ?>
            BYE
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </td>
        <td>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixtureResults->count() > 0): ?>
            <span class="badge bg-label-primary">vs</span>
          <?php else: ?>
            <button type="button" data-id="<?php echo e($fixture); ?>" data-reg1="<?php echo e($fixture->registrations1 ? $fixture->registrations1->players[0]->full_name:''); ?> " data-reg2="<?php echo e($fixture->registrations2 ? $fixture->registrations2->players[0]->full_name:''); ?> " class="btn btn-sm btn-success insertResult" data-bs-toggle="modal" data-bs-target="#tennisResultModal" >vs</button></td>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <td class="p2 bg-label-<?php echo e($bracket->getWinnerRegistration($fixture->id,$fixture->registration2_id)); ?> registration2" data-id="<?php echo e($fixture->registrations2 ? $fixture->registrations2->id:''); ?>">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration2_id > 0): ?>
            <?php echo e($fixture->registrations2['players'][0]['name'].' '.$fixture->registrations2['players'][0]['surname']); ?>

          <?php elseif(is_null($fixture->registration2_id)): ?>

          <?php else: ?>
            BYE
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </td>
        <td class="resultTd">

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixtureResults->count() > 0): ?>
            <?php echo e($bracket->result($fixture->id)); ?>

          <?php else: ?>

            <button type="button" data-id="<?php echo e($fixture); ?>" data-reg1="<?php echo e($fixture->registrations1 ? $fixture->registrations1->players[0]->full_name:''); ?> " data-reg2="<?php echo e($fixture->registrations2 ? $fixture->registrations2->players[0]->full_name:''); ?> " class="btn btn-sm btn-success insertResult" data-bs-toggle="modal" data-bs-target="#tennisResultModal">Insert Score</button></td>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


        <td>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixtureResults->count() > 0): ?>
            <button  data-id="<?php echo e($fixture->id); ?>" class="deleteFixture btn btn-xs btn-danger" ><i class="ti ti-trash me-1" ></i> Delete</button>
          <?php else: ?>


          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody>
  </table>
</div>
<?php echo $__env->make('backend.draw._modals.change-schedule-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\bracket\partials\fixtures.blade.php ENDPATH**/ ?>