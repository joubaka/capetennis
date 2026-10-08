<?php
  $configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Admin - Event Page'); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>">
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
  <script src="<?php echo e(asset('assets/js/draw-show-ver3.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/js/ui-toasts.js')); ?>"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>

<div class="card-header event-header">
  <h3 class="text-center">
    <?php echo e($event->name); ?>:
    <div class="badge bg-info"><?php echo e($draw->drawName); ?></div>
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
          <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#draws">Draw</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#options">Settings</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#players">Players</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#fixtures">Fixtures</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">Schedule</button></li>
        </ul>

        <div class="tab-content">

          
          <div class="tab-pane fade show active" id="draws">

            <div class="col-12 mb-3">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->oop_published == 1): ?>
                <button data-id="<?php echo e($draw->id); ?>" id="unpublishOOP" class="btn btn-danger">Unpublish Order of Play</button>
              <?php else: ?>
                <button data-id="<?php echo e($draw->id); ?>" id="publishOOP" class="btn btn-success">Publish Order of Play</button>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              <a href="<?php echo e(route('event.draw.get.pdf',$draw->id)); ?>">Print</a>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php switch($draw->settings->draw_format_id):
              case (1): ?>
                <?php break; ?>

              <?php case (2): ?>
                <?php echo $__env->make('backend.draw._includes.individual_draw', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php break; ?>

              <?php default: ?>
                <div class="alert alert-danger">No draw selected!</div>
            <?php endswitch; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </div>

          
          <div class="tab-pane fade" id="players">
            <div class="demo-inline-spacing mb-3">
              <button type="button" class="btn btn-label-linkedin" data-bs-toggle="modal" data-bs-target="#add-player-modal">
                <i class="ti ti-users-plus me-2"></i> Add Player
              </button>

              <button type="button" class="btn btn-label-github" data-bs-toggle="modal" data-bs-target="#add-category-modal">
                <i class="ti ti-clipboard-data me-2"></i> Copy from Category
              </button>
            </div>

            <div class="card mb-3">
              <div class="card-body">
                <p>Player Ranking</p>
          <ul class="list-group" id="handle-list-1" data-draw-id="<?php echo e($draw->id); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
   <li data-id="<?php echo e($registration->id); ?>" data-seed="<?php echo e($registration->pivot->seed); ?>" class="list-group-item d-flex justify-content-between">
  <span>
    <i class="drag-handle cursor-move ti ti-menu-2 me-2"></i>

    <span class="seed-label text-muted small me-2" data-registration="<?php echo e($registration->id); ?>">
      Seed <?php echo e($registration->pivot->seed); ?>

    </span>

    <?php echo e($registration->players[0]->getFullNameAttribute()); ?>

  </span>
</li>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</ul>

              </div>
            </div>

            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Seed</th><th>Player</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr>
                    <td>
                      <select class="form-select seed-select" data-registration="<?php echo e($registration->id); ?>" data-drawid="<?php echo e($draw->id); ?>">
                        <option value="0">--</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= $draw->registrations->count(); $i++): ?>
                          <option value="<?php echo e($i); ?>" <?php echo e($registration->pivot->seed == $i ? 'selected':''); ?>><?php echo e($i); ?></option>
                        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </select>
                    </td>
                    <td class="fw-medium"><?php echo e($registration->players[0]->getFullNameAttribute()); ?></td>
                    <td>
                      <div class="dropdown">
                        <button class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                        <div class="dropdown-menu">
                          <button type="button" class="dropdown-item remove-from-draw-button"
                                  data-id="<?php echo e($registration->id); ?>" data-drawid="<?php echo e($draw->id); ?>">
                            <i class="ti ti-trash me-1"></i> Remove
                          </button>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>

          
          <div class="tab-pane fade" id="options">
            <div class="col-12">
              <form action="<?php echo e(route('draw.update',$draw->id)); ?>" method="post">
                <?php echo method_field('PUT'); ?>
                <?php echo csrf_field(); ?>

                <div class="mb-3">
                  <label class="form-label">Name</label>
                  <input name="name" type="text" class="form-control" value="<?php echo e($draw->drawName); ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label">Draw Type</label>
                  <select name="draw_type" class="form-select">
                    <option value="1">Please select draw type</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="<?php echo e($drawType->id); ?>" <?php echo e($drawType->id == $draw->settings->draw_type_id ? 'selected':''); ?>>
                        <?php echo e($drawType->drawTypeName); ?>

                      </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Draw Format</label>
                  <select name="draw_format" class="form-select">
                    <option value="1">Please select draw format</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawFormats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <option value="<?php echo e($format->id); ?>" <?php echo e($format->id == $draw->settings->draw_format_id ? 'selected':''); ?>>
                        <?php echo e($format->name); ?>

                      </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Number of sets</label>
                  <select name="num_sets" class="form-select">
                    <option value="0">Please select</option>
                    <option value="1" <?php echo e($draw->settings->num_sets == 1 ? 'selected':''); ?>>1</option>
                    <option value="3" <?php echo e($draw->settings->num_sets == 3 ? 'selected':''); ?>>3</option>
                    <option value="5" <?php echo e($draw->settings->num_sets == 5 ? 'selected':''); ?>>5</option>
                  </select>
                </div>

                <button type="submit" class="btn btn-primary">Update Settings</button>
              </form>
            </div>
          </div>

          
          <div class="tab-pane fade" id="fixtures">
            <div class="card">
              <h5 class="card-header"><?php echo e($draw->drawName); ?></h5>
              <div class="card-body">
                <?php echo $__env->make('bracket.partials.fixtures', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              </div>
            </div>
          </div>

          
          <div class="tab-pane fade" id="schedule">
            <div class="card">
              <h5 class="card-header">Schedule</h5>

              <div class="row">
                <div class="col-6">
                  <div class="card-body">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                      Schedule Settings
                    </button>
                  </div>
                </div>

                <div class="col-6">
                  <ul class="list-group list-group-flush">
                    <li class="list-group-item">Venue: <span id="venue"></span></li>
                    <li class="list-group-item">Number of courts: <span id="numcourts"></span></li>
                    <li class="list-group-item">Match duration: <span id="duration"></span></li>
                    <li class="list-group-item">Start Time: <span id="startTime"></span></li>
                    <li class="list-group-item">Last Match Time: <span id="endTime"></span></li>
                  </ul>
                </div>
              </div>

              <div class="card">
                <div class="card-body">
                  <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#basicModal">
                    Schedule Matches
                  </button>
                </div>
              </div>

            </div>
          </div>


        </div>
      </div>
    </div>

  </div>
</div>

<?php echo $__env->make('backend.draw._modals.schedule-settings-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<div class="modal fade" id="add-player-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo e(route('add.draw.registration',$draw->id)); ?>" method="post">
        <?php echo csrf_field(); ?>

        <div class="modal-header">
          <h5 class="modal-title">Select Players to Add</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <label class="form-label">Players</label>
          <select id="select2Multiple" name="players[]" class="select2 form-select" multiple>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($registration->registration->id); ?>">
                <?php echo e($registration->registration->players[0]->getFullNameAttribute()); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>

          <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save</button>
        </div>

      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="add-category-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo e(route('add.draw.registration.category',$draw->id)); ?>" method="post">
        <?php echo csrf_field(); ?>

        <div class="modal-header">
          <h5 class="modal-title">Select Category to Add</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <select name="category" class="select2 form-select">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event_category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($event_category->id); ?>"><?php echo e($event_category->category->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>

          <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary">Save</button>
        </div>

      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="result-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="submit-score-form">
        <?php echo csrf_field(); ?>

        <div class="modal-header">
          <h5 class="modal-title">Edit Score</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <table class="table table-bordered table-sm">
            <thead>
              <tr class="info"><th>Set:</th>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $draw->settings->num_sets; $i++): ?>
                  <th class="text-center"><?php echo e($i+1); ?></th>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tr>
            </thead>

            <tbody id="scoreBody">
              <tr>
                <td><div id="reg1name"></div></td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $draw->settings->num_sets; $i++): ?>
                  <td><input type="text" name="reg1Set[]" class="score form-control"></td>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tr>

              <tr>
                <td><div id="reg2name"></div></td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 0; $i < $draw->settings->num_sets; $i++): ?>
                  <td><input type="text" name="reg2Set[]" class="score form-control"></td>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tr>
            </tbody>
          </table>
        </div>

        <input type="hidden" name="fixture_id" id="fixture_id_main">

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary" id="submit-result-button">Submit</button>
        </div>

      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="basicModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Courts</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <p><?php echo e($venue->name); ?> - <?php echo e($venue->num_courts); ?> courts</p>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\individual\cavaliersTrials.blade.php ENDPATH**/ ?>