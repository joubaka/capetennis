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

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<script src="<?php echo e(asset('assets/js/draw-show.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/app-email.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/ui-toasts.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/extended-ui-drag-and-drop.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/js/menu.js')); ?>"></script>
<?php $__env->stopSection(); ?>



<?php $__env->startSection('content'); ?>

<div class="card-header event-header">
    <h3 class="text-center"> <?php echo e($event->name); ?> </h3>
</div>
<div class="row">

    <div class="col-12 col-sm-3">
        <?php echo $__env->make('backend.adminPage.admin_show.navbar.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
    <div class="col-12 col-sm-9">
        <ul class="nav nav-pills mb-2">
            <li class="nav-item me-2">
                <button id="create-draw-button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exampleModal">Create Draw</button>
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


<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?php echo e(route('draw.store')); ?>" method="post">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Create Draw</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="defaultFormControlInput" class="form-label">Name</label>
                        <input name="name" type="text" class="form-control" id="defaultFormControlInput" placeholder="" aria-describedby="defaultFormControlHelp" />
                    </div>
                    <div class="mb-3">
                        <label for="exampleFormControlSelect1" class="form-label">Number of sets</label>
                        <select name="num_sets" class="form-select">
                            <option value="1" selected>Please select number of sets</option>
                            <option value="1">1</option>

                            <option value="3">3</option>
                            <option value="5">5</option>
                        </select>
                    </div>

                    <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\team\draw-index-team.blade.php ENDPATH**/ ?>