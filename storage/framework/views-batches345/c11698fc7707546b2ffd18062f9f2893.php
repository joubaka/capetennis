

<?php $__env->startSection('title', 'Team Schedule – ' . $draw->drawName); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"
        referrerpolicy="no-referrer" />
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"
          referrerpolicy="no-referrer"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Team Schedule — <?php echo e($draw->drawName); ?></h4>
    <a href="<?php echo e(route('event.tab.draws', $event->id)); ?>" class="btn btn-secondary btn-sm">Back</a>
  </div>

  
  <div class="card mb-3">
    <div class="card-body">
      <form id="autoForm" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Start</label>
          <input type="text" id="start" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>
        <div class="col-md-3">
          <label class="form-label">End</label>
          <input type="text" id="end" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Duration (min)</label>
          <input type="number" id="duration" class="form-control" value="90" min="20" step="5">
        </div>
        <div class="col-md-2">
          <label class="form-label">Gap (min)</label>
          <input type="number" id="gap" class="form-control" value="0" min="0" step="5">
        </div>
        <div class="col-md-2">
          <label class="form-label">Round(s)</label>
          <input type="text" id="round" class="form-control" placeholder="e.g. 1, 2">
        </div>
        <div class="col-12">
          <label class="form-label">Venues (limit to these)</label>
          <select id="venues" class="form-select" multiple></select>
        </div>
        <div class="col-12 d-flex gap-2 mt-2">
          <button type="button" id="btnAuto" class="btn btn-primary">Auto-Schedule</button>
          <button type="button" id="btnReload" class="btn btn-outline-secondary">Reload</button>
          <button type="button" id="btn-clear-schedule" class="btn btn-outline-danger btn-sm">
            <i class="ti ti-trash"></i> Clear All
          </button>
          <button type="button" id="btn-reset-schedule" class="btn btn-outline-warning btn-sm">
            <i class="ti ti-refresh"></i> Reset Auto Schedule
          </button>
        </div>
      </form>
    </div>
  </div>

  
  <div class="card mt-3">
    <div class="card-header">
      <h5 class="mb-0">Rank → Venue Map</h5>
      <small class="text-muted">Assign home rank numbers to venues. Matches play in rank order at the mapped venue.</small>
    </div>
    <div class="card-body">
      <form id="rankVenueForm" class="row g-2 align-items-end">
        <div class="col-md-12">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:120px;">Home Rank #</th>
                <th>Venue</th>
                <th style="width:100px;">Duration</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="rankVenueRows">
              
            </tbody>
          </table>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRankVenue">
            <i class="ti ti-plus"></i> Add Mapping
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAutoMapRanks">
            <i class="ti ti-wand"></i> Auto-Map Ranks
          </button>
        </div>
      </form>
    </div>
  </div>

  
  <div class="card mt-3">
    <div class="card-body">
      <table id="scheduleTable" class="table table-sm table-striped table-bordered align-middle w-100">
        <thead class="table-light">
          <tr class="text-center">
            <th>#</th>
            <th>Round</th>
            <th>Tie</th>
            <th>Rank</th>
            <th>Home vs Away</th>
            <th style="min-width:180px;">Date/Time</th>
            <th>Venue</th>
            <th>Court</th>
            <th>Dur</th>
            <th>Status</th>
            <th style="min-width:120px;">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
    window.scheduleConfig = {
        drawId: <?php echo e($draw->id); ?>,
        routes: {
            data: "<?php echo e(route('backend.team-schedule.data', $draw->id)); ?>",
            save: "<?php echo e(route('backend.team-schedule.save', $draw->id)); ?>",
            auto: "<?php echo e(route('backend.team-schedule.auto', $draw->id)); ?>",
            clear: "<?php echo e(route('backend.draw.schedule.clear', $draw->id)); ?>",
            reset: "<?php echo e(route('backend.draw.schedule.reset', $draw->id)); ?>"
        }
    };
</script>



<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
 <script>
    window.teamScheduleDataUrl = "<?php echo e(route('backend.team-schedule.data', $draw->id)); ?>";
</script>

<script src="<?php echo e(asset(mix('js/team-schedule.js'))); ?>"></script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-schedule\schedule.blade.php ENDPATH**/ ?>