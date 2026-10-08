



<?php
  use Illuminate\Support\Str;
?>

<div class="container-fluid mt-3">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php
    $checked = $event->regions->contains('id', $region->id);
  ?>

  <div class="form-check">
    <input class="form-check-input event-region-checkbox"
           type="checkbox"
           value="<?php echo e($region->id); ?>"
           <?php echo e($checked ? 'checked' : ''); ?>>
    <label class="form-check-label">
      <?php echo e($region->region_name); ?>

    </label>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<button id="saveEventRegions" class="btn btn-sm btn-outline-primary mt-3">
  Save Regions
</button>

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-semibold mb-0">Team Assignment</h4>

    <div class="d-flex align-items-center gap-2">

      
      <select id="categoryFilter" class="form-select form-select-sm" style="width:auto;">
        <option value="all">All Categories</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($categoryEvent->id); ?>">
            <?php echo e($categoryEvent->category->name); ?>

          </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>

      
      <button id="addTeamBtn" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> Add Team
      </button>

      
      <button id="saveTeams" class="btn btn-sm btn-success">
        <i class="ti ti-device-floppy"></i> Save Teams
      </button>

    </div>
  </div>

  <div class="alert alert-info py-2 small mb-3">
    Drag players into teams (category restricted). Drag inside a team to change playing order.
  </div>

  <div class="row">

    
    <div class="col-lg-3">

      <div class="accordion" id="categoryAccordion">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $catId   = $categoryEvent->id;
            $catName = $categoryEvent->category->name;
          ?>

          <div class="accordion-item category-block"
               data-category-event-id="<?php echo e($catId); ?>">

            <h2 class="accordion-header">
              <button class="accordion-button py-2" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse-cat-<?php echo e($catId); ?>">
                <?php echo e($catName); ?>

              </button>
            </h2>

            <div id="collapse-cat-<?php echo e($catId); ?>" class="accordion-collapse collapse show">
              <div class="accordion-body p-2">

                <ul class="list-group sortable-player-pool"
                    data-category-event-id="<?php echo e($catId); ?>"
                    style="min-height:150px;">

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categoryEvent->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                      $player = $registration->players->first();
                    ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center"
                          data-player-id="<?php echo e($player->id); ?>"
                          data-category-event-id="<?php echo e($catId); ?>">

                        <span><?php echo e($player->name); ?> <?php echo e($player->surname); ?></span>
                        <small class="text-muted"><?php echo e($catName); ?></small>
                      </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </ul>

              </div>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      </div>
    </div>

    
    <div class="col-lg-9">

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

        <h5 class="fw-semibold mt-2 mb-2">
          <?php echo e($region->region_name); ?>

        </h5>

        <div class="row g-3 mb-4">

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\Team::where('region_id', $region->id)
              ->whereIn('category_event_id', $event->categoryEvents->pluck('id'))
              ->orderBy('name')
              ->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

            <div class="col-md-4 team-wrapper"
                 data-region-id="<?php echo e($team->region_id); ?>"
                 data-category-event-id="<?php echo e($team->category_event_id); ?>">

              <div class="card shadow-sm border border-primary team-card"
                   data-team-id="<?php echo e($team->id); ?>">

                
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                  <span class="fw-bold"><?php echo e($team->name); ?></span>
                  <button class="btn btn-sm btn-outline-light remove-team">×</button>
                </div>

                
                <ul class="list-group list-group-flush sortable-team"
                    data-category-event-id="<?php echo e($team->category_event_id); ?>"
                    style="min-height:200px;">

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $team->team_players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center"
                        data-player-id="<?php echo e($tp->player_id); ?>">
                      <span><?php echo e($tp->player->name); ?> <?php echo e($tp->player->surname); ?></span>
                      <small class="text-muted">#<?php echo e($tp->rank); ?></small>
                    </li>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </ul>

              </div>
            </div>

          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </div>
</div>


<div class="modal fade" id="createTeamModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Create New Team</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label">Team Name</label>
          <input type="text" id="teamName" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Category</label>
          <select id="teamCategory" class="form-select" required>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($categoryEvent->id); ?>">
                <?php echo e($categoryEvent->category->name); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Region</label>
          <select id="teamRegion" class="form-select" required>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($region->id); ?>">
                <?php echo e($region->region_name); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="createTeamConfirm">Create Team</button>
      </div>

    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\team\tabs\teams.blade.php ENDPATH**/ ?>