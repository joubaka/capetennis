  <!-- Multiple Lists Draggable -->
  <div class="row mb-4">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

      <div class="col-12 col-md-3">
          <div class="card shadow-none bg-transparent border border-primary mb-2">
            <div class="card-header <?php echo e(count($c->positions) > 0 ? 'bg-label-success' : 'noooo'); ?>" data-categoryevent="<?php echo e($c->id); ?>">

                  <h5><?php echo e($c->category->name); ?> <?php echo e($c->id); ?></h5>
                  <button type="button"
                  class="btn btn-sm btn-outline-danger reset-order"
                  data-categoryevent="<?php echo e($c->id); ?>">
            Reset
          </button>
              </div>

              <div class="card-body ">

                  <div class="col-12  m-2">

                    <ul class="sortable" data-categoryevent="<?php echo e($c->id); ?>">
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($c->positions) > 0): ?>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $c->positions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $position): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                          <li data-categoryevent="<?php echo e($c->id); ?>" value="<?php echo e($position->player->id); ?>" class="list-group-item">
                              <span class="number"><?php echo e($key+1); ?></span><span> <?php echo e($position->player->name); ?> <?php echo e($position->player->surname); ?></span>

                          </li>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          <?php else: ?>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $c->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <li data-categoryevent="<?php echo e($c->id); ?>" value="<?php echo e($reg->players[0]->id); ?>" class="list-group-item">
                              <span class="number"></span><span> <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?></span>

                          </li>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </ul>
                      <ul class="original-order d-none" data-categoryevent="<?php echo e($c->id); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $c->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <li data-categoryevent="<?php echo e($c->id); ?>" value="<?php echo e($reg->players[0]->id); ?>" class="list-group-item">
                              <span class="number"></span><span> <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?></span>

                          </li>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </ul>
                  </div>


              </div>
          </div>


      </div>

      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <!-- /Multiple Lists Draggable ends -->
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\_includes\position_type.blade.php ENDPATH**/ ?>