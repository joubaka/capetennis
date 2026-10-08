

<?php $__env->startSection('title', "Order of Play – {$venue->name} – {$date}"); ?>

<?php $__env->startSection('content'); ?>
<style>
  #order {
    margin-bottom: 150px;
  }

  .day-heading {
    background-color: #f5f5f5;
    font-weight: bold;
    text-transform: uppercase;
  }

  .view-toggle .btn {
    text-transform: uppercase;
    font-weight: 600;
  }

  .view-toggle .btn.active {
    pointer-events: none;
  }

  .table td, .table th {
    vertical-align: middle !important;
  }

  .table td.fw-bold {
    font-size: 1rem;
    letter-spacing: 0.5px;
  }

 
  @media print {
    @page {
      size: A4 landscape;
      margin: 10mm;
    }

    body {
      font-size: 11px !important;
      color: #000;
    }

    table {
      width: 100%;
      border-collapse: collapse !important;
    }

    th, td {
      padding: 2px 4px !important;
      white-space: normal !important;
      overflow-wrap: anywhere;
      font-size: 10px !important;
      line-height: 1.3;
    }

    th {
      background: #000 !important;
      color: #fff !important;
      -webkit-print-color-adjust: exact;
    }

    /* Hide buttons for printing */
    .btn, .view-toggle, .mt-4, .text-end, .order-filter {
      display: none !important;
    }

    .table td, .table th {
      border: 1px solid #888 !important;
    }

    /* Smaller badges */
    .badge {
      font-size: 9px !important;
      padding: 2px 4px !important;
    }

    /* Compact day headings */
    .day-heading td {
      background: #f0f0f0 !important;
      font-weight: bold;
      font-size: 11px !important;
      text-transform: uppercase;
      text-align: center;
      -webkit-print-color-adjust: exact;
    }

    /* Wider result column for handwriting */
    td:last-child {
      min-width: 140px !important;
      text-align: left !important;
      border-bottom: 1px dotted #bbb !important;
    }
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
    #order { margin-bottom: 0; }
    .table-responsive { overflow: visible; }
    #order h3 { font-size: 14px; }
  }
</style>

<?php
  // 🔗 Build base URL for toggle buttons
  $baseRoute = url("event/{$event->id}/venue/{$venue->id}/order");

?>



<div class="container" id="order">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
      <h3 class="mb-1">Order of Play</h3>
      <strong><?php echo e($event->name); ?></strong><br>
      <strong class="venue-title"><?php echo e($venue->name); ?></strong>
    </div>

    
    <div class="btn-group view-toggle" role="group">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableDates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableDate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e($baseRoute); ?>/<?php echo e($availableDate); ?><?php echo e($selectedDrawId ? '?draw_id='.$selectedDrawId : ''); ?>"
           class="btn btn-outline-primary <?php echo e($date === $availableDate ? 'active' : ''); ?>">
          <?php echo e(\Carbon\Carbon::parse($availableDate)->format('D j M')); ?>

        </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <a href="<?php echo e($baseRoute); ?>/all<?php echo e($selectedDrawId ? '?draw_id='.$selectedDrawId : ''); ?>"
         class="btn btn-outline-dark <?php echo e(strtolower($date) === 'all' ? 'active' : ''); ?>">All Days</a>
    </div>
  </div>

  <form method="get" class="order-filter mb-3">
    <label for="order-draw" class="form-label">Age group / draw</label>
    <div class="d-flex gap-2">
      <select name="draw_id" id="order-draw" class="form-select">
        <option value="">All draws at this venue</option>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableDraw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($availableDraw->id); ?>" <?php if((int) $selectedDrawId === (int) $availableDraw->id): echo 'selected'; endif; ?>><?php echo e($availableDraw->drawName); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <button type="submit" class="btn btn-outline-primary">Apply</button>
    </div>
  </form>

  
  <div class="mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(strtolower($date) === 'all'): ?>
      <h5 class="text-muted">Showing all published fixtures</h5>
    <?php else: ?>
      <h5 class="text-muted">
        <?php echo e(\Carbon\Carbon::parse($date)->format('l, d M Y')); ?>

      </h5>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <div class="table-responsive"><table class="table table-bordered align-middle">
    <thead class="table-dark">
      <tr><th colspan="5"><?php echo e($venue->name); ?> · <?php echo e(strtolower($date) === 'all' ? 'All published days' : \Carbon\Carbon::parse($date)->format('l, d M Y')); ?></th></tr>
      <tr>
        <th style="width: 6%">Time</th>
        <th style="width: 16%">Draw / Match</th>
        <th style="width: 25%">Home</th>
        <th style="width: 25%">Away</th>
        <th style="width: 20%">Result</th>
      </tr>
    </thead>

    <tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(strtolower($date) === 'all'): ?>
        
        <?php
          $grouped = $fixtures->groupBy(function ($fx) {
              return \Carbon\Carbon::parse($fx->scheduled_at)->format('l, d M Y');
          });
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $grouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day => $dayFixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr class="day-heading text-center">
            <td colspan="5"><?php echo e($venue->name); ?> · <?php echo e(strtoupper($day)); ?></td>
          </tr>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $dayFixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><?php echo e(\Carbon\Carbon::parse($fx->scheduled_at)->format('H:i')); ?></td>
              <td><?php echo e($fx->draw->drawName); ?><br><small>M<?php echo e($fx->match_nr ?? $fx->id); ?></small></td>
              <td>
                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              </td>
              <td>
                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              </td>
              <td class="fw-bold text-center"><?php echo e($fx->result ?: '____  ____  ____'); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php else: ?>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><?php echo e(\Carbon\Carbon::parse($fx->scheduled_at)->format('H:i')); ?></td>
            <td><?php echo e($fx->draw->drawName); ?><br><small>M<?php echo e($fx->match_nr ?? $fx->id); ?></small></td>
            <td>
              <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </td>
            <td>
              <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </td>
            <td class="fw-bold text-center"><?php echo e($fx->result ?: '____  ____  ____'); ?></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr>
            <td colspan="5" class="text-center text-muted">No matches scheduled for this day.</td>
          </tr>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody>
  </table></div>

  <div class="mt-4 text-end">
    <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixture\orderOfPlay.blade.php ENDPATH**/ ?>