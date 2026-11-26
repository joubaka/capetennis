      
      <div class="card mb-4 shadow-sm <?php echo e($documentCardClass ?? ''); ?>">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <h6 class="card-title text-uppercase mb-0">
            <i class="ti ti-folder text-primary me-2"></i> <?php echo e($documentTitle ?? 'Documents'); ?>

          </h6>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! ($documentsReadOnly ?? false) && auth()->user()?->hasAnyRole(['super-user', 'admin'])): ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($documentUploadModal)): ?>
              <button type="button" class="btn btn-sm btn-outline-primary" style="min-height:44px" data-bs-toggle="modal" data-bs-target="<?php echo e($documentUploadModal); ?>">Upload</button>
            <?php else: ?>
            <form action="<?php echo e(route('file.store')); ?>" method="POST" enctype="multipart/form-data"
                  class="d-flex align-items-center gap-2 mb-0">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">

              <label class="btn btn-sm btn-outline-primary mb-0" style="min-height:44px">
                <i class="ti ti-upload me-1"></i> Upload
                <input type="file" name="myFile" class="visually-hidden" aria-label="Upload document"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                       onchange="this.form.submit()">
              </label>

            </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

        <div class="card-body pb-2">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($documentDescription)): ?>
            <p class="text-muted small"><?php echo e($documentDescription); ?></p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="file-item border-bottom py-2 d-flex justify-content-between align-items-center">

              <div class="d-flex align-items-center flex-grow-1" style="min-width: 0">
                <i class="ti ti-file-description text-primary fs-5 me-2"></i>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($file->isAvailable()): ?>
                <a href="<?php echo e(route('events.documents.show', [$event, $file])); ?>" <?php if($documentNewWindow ?? true): ?> target="_blank" rel="noopener" <?php endif; ?>
                   class="fw-semibold text-dark text-decoration-none text-break">
                  <?php echo e($file->name); ?>

                </a>
                <?php else: ?>
                <span class="fw-semibold text-break"><?php echo e($file->name); ?> <span class="d-block small text-muted">Document unavailable</span></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

              <div class="d-flex align-items-center">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! ($documentsReadOnly ?? false) && auth()->user()?->hasAnyRole(['super-user', 'admin'])): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
                  <button type="button" data-id="<?php echo e($file->id); ?>"
                          class="btn btn-sm btn-icon btn-outline-danger deleteFileButton"
                          style="min-width:44px;min-height:44px"
                          data-bs-toggle="tooltip" title="Delete" aria-label="Delete <?php echo e($file->name); ?>">
                    <i class="ti ti-trash"></i>
                  </button>
                <?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="text-muted"><?php echo e($documentEmptyMessage ?? 'No documents uploaded yet.'); ?></div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

      </div>


<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\event-documents.blade.php ENDPATH**/ ?>