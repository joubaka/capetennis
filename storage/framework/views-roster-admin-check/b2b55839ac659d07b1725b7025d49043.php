  <!-- Multiple Lists Draggable -->
  <div class="row mb-4">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $category_event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

      <div class="col-12 col-md-4">
          <div class="card shadow-none bg-transparent border border-primary mb-2">
              <div class="card-header <?php echo e(count($category_event->positions) > 0 ? 'bg-label-success':'noooo'); ?>">
                  <h5><?php echo e($category_event->category->name); ?></h5>
              </div>

              <div class="card-body ">
                  <form class="subitScoresForm">
                      <input type="hidden" name="category_event" value="<?php echo e($category_event->id); ?>">
                      <div class="col-12 m-2">

                          <ul class="">

                            
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $category_event->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>


                              <li data-categoryevent="<?php echo e($category_event->id); ?>" value="<?php echo e($reg->players[0]->id); ?>" class="list-group-item">
                                  <span class="row">
                                      <span class="col-6"> <span class="number"></span><span> <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?></span></span>
                                      <span class="col-6">

                                          <span><input type="text" class="form-control" placeholder="score" name="rrscore[]" value="<?php echo e($reg->players[0]->positions($category_event->id) ? $reg->players[0]->positions($category_event->id)->round_robin_score : 0); ?>"></span>

                                      </span>

                                  </span>
                                  <input type="hidden" name="order[]" value="<?php echo e($reg->players[0]->id); ?>">

                              </li>
                              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                             
                          </ul>

                      </div>
                  </form>
                  <div class="btn btn-secondary btn-sm submitScoreButton">Submit Scores</div>

              </div>
          </div>


      </div>

      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <!-- /Multiple Lists Draggable ends --><?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\_includes\participation_type.blade.php ENDPATH**/ ?>