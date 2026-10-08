<?php
$configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Admin - Event Page'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/quill/katex.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/quill/quill.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>


<script src="<?php echo e(asset('assets/js/venues.js')); ?>"></script>

<?php $__env->stopSection(); ?>



<?php $__env->startSection('content'); ?>

<div class="card-header event-header">
    <h3 class="text-center">Team Event: <?php echo e($event->name); ?> </h3>
</div>
<div class="row">

    <div class="col-12 col-sm-3 col-md-3">
        <?php echo $__env->make('backend.adminPage.admin_show.navbar.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <div class="col-12 col-sm-9 col-md-9">

        <div class="card">
            <div class="row">


                <div class="col-9 col-md-9">
                <form id="venueForm" action="<?php echo e(route('save.draw.venues')); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <!-- Multiple Select Dropdown -->
                    <div class="mb-3 m-3">
                        <label for="venues" class="form-label">Select Venues</label>
                        <select id="venues" name="venues[]" class="form-control" multiple="multiple" style="width: 100%;">

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($venue->id); ?>"  <?php echo e(in_array($venue->id, $selectedVenues) ? 'selected':''); ?> ><?php echo e($venue->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <!-- Add more options as needed -->
                        </select>
                        <input type="hidden" name="draw" value="<?php echo e($draw->id); ?>">
                    </div>

                    <button type="submit" id="apply-venue-button" class="m-4 btn btn-primary waves-effect waves-light">Apply venues</button>
                </form>








                </div>
            </div>


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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($drawType->id); ?>"><?php echo e($drawType->drawTypeName); ?></option>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<div class="modal fade" id="basicModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel1">Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div>
                    <label for="smallSelect" class="form-label">Schedule</label>
                    <select id="schedule-type" class="form-select form-select-sm">
                        <option>Small select</option>
                        <option value="1">Per Round</option>
                        <option value="2">Per Tie</option>
                        <option value="3">Per Team Rank</option>
                        <option value="4">Per Time Slot</option>
                    </select>
                </div>
                <div id="schedule-times">
                    <form id="schedule-form">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="schedule">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Match</th>
                                        <th>Starting Time</th>
                                        <th>Venue</th>

                                    </tr>
                                </thead>
                                <tbody>






                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
                <div id="timeSlot">
                    timeslots
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="apply-time-button" class="btn btn-primary">Apply times</button>
            </div>
        </div>

    </div>
</div>
<script>
    var venues = <?php echo $venues->toJson(); ?>;

</script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\venue\venue-show.blade.php ENDPATH**/ ?>