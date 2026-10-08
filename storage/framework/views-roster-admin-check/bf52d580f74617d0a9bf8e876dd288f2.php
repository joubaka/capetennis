
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team->team_players_no_profile; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $play): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

  <li class="d-flex align-items-center mb-4">
      <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
          <div class="me-2">
              <div class="d-flex align-items-center">
                  <div class="fs-6 mb-0 me-1"><span class="badge bg-label-primary"><?php echo e($key+1); ?></span> <?php echo e(ucfirst(strtolower($play->name))); ?> <?php echo e(ucfirst(strtolower($play->surname))); ?></div>

              </div>
              <small class="text-muted"></small>
          </div>
          <div class="user-progress">
              <p class="text-success fw-semibold mb-0">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->team_players[$key]->pay_status == 1): ?>
                 
                  <span class="badge bg-label-success">Registered</span>

                  <?php else: ?>
                
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->signUp == 1): ?>
                    
                      <a href="<?php echo e(route('player.create',['name'=> ucfirst(strtolower($play->name)),'surname' => ucfirst(strtolower($play->surname)),'team'=>$team->id,'event'=>$event->id,'noProfileId'=> $play->id])); ?>" class="badge bg-label-warning">Register now</a>
                      <?php else: ?>

                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                 
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              </p>
          </div>
      </div>


  </li>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\team\noProfile.blade.php ENDPATH**/ ?>