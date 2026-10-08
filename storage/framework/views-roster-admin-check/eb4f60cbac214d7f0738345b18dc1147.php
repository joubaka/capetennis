<?php $__env->startSection('title', 'User Management - Crud App'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
<?php $__env->stopSection(); ?>




<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('js/laravel-user-management.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/rank-show.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Players</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-success">to do</small>
                        </div>
                        <small>Total Registrations: <?php echo e($registrations); ?> </small>
                    </div>
                    <span class="badge bg-label-success rounded p-2">
                        <i class="ti ti-user-check ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">


            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Total Events</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-info"><?php echo e($events->count()); ?></small>
                        </div>
                        <small>Total Events in series</small>
                    </div>
                    <span class="badge bg-label-info rounded p-2">
                        <i class="ti ti-user ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Upcoming Events</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-secondary"><?php echo e($upcoming_events > 0 ? $upcoming_events:'0'); ?></small>
                        </div>
                        <small>Upcoming events</small>
                    </div>
                    <span class="badge bg-label-secondary rounded p-2">
                        <i class="ti ti-users ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="content-left">
                        <span>Completed events</span>
                        <div class="d-flex align-items-end mt-2">
                            <h3 class="mb-0 me-2"></h3>
                            <small class="badge bg-label-warning"><?php echo e($completed_events); ?></small>
                        </div>
                        <small>Completed events</small>
                    </div>
                    <span class="badge bg-label-warning rounded p-2">
                        <i class="ti ti-user-circle ti-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Users List Table -->



<div class="col-12">
    <div class="card mb-4">
        <div class="card-header"> Rankings</div>
        <div class="card-body">
            <div class="row">
                <div class="col-6">
                    <div class="row">




                        <div class="col-12">



                            <div class="list-group" id="cats">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                <a href="javascript:void(0);" data-eventcategory="<?php echo e($category->id); ?>" class="list-group-item list-group-item-action"><?php echo e($category->event->name); ?> - <?php echo e($category->category->name); ?></a>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            </div>
                        </div>


                    </div>
                </div>
                <div class="col-6">

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->ranking_lists->count() > 0): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series->ranking_lists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $rl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>


                    <div class="card">
                        <div class="card-header bg-label-primary">
                            <small class=""><?php echo e($rl->category->name); ?> - <?php echo e($rl->category->id); ?></small>

                        </div>
                        <div class="demo-inline-spacing mt-3">
                            <div class="list-group sortable" id="test-<?php echo e($key); ?>" data-ranklist="<?php echo e($rl->id); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rl->rank_cats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rankCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <a href="javascript:void(0);" data-eventcategory="<?php echo e($rankCategory->category_event_id); ?>" class="list-group-item list-group-item-action"><?php echo e($rankCategory->eventCategory->event->name); ?> - <?php echo e($rankCategory->eventCategory->category->name); ?> </a>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                            </div>
                        </div>
                    </div>

                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                    <div class="col-12 col-md-3">
                        <div class="badg bg-label-danger">No Ranking list created</div>
                    </div>
                    <div class="btn btn-primary btn-sm mt-2" data-id="<?php echo e($series->id); ?>" id="addRankList">Create lists</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </div>

            </div>


        </div>
    </div>
</div>
<div class="col-5">
    <div class="card mb-4">
        <div class="card-header"> Setup</div>
        <div class="card-body">
            <h5>Calculate</h5>


            <p> <a href="<?php echo e(route('ranking.calculate',$series->id)); ?>" class="calculate btn btn-info" data-id="<?php echo e($series->id); ?>">calculate - <?php echo e($series->id); ?></a></p>

        </div>
    </div>
</div>
<div class="col-12">
    <div class="card mb-4">
        <div class="card-header">
            <h5>Rankings</h5>
        </div>
        <div class="row">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series->ranking_lists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ranking_list): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class=" col-sm-12 col-lg-6">
                <div class="card">
                    <div class="card-body">




                        <h5><?php echo e($ranking_list->category->name); ?> <?php echo e($ranking_list->category->id); ?> </h5>
                        <table class="table table-responsive ">
                            <thead>
                                <th>Rank</th>
                                <th>Name</th>
                                <th># of events</th>
                                <th>Points</th>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ranking_list->ranking_scores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $scores): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><?php echo e($key+1); ?></td>
                                    <td> <a class="badge bg-label-primary" href="<?php echo e(route('result.details', ['id' => $scores->player->id, 'series' => $series->id])); ?>"> <?php echo e($scores->player->name); ?> <?php echo e($scores->player->surname); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $scores->player->id,'context' => $ranking_list->category]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($scores->player->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ranking_list->category)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scores->primarySchool == 1): ?><span class=" badge bg-label-warning"><?php echo e($scores->primarySchool == 1 ? ' (u/13) ':''); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a> <?php echo e($scores->player->id); ?></td>
                                    <td> <?php echo e($scores->num_events); ?></td>
                                    <td> <?php echo e($scores->total_points); ?></td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>



                    </div>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\ranking\show_ranking.blade.php ENDPATH**/ ?>