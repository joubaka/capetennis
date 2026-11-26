<!-- Modal -->
<div class="modal fade" id="addTeamModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="teamForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Team Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($event)): ?><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event)): ?>
                      <label class="form-label" for="new-team-category">Category</label><select id="new-team-category" name="category_event_id" class="form-select" required><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $teamCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($teamCategory->id); ?>"><?php echo e($teamCategory->category?->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div>
                        <label for="defaultFormControlInput" class="form-label">Name</label>
                        <input name="team_name" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp">

                    </div>
                    <div>
                        <label for="defaultFormControlInput" class="form-label">Number of Players in Team</label>
                        <input name="num_players" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp">

                    </div>
                    <div>
                        <label for="defaultFormControlInput" class="form-label">Year</label>
                        <input name="year" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp">

                    </div>
                    <input type="hidden" name="published" value="0">
                    <input id="region_id" type="hidden" name="region_id" value="">



                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="updateTeamButton" data-bs-dismiss="modal">Add Team</button>
                </div>
            </div>

        </form>

    </div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\_partials\_modals\modal-add-team.blade.php ENDPATH**/ ?>