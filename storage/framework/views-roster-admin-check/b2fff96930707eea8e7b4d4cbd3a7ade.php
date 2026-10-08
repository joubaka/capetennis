

<?php $__env->startSection('title', $series->name . ' – Ranking Audit'); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .audit-ok   { color: #28a745; }
  .audit-warn { color: #ffc107; }
  .audit-fail { color: #dc3545; }
  .audit-snapshot-meta { overflow-wrap: anywhere; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="card mb-4">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <h4 class="mb-1">Ranking Audit</h4>
        <div class="text-muted"><?php echo e($series->name); ?> (<?php echo e($series->year); ?>)</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo e(route('ranking.series.list', $series)); ?>" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Back to Ranking List
        </a>
        <a href="<?php echo e(route('series.show', $series)); ?>" class="btn btn-outline-secondary">
          <i class="ti ti-home me-1"></i> Series Home
        </a>
      </div>
    </div>
  </div>

  <div class="alert alert-primary d-flex flex-column flex-md-row justify-content-between gap-2" role="status">
    <div>
      <strong>Active ranking snapshot</strong>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeRunId): ?>
        <span class="badge bg-primary ms-1"><?php echo e(ucfirst($activeStatus)); ?></span>
        <div class="small audit-snapshot-meta mt-1">Run <?php echo e($activeRunId); ?></div>
      <?php else: ?>
        <div class="small mt-1">No calculated, reviewed or published ranking is currently available.</div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($archivedRankingRows > 0): ?>
      <div class="small align-self-md-center">
        <?php echo e($archivedRankingRows); ?> archived <?php echo e(Str::plural('row', $archivedRankingRows)); ?> excluded from the totals below.
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0"><?php echo e($eventSummary->count()); ?></h2>
          <small class="text-muted">Events in Series</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0"><?php echo e($categorySummary->count()); ?></h2>
          <small class="text-muted">Unique Categories (merged)</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0"><?php echo e(count($pointsMap)); ?></h2>
          <small class="text-muted">Points Positions Defined</small>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h2 class="mb-0 <?php echo e($totalRankingRows > 0 ? 'audit-ok' : 'audit-fail'); ?>">
            <?php echo e($totalRankingRows); ?>

          </h2>
          <small class="text-muted">Current Ranking Rows</small>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-list-numbers me-1"></i> Points Map</h5>
    </div>
    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($pointsMap)): ?>
        <div class="alert alert-danger mb-0">
          <i class="ti ti-alert-circle me-1"></i>
          No points defined for this series. Rankings cannot be calculated.
        </div>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-2">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($pointsMap)->sortKeys(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $position => $points): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <span class="badge bg-label-primary">Pos <?php echo e($position); ?>: <?php echo e($points); ?> pts</span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>

  
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-calendar-event me-1"></i> Events & Results</h5>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Results</th>
            <th>Categories with Results</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventSummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $es): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td>
                <a href="<?php echo e(route('admin.events.overview', $es['event'])); ?>" target="_blank">
                  <?php echo e($es['event']->name); ?>

                </a>
              </td>
              <td><?php echo e(optional($es['event']->start_date)->format('d M Y') ?? '—'); ?></td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($es['has_results']): ?>
                  <span class="badge bg-success"><i class="ti ti-check me-1"></i><?php echo e($es['result_rows']); ?> rows</span>
                <?php else: ?>
                  <span class="badge bg-danger"><i class="ti ti-x me-1"></i>No results</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $es['categories']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <span class="badge bg-label-secondary me-1">
                    <?php echo e($cat['name']); ?> (<?php echo e($cat['players']); ?> players)
                  </span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($es['categories']->isEmpty()): ?>
                  <span class="text-muted fst-italic">None</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-trophy me-1"></i> Category Ranking Audit</h5>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Category (merged key)</th>
            <th>Players</th>
            <th>Events</th>
            <th>Positions in Data</th>
            <th>Missing Points Config</th>
            <th>Ranked Players</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categorySummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $rankedCount = isset($rankingsByCategory[$cs['category_id']])
                ? $rankingsByCategory[$cs['category_id']]->count()
                : 0;
              $hasMissing = $cs['missing_points']->isNotEmpty();
            ?>
            <tr>
              <td>
                <strong><?php echo e($cs['category_name']); ?></strong><br>
                <small class="text-muted"><?php echo e($cs['category_key']); ?></small>
              </td>
              <td><?php echo e($cs['player_count']); ?></td>
              <td><?php echo e($cs['events_represented']); ?></td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cs['position_counts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pos => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <span class="badge bg-label-primary me-1">Pos <?php echo e($pos); ?> (×<?php echo e($count); ?>)</span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasMissing): ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cs['missing_points']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="badge bg-danger me-1">Pos <?php echo e($pos); ?></span>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-success">All OK</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankedCount > 0): ?>
                  <span class="badge bg-success"><?php echo e($rankedCount); ?></span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">0 – not ranked yet</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categorySummary->isEmpty()): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-3">No category results found for any event in this series.</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingsByCategory->isNotEmpty()): ?>
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="ti ti-list me-1"></i> Active Rankings Snapshot</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingsByCategory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryId => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-md-6">
              <div class="card border">
                <div class="card-header bg-light py-2">
                  <strong><?php echo e(optional($rows->first()->category)->name ?? 'Category '.$categoryId); ?></strong>
                  <span class="badge bg-secondary float-end"><?php echo e($rows->count()); ?> players</span>
                </div>
                <div class="card-body p-0 table-responsive">
                  <table class="table table-sm mb-0">
                    <thead class="table-light">
                      <tr>
                        <th width="50">#</th>
                        <th>Player</th>
                        <th width="80">Points</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rows->sortBy('rank_position'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                          <td><?php echo e($row->rank_position); ?></td>
                          <td><?php echo e(optional($row->player)->name); ?> <?php echo e(optional($row->player)->surname); ?></td>
                          <td><strong><?php echo e($row->total_points); ?></strong></td>
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
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\ranking\series\audit.blade.php ENDPATH**/ ?>