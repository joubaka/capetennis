<div class="card">
    <div class="card-header event-header">
        <h3 class="text-center">Team Event: <?php echo e($event->name); ?> </h3>
    </div>
    <div class="card-body">

    </div>
</div>



<div class="nav-tabs-shadow nav-align-top">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-home" aria-controls="navs-top-home" aria-selected="true">Home</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-draws" aria-controls="navs-top-draws" aria-selected="false">Draws</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-convenors" aria-controls="navs-top-convenors" aria-selected="false">Event Directors</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-setup" aria-controls="navs-top-setup" aria-selected="false">Setup</button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="navs-top-home" role="tabpanel">
            <?php echo $__env->make('backend.headOffice._partials.tabs.home', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="tab-pane fade" id="navs-top-draws" role="tabpanel">
            <?php echo $__env->make('backend.headOffice._partials.tabs.draws', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        </div>
        <div class="tab-pane fade" id="navs-top-convenors" role="tabpanel">
            <?php echo $__env->make('backend.headOffice._partials.tabs.convenors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        </div>
        <div class="tab-pane fade" id="navs-top-setup" role="tabpanel">
            <?php echo $__env->make('backend.headOffice._partials.tabs.setup', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        </div>
    </div>
    </div>
</div><?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\_partials\dashboard-home.blade.php ENDPATH**/ ?>