<div class="row">



    <div class="col-12 col-sm-9 col-md-9">
        <ul class="nav nav-pills mb-2">
            <li class="nav-item me-2">
                <button id="create-draw-button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#drawModal">Create Draw</button>
            </li>

        </ul>
        <div class="card">
            <div class="row">


                <div class="col-12 col-md-12">
                    <div class="list-group m-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                        <?php echo $__env->make('backend.draw._includes.draw_tab_team', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                </div>
            </div>


        </div>
    </div>

</div>
<div class="modal fade" id="drawModal" tabindex="-1" aria-labelledby="drawModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Create Draw</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="create-draw-form">


                <div class="modal-body">
                    <div class="col-md-12 col-12 mb-md-0 mb-4">
                        <h5>Regions</h5>
                        <p>example: 1-3;2-4</p>
                        <ul class="list-group list-group-flush" id="pending-tasks">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li data-id="<?php echo e($region->pivot->id); ?>" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                                <span><?php echo e($region->region_name); ?></span>

                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                    </div>
                    <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
                    <div class="mt-4">
                        <h5>Draw Format type</h5>
                        <select name="drawType" id="smallSelect" class="form-select form-select-sm">
                            <option>Select Format</option>
                            <optgroup label="Team Formats">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teamDrawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($drawType->id); ?>"><?php echo e($drawType->drawTypeName); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                            <optgroup label="Individual Formats">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $individualDrawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($drawType->id); ?>"><?php echo e($drawType->drawTypeName); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md">
                            <small class="text-light fw-medium d-block">Checkboxes Colors</small>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="form-check form-check-primary mt-3">
                                <input class="form-check-input" name="category[]" type="checkbox" value="<?php echo e($eventCategory->id); ?>" />
                                <label class="form-check-label" for="customCheckPrimary"><?php echo e($eventCategory->category->name); ?></label>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>


                    </div>
                    <div class="pt-4">
                        <button type="button" id="create-fixtures-button" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light">Create Fixtures</button>
                        <button type="reset" class="btn btn-label-secondary waves-effect">Cancel</button>
                    </div>
                </div>
            </form>








        </div>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="drawModal" tabindex="-1" aria-labelledby="drawModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Create Draw</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="create-draw-form">


                <div class="modal-body">
                    <div class="col-md-12 col-12 mb-md-0 mb-4">
                        <h5>Regions</h5>
                        <p>example: 1-3;2-4</p>
                        <ul class="list-group list-group-flush" id="pending-tasks">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->region_in_events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li data-id="<?php echo e($region->pivot->id); ?>" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                                <span><?php echo e($region->region_name); ?></span>

                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                    </div>
                    <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
                    <div class="mt-4">
                        <h5>Draw Format type</h5>
                        <select name="drawType" id="smallSelect" class="form-select form-select-sm">
                            <option>Select Format</option>
                            <optgroup label="Team Formats">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teamDrawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($drawType->id); ?>"><?php echo e($drawType->drawTypeName); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                            <optgroup label="Individual Formats">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $individualDrawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($drawType->id); ?>"><?php echo e($drawType->drawTypeName); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md">
                            <small class="text-light fw-medium d-block">Checkboxes Colors</small>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="form-check form-check-primary mt-3">
                                <input class="form-check-input" name="category[]" type="checkbox" value="<?php echo e($eventCategory->id); ?>" />
                                <label class="form-check-label" for="customCheckPrimary"><?php echo e($eventCategory->category->name); ?></label>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>


                    </div>
                    <div class="pt-4">
                        <button type="button" id="create-fixtures-button" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light">Create Fixtures</button>
                        <button type="reset" class="btn btn-label-secondary waves-effect">Cancel</button>
                    </div>
                </div>
            </form>








        </div>

    </div>
</div>

<script>
    var venues = <?php echo $venues->toJson(); ?>;
</script>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\_partials\tabs\interpro-draw.blade.php ENDPATH**/ ?>