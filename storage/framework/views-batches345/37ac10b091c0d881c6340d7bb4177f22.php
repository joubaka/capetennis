<style>#regionsAccordion .accordion-header > .accordion-button{flex-wrap:wrap;gap:.35rem;min-width:0}#regionsAccordion .region-name,#regionsAccordion .region-short-name{min-width:0;max-width:100%;overflow-wrap:anywhere}#regionsAccordion .accordion-header > .accordion-button::after{flex-shrink:0}</style>
       



<div class="tab-pane fade" id="tab-regions" role="tabpanel" aria-labelledby="tab-regions">
  <div class="card">

    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="m-0">
        <i class="ti ti-home me-1"></i> Regions & Teams in Event
      </h5>

      <button type="button"
              class="btn btn-primary btn-sm"
              data-bs-toggle="modal"
              data-bs-target="#modalToggle">
        <i class="ti ti-plus me-1"></i> Add Region
      </button>
    </div>

    <div class="card-body">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('team_rename_success')): ?>
          <div class="alert alert-success" role="status"><?php echo e(session('team_rename_success')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->teamRename->any()): ?>
          <div class="alert alert-danger" role="alert"><?php echo e($errors->teamRename->first('name')); ?> Open Edit Team Name to correct it.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="accordion" id="regionsAccordion">

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->regions->isEmpty()): ?>
            <div class="alert alert-primary noRegions text-center">
              <i class="ti ti-info-circle me-1"></i>
              No regions added to this event yet.
            </div>
          <?php else: ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $regionTeamCount = $region->teams->count();
              $regionUnpublishedCount = $region->teams->where('published', false)->count();
              $renameTeamId = $errors->teamRename->any() ? old('rename_team_id') : session('renamed_team_id');
              $renameRegionOpen = $renameTeamId && $region->teams->contains('id', (int) $renameTeamId);
            ?>

            
            <div class="accordion-item mb-2 border rounded"
                 data-region-row
                 data-region-id="<?php echo e($region->id); ?>"
                 data-pivot-id="<?php echo e($region->pivot->id); ?>">

              <h2 class="accordion-header" id="heading-<?php echo e($region->id); ?>">
                <button class="accordion-button <?php echo e($renameRegionOpen ? '' : 'collapsed'); ?> fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse-<?php echo e($region->id); ?>"
                        aria-expanded="<?php echo e($renameRegionOpen ? 'true' : 'false'); ?>">
                  <span class="badge bg-label-secondary me-2">#<?php echo e($region->id); ?></span>
                  <span class="region-name"><?php echo e($region->region_name); ?></span><span class="region-short-name ms-2 text-muted">(<?php echo e(\App\Support\RegionAbbreviation::label($region)); ?>)</span>
                  <span class="ms-2 text-muted small">
                    (<?php echo e($region->teams->count()); ?> Teams)
                  </span>
                </button>
              </h2>

              <div id="collapse-<?php echo e($region->id); ?>"
                   class="accordion-collapse collapse <?php echo e($renameRegionOpen ? 'show' : ''); ?>"
                   data-bs-parent="#regionsAccordion">

                <div class="accordion-body pt-2">

                  
                  <div class="d-flex flex-wrap justify-content-end align-items-center mb-2 gap-2">
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary renameRegionEvent"
                            data-id="<?php echo e($region->pivot->id); ?>"
                            data-name="<?php echo e($region->region_name); ?>"
                            data-short-name="<?php echo e($region->short_name); ?>"
                            data-event-count="<?php echo e($region->events()->count()); ?>">
                      <i class="ti ti-edit me-1"></i> Edit Region
                    </button>

                    <a href="javascript:void(0)"
                       class="text-danger removeRegionEvent"
                       data-id="<?php echo e($region->pivot->id); ?>">
                      <i class="ti ti-trash me-1"></i> Remove Region
                    </a>

                    <button type="button"
                            class="btn btn-sm btn-success publishRegionTeams"
                            data-url="<?php echo e(route('backend.region.teams.publish', [$event, $region])); ?>"
                            data-team-count="<?php echo e($regionTeamCount); ?>"
                            data-unpublished-count="<?php echo e($regionUnpublishedCount); ?>"
                            <?php if($regionTeamCount === 0 || $regionUnpublishedCount === 0): echo 'disabled'; endif; ?>>
                      <i class="ti ti-eye me-1"></i>
                      <?php echo e($regionTeamCount > 0 && $regionUnpublishedCount === 0 ? 'All Teams Published' : 'Publish All Teams'); ?>

                    </button>

                    <a href="javascript:void(0)"
                       class="btn btn-sm btn-outline-primary import-region-teams-btn"
                       data-region-name="<?php echo e($region->region_name); ?>"
                       data-team-prefix="<?php echo e($region->short_name ?: $region->region_name); ?>"
                       data-import-url="<?php echo e(route('backend.region.teams.import.no.profile', [$event, $region])); ?>"
                       data-bs-toggle="modal"
                       data-bs-target="#import-region-teams-modal">
                      <i class="ti ti-file-spreadsheet me-1"></i> Import Teams
                    </a>

                    <a href="javascript:void(0)"
                       class="btn btn-sm btn-primary addTeam"
                       data-regionid="<?php echo e($region->id); ?>"
                       data-bs-toggle="modal"
                       data-bs-target="#addTeamModal">
                      <i class="ti ti-plus me-1"></i> Add Team
                    </a>
                  </div>

                  
                  <div class="teams-container">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($region->teams->isEmpty()): ?>
                    <div class="alert alert-light border text-center py-2 no-teams-alert">
                      No teams in this region yet.
                    </div>
                  <?php else: ?>

                    <div class="list-group">

                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                        
                        <div class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-start py-3 px-3 border-0 border-bottom"
                             data-team-row
                             data-team-id="<?php echo e($team->id); ?>">

                          <div class="flex-grow-1" style="min-width:0; overflow-wrap:anywhere">
                            <div class="fw-medium"><?php echo e($team->name); ?></div>

                            <small class="text-muted d-block mb-1 category-<?php echo e($team->id); ?>">
                              Category:
                              <span class="fw-semibold text-primary">
                                <?php echo e($team->category?->category?->name ?? 'None'); ?>

                              </span>
                            </small>

                            <button class="btn btn-xs bg-label-info edit-team-category"
                                    data-team='<?php echo json_encode($team->only(["id", "name"]), 512) ?>'
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-team-category-modal">
                              <i class="ti ti-edit me-25"></i> Edit Category
                            </button>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team.update', $team)): ?>
                              <details class="mt-2" <?php if($errors->teamRename->any() && (int) old('rename_team_id') === (int) $team->id): ?> open <?php endif; ?>>
                                <summary class="btn btn-xs btn-outline-secondary">Edit Team Name</summary>
                                <form method="POST" action="<?php echo e(route('backend.team.name.update', [$event, $team])); ?>" class="mt-2">
                                  <?php echo csrf_field(); ?>
                                  <?php echo method_field('PATCH'); ?>
                                  <input type="hidden" name="rename_team_id" value="<?php echo e($team->id); ?>">
                                  <label class="form-label" for="team-name-<?php echo e($team->id); ?>">Team name</label>
                                  <input class="form-control" id="team-name-<?php echo e($team->id); ?>" name="name" maxlength="255" required value="<?php echo e((int) old('rename_team_id') === (int) $team->id ? old('name', $team->name) : $team->name); ?>">
                                  <button type="submit" class="btn btn-sm btn-primary mt-2">Save Team Name</button>
                                </form>
                              </details>
                            <?php endif; ?>
                          </div>

                          <div class="text-end" style="min-width:180px">

                            
                            <button type="button"
                               class="publishTeam btn btn-xs w-100 mb-2
                               <?php echo e($team->published ? 'btn-warning' : 'btn-success'); ?>"
                               data-id="<?php echo e($team->id); ?>"
                               data-url="<?php echo e(route('publish.team', $team)); ?>"
                               data-state="<?php echo e((int)$team->published); ?>">

                              <i class="ti <?php echo e($team->published ? 'ti-eye-off' : 'ti-eye'); ?> me-1"></i>
                              <?php echo e($team->published ? 'Unpublish Team' : 'Publish Team'); ?>

                            </button>


                            
                            <a href="javascript:void(0)"
                               class="toggleNoProfile btn btn-xs w-100 mb-2
                               <?php echo e($team->noProfile ? 'btn-danger' : 'btn-info'); ?>"
                               data-url="<?php echo e(route('backend.teams.toggle-noprofile', $team->id)); ?>"
                               data-state="<?php echo e((int)$team->noProfile); ?>">

                              <i class="ti <?php echo e($team->noProfile ? 'ti-user-off' : 'ti-user'); ?> me-1"></i>
                              <?php echo e($team->noProfile ? 'Disable NoProfile' : 'Enable NoProfile'); ?>

                            </a>

                            
                     
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile == 1): ?>
                              <button type="button"
                                      class="import-noprofile-btn btn btn-xs btn-outline-success w-100 mb-2"
                                      data-region-id="<?php echo e($region->id); ?>"
                                      data-team-id="<?php echo e($team->id); ?>"
                                      data-team-name="<?php echo e($team->name); ?>"
                                      data-import-url="<?php echo e(route('backend.team.import.no.profile', [$event, $team])); ?>"
                                      data-template-url="<?php echo e(route('team.import.no.profile.template', [$event, $team])); ?>"
                                      data-bs-toggle="modal"
                                      data-bs-target="#import-noprofile-modal">
                                <i class="ti ti-file-import me-1"></i> Import Roster
                              </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <a href="javascript:void(0)"
                               class="text-danger small removeTeam"
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
            </div>

          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\regions.blade.php ENDPATH**/ ?>