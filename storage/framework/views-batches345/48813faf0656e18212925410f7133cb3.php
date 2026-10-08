<?php
  // Quick counts for badges
  $regionCount   = $event->regions->count();
  $teamCount     = $event->regions->sum(fn($r) => $r->teams->count());
  $categoryCount = $event->eventCategories->count();
  $playerCount   = $event->region_in_events->sum(
                     fn($r) => $r->teams->sum(fn($t) => $t->players->count())
                   );
?>

<style>
  .tabs-wrap {
    position: sticky; top: 72px; z-index: 100;
    background: var(--bs-body-bg);
    border-bottom: 1px solid var(--bs-border-color);
  }
  .tabs-wrap .nav-tabs {
    flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden;
    gap: .25rem; scrollbar-width: thin;
  }
  .tabs-wrap .nav-link {
    white-space: nowrap; display: inline-flex; align-items: center; gap: .4rem;
    padding: .5rem .75rem;
  }
  .tabs-wrap .nav-link .badge {
    transform: translateY(-1px);
  }
  .subtabs-sticky {
    position: sticky; top: 124px; z-index: 90;
    background: var(--bs-body-bg); border-bottom: 1px solid var(--bs-border-color);
  }
  .subtabs-sticky .nav-tabs { overflow-x: auto; flex-wrap: nowrap; }
  .tab-pane .card-header { display: flex; align-items: center; justify-content: space-between; }
</style>

<div class="col-xl-12">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isInterprovincialTrials()): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <div><strong>Interprovincial Trials invitations</strong><br><small>Load existing player nominations, review exact recipients, and queue invitations.</small></div>
      <a class="btn btn-primary" href="<?php echo e(route('backend.interprovincial-trials.invitations.index', $event)); ?>">Manage invitations</a>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


  <div class="col-xl-12">
    <div class="nav-tabs-shadow mb-4">

      
      <div class="tabs-wrap">
        <ul class="nav nav-tabs nav-fill px-2" role="tablist">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::id() === 584): ?>
            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link" role="tab"
                data-bs-toggle="tab" data-bs-target="#tab-regions"
                aria-controls="tab-regions" aria-selected="false">
                <i class="ti ti-home ti-xs me-1"></i>
                Regions
                <span class="badge rounded-pill bg-label-primary ms-1"><?php echo e($regionCount); ?></span>
                <span class="badge rounded-pill bg-label-info ms-1"><?php echo e($teamCount); ?></span>
              </button>
            </li>

            <li class="nav-item" role="presentation">
              <button type="button" class="nav-link" role="tab"
                data-bs-toggle="tab" data-bs-target="#tab-categories"
                aria-controls="tab-categories" aria-selected="false" tabindex="-1">
                <i class="ti ti-category ti-xs me-1"></i>
                Categories
                <span class="badge rounded-pill bg-label-warning ms-1"><?php echo e($categoryCount); ?></span>
              </button>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link active" role="tab"
              data-bs-toggle="tab" data-bs-target="#tab-players"
              aria-controls="tab-players" aria-selected="true">
              <i class="ti ti-users-group ti-xs me-1"></i>
              Players
              <span class="badge rounded-pill bg-label-success ms-1"><?php echo e($playerCount); ?></span>
            </button>
          </li>

          <li class="nav-item" role="presentation">
            <button type="button" class="nav-link" role="tab"
              data-bs-toggle="tab" data-bs-target="#tab-order"
              aria-controls="tab-order" aria-selected="false" tabindex="-1">
              <i class="ti ti-list-ordered ti-xs me-1"></i>
              Player order
            </button>
          </li>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::id() === 584): ?>
            <li class="nav-item" role="presentation">
              <button id="result-rank-button" type="button" class="nav-link" role="tab"
                data-bs-toggle="tab" data-bs-target="#tab-result-rank"
                aria-controls="tab-result-rank" aria-selected="false">
                <i class="ti ti-award ti-xs me-1"></i>
                Result Ranks
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <a href="<?php echo e(route('headOffice.show', $event->id)); ?>" class="nav-link">
                <i class="ti ti-gauge ti-xs me-1"></i> Dashboard
              </a>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
      </div>

      <div class="tab-content p-3">

       
<div class="tab-pane fade" id="tab-regions" role="tabpanel" aria-labelledby="tab-regions">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="m-0"><i class="ti ti-home me-1"></i> Regions & Teams in Event</h5>
      <button type="button" class="btn btn-primary btn-sm" data-bs-target="#modalToggle" data-bs-toggle="modal">
        <i class="ti ti-plus me-1"></i> Add Region
      </button>
    </div>

    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->regions->isEmpty()): ?>
        <div class="alert alert-primary noRegions text-center" role="alert">
          <i class="ti ti-info-circle me-1"></i> No regions added to this event yet.
        </div>
      <?php else: ?>
        <div class="accordion" id="regionsAccordion">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="accordion-item mb-2 border rounded">
              <h2 class="accordion-header" id="heading-<?php echo e($region->id); ?>">
                <button class="accordion-button collapsed fw-semibold" type="button"
                        data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo e($region->id); ?>"
                        aria-expanded="false" aria-controls="collapse-<?php echo e($region->id); ?>">
                  <span class="me-2 badge bg-label-secondary">#<?php echo e($region->id); ?></span>
                  <?php echo e($region->region_name); ?>

                  <span class="ms-2 text-muted small">(<?php echo e($region->teams->count()); ?> Teams)</span>
                </button>
              </h2>

              <div id="collapse-<?php echo e($region->id); ?>" class="accordion-collapse collapse"
                   aria-labelledby="heading-<?php echo e($region->id); ?>" data-bs-parent="#regionsAccordion">
                <div class="accordion-body pt-2">
                  <div class="d-flex justify-content-end mb-2">
                    <a href="javascript:void(0)" class="text-danger removeRegionEvent me-2"
                       data-id="<?php echo e($region->pivot->id); ?>">
                      <i class="ti ti-trash me-1"></i> Remove Region
                    </a>
                    <a data-regionid="<?php echo e($region->id); ?>" data-bs-target="#addTeamModal"
                       data-bs-toggle="modal" href="javascript:void(0)" class="btn btn-sm btn-primary addTeam">
                      <i class="ti ti-plus me-1"></i> Add Team
                    </a>
                  </div>

                  
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($region->teams->isEmpty()): ?>
                    <div class="alert alert-light border text-center py-2">No teams in this region yet.</div>
                  <?php else: ?>
                    <div class="list-group">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom">
                          <div>
                            <div class="fw-medium"><?php echo e($team->name); ?></div>
                            <small class="text-muted d-block mb-1 category-<?php echo e($team->id); ?>">
                              Category:
                              <span class="fw-semibold text-primary">
                                <?php echo e($team->category ? $team->category->category->name : 'None'); ?>

                              </span>
                            </small>
                            <button class="btn btn-xs bg-label-info edit-team-category"
                                    data-team='<?php echo json_encode($team->only(["id", "name"]), 512) ?>'
                                    data-bs-toggle="modal" data-bs-target="#edit-team-category-modal">
                              <i class="ti ti-edit me-25"></i> Edit Category
                            </button>
                          </div>

                          <div class="text-end">
                            
                            <a href="javascript:void(0)"
                               class="publishTeam d-block mb-2"
                               data-state="<?php echo e((int)$team->published === 1 ? '1' : '0'); ?>"
                               data-id="<?php echo e($team->id); ?>">
                              <?php echo (int)$team->published === 0
                                  ? '<span class="badge bg-label-success">Publish Team</span>'
                                  : '<span class="badge bg-label-danger">Unpublish Team</span>'; ?>

                            </a>

                            
                            <a href="javascript:void(0)"
                               class="toggleTeam toggleNoProfile d-block mb-2"
                               data-url="<?php echo e(route('backend.teams.toggle-noprofile', $team->id)); ?>"
                               data-state="<?php echo e((int)$team->noProfile); ?>">
                              <?php echo (int)$team->noProfile === 0
                                  ? '<span class="badge bg-label-info">Enable NoProfile</span>'
                                  : '<span class="badge bg-label-warning">Disable NoProfile</span>'; ?>

                            </a>

                            
                            <a href="javascript:void(0)" class="text-danger removeTeam small"
                               data-id="<?php echo e($team->id); ?>">
                              <i class="ti ti-trash me-25"></i> Delete
                            </a>
                          </div>
                        </div>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
</div>

        
        <div class="tab-pane fade" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories">
          <div class="card">
            <div class="card-header">
              <h5 class="m-0">Event Categories</h5>
              <button class="btn btn-primary btn-sm" id="add-category-button"
                data-bs-toggle="modal" data-bs-target="#add-category-modal">
                <i class="ti ti-plus me-1"></i> Add Category
              </button>
            </div>
            <div class="card-body">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->eventCategories->isEmpty()): ?>
                <div class="alert alert-primary noRegions" role="alert">No Categories added to event</div>
              <?php else: ?>
                <ul class="list-group">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <span><?php echo e($category->category->name); ?></span>
                      <span class="text-muted">#<?php echo e($category->id); ?></span>
                    </li>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        </div>

  
  <?php echo $__env->make('backend.adminPage.admin_show.tabs.players', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> 

  
  <?php echo $__env->make('backend.adminPage.admin_show.tabs.player-order', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> 



        
        <?php echo $__env->make('backend.adminPage.admin_show._partials.result-ranks', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      </div> 
    </div>
  </div>
</div>

<div class="modal fade" id="editNoProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editNoProfileForm"><?php echo csrf_field(); ?>
        <div class="modal-header">
          <h5 class="modal-title">Edit Dummy Player</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="noProfileId">
          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="name" id="noProfileName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Surname</label>
            <input type="text" class="form-control" name="surname" id="noProfileSurname" required>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>



<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\interpro-dash.blade.php ENDPATH**/ ?>