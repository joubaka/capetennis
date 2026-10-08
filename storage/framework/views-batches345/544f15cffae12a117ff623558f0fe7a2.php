<?php
    $configData = Helper::appClasses();
?>





<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
    <script src="<?php echo e(asset('assets/js/manage-draw.js')); ?>"></script>




<?php $__env->stopSection(); ?>


<?php $__env->startSection('title', 'Manage Draw: ' . $draw->drawName); ?>



<?php $__env->startSection('content'); ?>
    <div class="container mt-4">
        <h3 class="mb-4">Manage Draw: <?php echo e($draw->drawName); ?></h3><a href="<?php echo e(url()->previous()); ?>"
            class="btn btn-secondary mb-3">← Back</a>


        <ul class="nav nav-tabs mb-3" id="drawTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings"
                    type="button" role="tab">
                    Settings
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="regs-tab" data-bs-toggle="tab" data-bs-target="#registrations" type="button"
                    role="tab">
                    Registrations
                </button>
            </li>
        </ul>

        <div class="tab-content" id="drawTabsContent">

            <!-- Settings Tab -->
            <div class="tab-pane fade show active" id="settings" role="tabpanel">
                <div class="row">

                    <!-- Editable Settings Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <form id="drawSettingsForm" data-draw-id="<?php echo e($draw->id); ?>">
                                    <?php echo csrf_field(); ?>
                                    <select name="draw_type_id" id="draw_type_id" class="form-select">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($type->id); ?>"
                                                <?php echo e(optional($draw->settings)->draw_type_id == $type->id ? 'selected' : ''); ?>>
                                                <?php echo e($type->drawTypeName); ?>

                                            </option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>

                                    <div class="mb-3">
                                        <label class="form-label">Boxes</label>
                                        <select name="boxes" id="boxes" class="form-select">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = range(1, 16); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($b); ?>"
                                                    <?php echo e(optional($draw->settings)->boxes == $b ? 'selected' : ''); ?>>
                                                    <?php echo e($b); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Playoff Size</label>
                                        <select name="playoff_size" id="playoff_size" class="form-select">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [2, 4, 6, 8, 16]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ps): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($ps); ?>"
                                                    <?php echo e(optional($draw->settings)->playoff_size == $ps ? 'selected' : ''); ?>>
                                                    <?php echo e($ps); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Number of Sets</label>
                                        <select name="num_sets" id="num_sets" class="form-select">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = range(1, 5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $set): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($set); ?>"
                                                    <?php echo e(optional($draw->settings)->num_sets == $set ? 'selected' : ''); ?>>
                                                    <?php echo e($set); ?></option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Draw Format</label>
                                        <select name="draw_format_id" id="draw_format_id" class="form-select">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawFormats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <option value="<?php echo e($format->id); ?>"
                                                    <?php echo e(optional($draw->settings)->draw_format_id == $format->id ? 'selected' : ''); ?>>
                                                    <?php echo e($format->name); ?>

                                                </option>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>

                                    </div>


                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Column -->
                    <div class="col-md-6">
                        <div class="card border border-info">
                            <div class="card-header bg-info text-white">
                                Live Settings Preview
                            </div>
                            <div class="card-body">
                                <ul class="list-group" id="settingsPreview">
                                    <li class="list-group-item">Draw Format:
                                        <strong><?php echo e($draw->drawFormat->name ?? 'N/A'); ?></strong></li>
                                    <li class="list-group-item">Type: <span id="preview_type">
                                            <?php echo e(optional(optional($draw->settings)->drawType)->name ?? (optional($draw->settings)->draw_type_id ?? 'N/A')); ?>


                                        </span></li>
                                    <li class="list-group-item">
                                        Boxes: <span
                                            id="preview_boxes"><?php echo e(optional($draw->settings)->boxes ?? 'N/A'); ?></span>
                                    </li>

                                    <li class="list-group-item">
                                        Playoff Size: <span
                                            id="preview_playoff"><?php echo e(optional($draw->settings)->playoff_size ?? 'N/A'); ?></span>
                                    </li>

                                    <li class="list-group-item">
                                        Sets: <span
                                            id="preview_sets"><?php echo e(optional($draw->settings)->num_sets ?? 'N/A'); ?></span>
                                    </li>

                                    <li class="list-group-item">
                                        Format: <span id="preview_format">
                                            <?php echo e(optional(optional($draw->settings)->drawFormat)->name ?? (optional($draw->settings)->draw_format_id ?? 'N/A')); ?>

                                        </span>

                                    </li>

                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Registrations Tab -->
            <div class="tab-pane fade" id="registrations" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5>Assigned Registrations</h5>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->registrations->count()): ?>
                            <ul class="list-group list-group-flush">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <?php echo e($reg->players[0]->name); ?> <?php echo e($reg->players[0]->surname); ?>

                                        <span class="text-muted small">#<?php echo e($reg->id); ?></span>
                                    </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </ul>
                            <hr>
                            <?php echo $__env->make('backend.draw.formats.monrad', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php else: ?>
                            <p class="text-muted">No players assigned to this draw.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\show2.blade.php ENDPATH**/ ?>