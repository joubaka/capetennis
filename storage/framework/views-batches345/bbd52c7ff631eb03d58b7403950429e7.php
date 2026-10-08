   
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js" integrity="sha512-EmfT33UCuNEdtd9zuhgQClh7gidfPpkp93WO8GEfAP3cLD++UM1AG9jsTUitCI9DH5nF72XaFePME92r767dHA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
    <h3 class="text-center"> <?php echo e($event->name); ?>: <div class="badge bg-info"><?php echo e($draw->drawName); ?></div>
    </h3>
</div>
<div class="row">

    <div class="col-12 col-md-3">
        <?php echo $__env->make('backend.adminPage.admin_show.navbar.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
    <div class="col-12 col-md-9">

        <div class="col-12 col-md-12">
            <div class="nav-align-top">
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#draws" aria-controls="draws" aria-selected="true">Draw</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#options" aria-controls="fixtures" aria-selected="false">Settings</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link " role="tab" data-bs-toggle="tab" data-bs-target="#players" aria-controls="players" aria-selected="false">Players</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#fixtures" aria-controls="fixtures" aria-selected="false">Fixtures</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#schedule" aria-controls="schedule" aria-selected="false">Schedule</button>
                    </li>


                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade" id="players" role="tabpanel">
                        <div class="demo-inline-spacing">
                            <button type="button" data-bs-target="#add-player-modal" data-bs-toggle="modal" class="btn btn-label-linkedin waves-effect">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-users-plus me-2" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M5 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"></path>
                                    <path d="M3 21v-2a4 4 0 0 1 4 -4h4c.96 0 1.84 .338 2.53 .901"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    <path d="M16 19h6"></path>
                                    <path d="M19 16v6"></path>
                                </svg>
                                Add Player</button>
                            <button type="button" data-bs-target="#add-category-modal" data-bs-toggle="modal" class="btn btn-label-github waves-effect "><svg xmlns="http://www.w3.org/2000/svg" class="me-2 icon icon-tabler icon-tabler-clipboard-data" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2"></path>
                                    <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z"></path>
                                    <path d="M9 17v-4"></path>
                                    <path d="M12 17v-1"></path>
                                    <path d="M15 17v-2"></path>
                                    <path d="M12 17v-1"></path>
                                </svg> Copy from Category</button>
                        </div>
                        <div class="card">
                            <div class="card-header"></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 col-12 mb-md-0 mb-4">
                                        <p>Player Ranking</p>
                                        <ul class="list-group list-group-flush" id="handle-list-1">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <li data-id="<?php echo e($registration->id); ?>" class="list-group-item d-flex justify-content-between align-items-center">
                                                <span class="d-flex justify-content-between align-items-center">
                                                    <i class="drag-handle cursor-move ti ti-menu-2 align-text-bottom me-2"></i>
                                                    <span><?php echo e($registration->players[0]->getFullNameAttribute()); ?></span>
                                                </span>

                                            </li>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </ul>

                                    </div>


                                </div>
                            </div>


                        </div>
                  
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Seed</th>
                                        <th>Player</th>

                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                    <tr>
                                        <td>
                                            <span class="mb-3">

                                                <select class="form-select seed-select" data-drawid="<?php echo e($draw->id); ?>" data-registration="<?php echo e($registration->id); ?>" aria-label="Default select example">

                                                    <option value=0 selected>--</option>
                                                    <?php echo e($players = $draw->registrations->count()); ?>




                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= $players; $i++): ?> <option <?php echo e($registration->pivot->seed == $i ? 'selected':''); ?> value="<?php echo e($i); ?>"><?php echo e($i); ?></option>
                                                        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </select>
                                            </span>
                                        </td>
                                        <td> <span class="fw-medium"><?php echo e($registration->players[0]->getFullNameAttribute()); ?></span></td>


                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                                <div class="dropdown-menu">

                                                    <button type="button" data-id="<?php echo e($registration->id); ?>" data-drawid="<?php echo e($draw->id); ?>" class="dropdown-item remove-from-draw-button"><i class="ti ti-trash me-1"></i>Remove from draw</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        
                    </div>
                   
                    <div class="tab-pane fade" id="options" role="tabpanel">
                        <div class="col-12">
                            <form action="<?php echo e(route('draw.update',$draw->id)); ?>" method="post">
                                <?php echo method_field('PUT'); ?>
                                <?php echo csrf_field(); ?>


                                <div class="mb-3">
                                    <label for="defaultFormControlInput" class="form-label">Name</label>
                                    <input name="name" type="text" class="form-control" id="defaultFormControlInput" placeholder="" value="<?php echo e($draw->drawName); ?>" aria-describedby="defaultFormControlHelp" />
                                </div>
                                <div class="mb-3">
                                    <label for="exampleFormControlSelect1" class="form-label">Draw Type</label>

                                    <select name="draw_type" class="form-select">

                                        <option value="1" selected>Please draw Type</option>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                        <option <?php echo e($drawType->id == $draw->settings->draw_type_id ? 'selected':''); ?> value="<?php echo e($drawType->id); ?>"><?php echo e($drawType->drawTypeName); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="exampleFormControlSelect1" class="form-label">Draw Format</label>

                                    <select name="draw_format" class="form-select">

                                        <option value="1" selected>Please select draw format</option>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawFormats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                        <option <?php echo e($format->id == $draw->settings->draw_format_id ? 'selected':''); ?> value="<?php echo e($format->id); ?>"><?php echo e($format->name); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="exampleFormControlSelect1" class="form-label">Number of sets</label>
                                    <select name="num_sets" class="form-select">
                                        <option value="0" selected>Please select number of sets</option>
                                        <option <?php echo e($draw->settings->num_sets == 1 ? 'selected':''); ?> value="1">1</option>

                                        <option <?php echo e($draw->settings->num_sets == 3 ? 'selected':''); ?> value="3">3</option>
                                        <option <?php echo e($draw->settings->num_sets == 5 ? 'selected':''); ?> value="5">5</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn rounded-pill btn-label-primary waves-effect">Update Settings</button>

                            </form>

                        </div>

                    </div>
                    <div class="tab-pane fade show active" id="draws" role="tabpanel">


                        <div class="col-12">
                            <div class="mb-3">


                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->oop_published == 1): ?>
                                <button data-id="<?php echo e($draw->id); ?>" type="button" id="unpublishOOP" class="btn btn-danger">Unpublish Order of Play</button>
                                <?php else: ?>
                                <button data-id="<?php echo e($draw->id); ?>" type="button" id="publishOOP" class="btn btn-success">Publish Order of Play</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <a href="<?php echo e(route('event.draw.get.pdf',$draw->id)); ?>"> Print</a>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($draw->settings->draw_format_id):
                            case (1): ?>
                            <?php break; ?>

                            <?php case (2): ?>


                            <?php echo $__env->make('backend.draw._includes.individual_draw', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                            <?php break; ?>

                            <?php default: ?>
                            <div class="alert alert-danger" role="alert">
                                No draw selected!
                            </div>
                            <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>









                        </div>
                    </div>
                    <div class="tab-pane fade" id="fixtures" role="tabpanel">
                        <div class="col-12">
                            <div class="card">
                                <h5 class="card-header"> <?php echo e($draw->drawName); ?></h5>
                                <div class="table-responsive text-nowrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th width='5%'>Fixture Id</th>

                                                <th width="25%">Match #</th>
                                                <th width="25%">Registration 1</th>
                                                <th></th>
                                                <th width='25%'>Registration 2</th>
                                                <th>Result</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <td></td>


                                        <tbody class="table-border-bottom-0">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $bracket->fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr id="<?php echo e($fixture->id); ?>">
                                                <td> <span class="fw-medium"><?php echo e($fixture->id); ?></span></td>

                                                <td><?php echo e($fixture->match_nr); ?></td>

                                                <td class="bg-label-<?php echo e($bracket->getWinnerRegistration($fixture->id,$fixture->registration1_id)); ?>">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration1_id > 0): ?>
                                                    <?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fixture->registrations1->players,'context' => $draw]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fixture->registrations1->players),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
                                                    <?php elseif(is_null($fixture->registration1_id)): ?>

                                                    <?php else: ?>
                                                    BYE
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-label-primary me-1">vs</span></td>
                                                <td class="bg-label-<?php echo e($bracket->getWinnerRegistration($fixture->id,$fixture->registration2_id)); ?>">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration2_id > 0): ?>
                                                    <?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $fixture->registrations2->players,'context' => $draw]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fixture->registrations2->players),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
                                                    <?php elseif(is_null($fixture->registration2_id)): ?>

                                                    <?php else: ?>
                                                    BYE
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </td>
                                                <td class="resultTd"><?php echo e($bracket->result($fixture->id)); ?></td>

                                                <td>
                                                    <div class="dropdown">
                                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                                        <div class="dropdown-menu">
                                                            <a data-id="<?php echo e($fixture->id); ?>" data-reg2="<?php echo e(isset($fixture->registrations2) ? $fixture->registrations2->players[0]->getFullNameAttribute():''); ?>" data-reg1="<?php echo e(isset($fixture->registrations1) ? $fixture->registrations1->players[0]->getFullNameAttribute():''); ?>" data-result="<?php echo e($fixture->fixtureResults); ?>" class="dropdown-item editResult" data-bs-target="#result-modal" data-bs-toggle="modal" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i> Edit</a>
                                                            <a data-id="<?php echo e($fixture->id); ?>" class="dropdown-item" href="javascript:void(0);"><i class="ti ti-trash me-1"></i> Delete</a>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>






                        </div>

                    </div>
                    <div class="tab-pane fade" id="schedule" role="tabpanel">
                        <div class="col-12">
                            <div class="card">
                                <h5 class="card-header"> Schedule</h5>

                                <div class="row">
                                    <div class="col-6">
                                        <div class="card-body">
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                                Schedule Settings
                                            </button>

                                        </div>
                                    </div>
                                    <div class="col-6">

                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">Venue<span> <input id="venue" type="hidden" name="venueId"></span></li>
                                            <li class="list-group-item">Number of courts:<span id="numcourts"></span></li>

                                            <li class="list-group-item">Match duration <span id="duration"></span></li>
                                            <li class="list-group-item">Start Time<span id="startTime"></span></li>
                                            <li class="list-group-item">Last Match Time:<span id="endTime"></span></li>
                                            <input type="hidden" name="venueId">
                                        </ul>

                                    </div>
                                </div>
                                <!-- Button trigger modal -->






                            </div>






                        </div>

                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<?php echo $__env->make('backend.draw._modals.schedule-settings-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<!-- Modal -->
<div class="modal fade" id="add-player-modal" tabindex="-1" aria-labelledby="add-player-modalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?php echo e(route('add.draw.registration',$draw->id)); ?>" method="post">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="add-player-modalLabel">Select Players to add</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="select2Multiple" class="form-label">Please select Players</label>
                        <select id="select2Multiple" name="players[]" class="select2 form-select" multiple>
                            <option></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($registration->registration->id); ?>"><?php echo e($registration->registration->players[0]->getFullNameAttribute()); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


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

<!-- Modal -->
<div class="modal fade" id="add-category-modal" tabindex="-1" aria-labelledby="add-category-modalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?php echo e(route('add.draw.registration.category',$draw->id)); ?>" method="post">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="add-category-modalLabel">Select Category to add to draw</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="select2Category" class="form-label">Please select category to copy</label>
                        <select name="category" class="select2 form-select">
                            <option></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event_category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($event_category->id); ?>"><?php echo e($event_category->category->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


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



<!-- Modal -->
<div class="modal fade" id="result-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="#" id="submit-result-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Edit Score</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <table class="table table-bordered table-sm">
                        <thead class=" thead-colored thead-light">
                            <tr class="info" style="border-top: 2px solid #000 !important;">
                                <th class="text-right">Set:</th>



                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0 ; $i < $draw->settings->num_sets; $i++): ?>
                                    <th class="text-center "><?php echo e(($i+1)); ?></th>


                                    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tr>
                        </thead>

                        <tbody id="scoreBody">
                            <tr>
                                <td>
                                    <div id="reg1name"></div>
                                </td>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0 ; $i < $draw->settings->num_sets; $i++): ?>

                                    <td><input type="text" name="reg1Set[]" id="reg1ScoreSet<?php echo e($i+1); ?>" class="score form-control"></td>

                                    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tr>
                            <tr>
                                <td>
                                    <div id="reg2name"></div>
                                </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0 ; $i < $draw->settings->num_sets; $i++): ?>

                                    <td><input type="text" name="reg2Set[]" id="reg2ScoreSet<?php echo e($i+1); ?>" class="score form-control"></td>

                                    <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tr>
                        </tbody>
                    </table>


                </div>
                <input type="hidden" name="fixture_id" id="fixture_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="submit-result-button" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php $__env->stopSection(); ?>








<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\individual\draw-show-individual.blade.php ENDPATH**/ ?>