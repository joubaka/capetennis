

<?php $__env->startSection('title', $series->name . ' – Points Allocation'); ?>

<?php $__env->startSection('page-style'); ?>
  
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .point-input {
      max-width: 160px;
    }
  </style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Points Allocation</h4>
        <div class="text-muted"><?php echo e($series->name); ?></div>
      </div>

      <button id="save-points" class="btn btn-success">
        <i class="ti ti-device-floppy me-1"></i>
        Save Points
      </button>
    </div>
  </div>

  
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th style="width:120px;">Position</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><strong>#<?php echo e($row['position']); ?></strong></td>
              <td>
                <input type="number"
                       class="form-control point-input"
                       data-position="<?php echo e($row['position']); ?>"
                       value="<?php echo e($row['score']); ?>"
                       min="0">
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    // ------------------------------
    // Toastr defaults
    // ------------------------------
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: 'toast-top-right',
      timeOut: 3000
    };

    // ------------------------------
    // Save points
    // ------------------------------
    document.getElementById('save-points').addEventListener('click', () => {
      const points = [];

      document.querySelectorAll('.point-input').forEach(input => {
        points.push({
          position: parseInt(input.dataset.position),
          score: parseInt(input.value || 0)
        });
      });

      fetch('<?php echo e(route('ranking.points.update', $series)); ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
        },
        body: JSON.stringify({ points })
      })
      .then(res => {
        if (!res.ok) throw new Error('Request failed');
        return res.json();
      })
      .then(res => {
        toastr.success(res.message || 'Points saved successfully');
      })
      .catch(err => {
        console.error(err);
        toastr.error('Failed to save points');
      });
    });
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\ranking\points.blade.php ENDPATH**/ ?>