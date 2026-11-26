<?php
    $configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Admin - Event Page'); ?>

<?php $__env->startSection('vendor-style'); ?>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  

<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
    <script src="<?php echo e(asset('assets/js/manage-category.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

    <style>
        #eligible-player-list {
            min-height: 150px;
            background-color: #f0f4f8;
            border: 2px dashed #bbb;
            border-radius: 6px;
            padding: 1rem;
        }

        .dropzone {
            min-height: 150px;
            padding: 1rem;
            background-color: #f9f9f9;
            border: 2px dashed #ccc;
            border-radius: 6px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .dropzone.border-primary {
            background-color: #eaf4ff;
            border-color: #007bff;
        }

        .dropzone .card.draggable-player {
            cursor: grab;
        }

        .dropzone .card.draggable-player.dragging {
            opacity: 0.6;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }

        .dropzone .card-header {
            font-weight: bold;
        }


        .category-admin :is(.btn,.nav-link,input,select) { min-height:44px !important; }
        .category-admin .card-body { overflow-wrap:anywhere; }
    </style>
    <meta name="app-url" content="<?php echo e(url('/')); ?>">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <div id="action-toast" class="toast position-fixed bottom-0 end-0 m-3" role="alert" data-bs-delay="2000">
        <div class="toast-body bg-success text-white">Toast Message</div>
    </div>

    <!-- Blade: manage.blade.php -->
    <div class="container category-admin">
        <h3>Manage Category: <?php echo e($categoryEvent->category->name); ?></h3><a href="<?php echo e(url()->previous()); ?>" class="btn btn-secondary mb-3">← Back</a>


        <p class="text-muted"><?php echo e($eligibleRegistrations->count()); ?> unassigned registrations · <?php echo e($categoryEvent->draws->count()); ?> draws. Publication is managed separately.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <ul class="nav nav-tabs mb-3" id="categoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo e($errors->any() ? '' : 'active'); ?>" id="players-tab" data-bs-toggle="tab" data-bs-target="#players"
                    type="button" role="tab">Players</button>
            </li>
            <li class="nav-item">
              <a class="nav-link <?php echo e($errors->any() ? 'active' : ''); ?>" id="settings-tab-link" data-bs-toggle="tab" href="#settings-tab" role="tab">Create draw</a>
            </li>

        </ul>

        <div class="tab-content" id="categoryTabsContent">
            <div class="tab-pane fade <?php echo e($errors->any() ? '' : 'show active'); ?>" id="players" role="tabpanel">
                <div class="row">

                    <!-- Left Column: Eligible Players -->
                    <div class="col-md-6">
                        <h5>Eligible Players</h5><p class="small text-muted">Unassigned registrations in this category. Use the existing drag controls on a computer to move players between draws.</p>
                        <!-- Eligible Player List -->
                        <div id="eligible-player-list" class="dropzone border rounded p-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eligibleRegistrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <div class="card mb-2 draggable-player" data-player-id="<?php echo e($reg->id); ?>">
                                    <div class="card-body p-2">
                                        <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?>

                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <p class="text-muted">No unassigned registrations in this category.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <!-- Master template (hidden source of clean clones) -->
                        <div id="master-player-list" class="d-none">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allRegistrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="card draggable-player-template" data-player-id="<?php echo e($reg->id); ?>">
                                    <div class="card-body p-2">
                                        <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?>

                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                    </div>

                    <!-- Right Column: Draws -->
                    <div class="col-md-6">
                        <h5>Draws</h5>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $categoryEvent->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="card mb-3 dropzone" data-draw-id="<?php echo e($draw->id); ?>">
                                <div class="card-header"><?php echo e($draw->drawName); ?>

                                    (<?php echo e($draw->drawFormat->name ?? 'Unknown'); ?>)</div>
                                <div class="card-body">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                        <div class="card mb-2 draggable-player" data-player-id="<?php echo e($reg->id); ?>"
                                            data-draw-id="<?php echo e($draw->id); ?>">
                                            <div class="card-body p-2">
                                                <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?>

                                            </div>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                        <p class="text-muted small">No players assigned yet.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="alert alert-light border">No draws in this category yet.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                </div>
            </div>
            <div class="tab-pane fade <?php echo e($errors->any() ? 'show active' : ''); ?>" id="settings-tab" role="tabpanel" aria-labelledby="settings-tab-link">
              <div class="row mt-3">

                
                <div class="col-md-6">
                  <form id="draw-settings-form" method="POST" action="<?php echo e(route('draws.generate', $categoryEvent)); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="category_event_id" value="<?php echo e($categoryEvent->id); ?>">
                    <p class="small text-muted">Create an unpublished draw in this category. Players, fixtures and publication are managed separately.</p>
                    <div class="mb-3">
                      <label for="drawName" class="form-label">Draw Name</label>
                      <input type="text" class="form-control" id="drawName" name="draw_name" required maxlength="255" value="<?php echo e(old('draw_name')); ?>">
                    </div>
                    <div class="mb-3">
                      <label for="drawType" class="form-label">Draw Type</label>
                      <select class="form-select" id="drawType" name="draw_type" required>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <option value="<?php echo e($drawType->id); ?>" <?php if((string) old('draw_type') === (string) $drawType->id): echo 'selected'; endif; ?>><?php echo e($drawType->drawTypeName); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary" <?php if(!auth()->user()->can('draw.create', $categoryEvent->event)): echo 'disabled'; endif; ?>>Create unpublished draw</button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!auth()->user()->can('draw.create', $categoryEvent->event)): ?><p class="small text-muted mt-2">You do not have permission to create draws for this event.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </form>
                </div>

                
                <div class="col-md-6">
                  <div class="card shadow-sm">
                    <div class="card-header">
                      <strong>Live Preview</strong>
                    </div>
                    <div class="card-body" id="draw-preview">
                      <h5 id="preview-name">Draw Name Preview</h5>
                      <p><strong>Type:</strong> <span id="preview-type">-</span></p>
                    </div>
                  </div>
                </div>

              </div>
            </div>

        </div>
    </div>



<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\categoryEvent\manage.blade.php ENDPATH**/ ?>