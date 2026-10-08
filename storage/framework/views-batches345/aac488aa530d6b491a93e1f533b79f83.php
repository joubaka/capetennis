

<?php $__env->startSection('title', 'Scoreboard: ' . $event->name); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<style>
  .scoreboard-layout {
    display: grid;
    grid-template-columns: 2fr 0.7fr;
    gap: 1.5rem;
  }
  .global-sticky-header {
    position: sticky;
    top: 65px;
    z-index: 1040;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
  }
  .scoreboard-table {
    width: 100%;
    table-layout: fixed;
  }
  .ranking-sidebar {
    position: sticky;
    top: 80px;
    height: calc(100vh - 100px);
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
  }
  .ranking-item {
    display: flex;
    align-items: center;
    justify-content: start;
    gap: 8px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    margin-bottom: 6px;
    cursor: grab;
  }
  .ranking-item.dragging {
    background: #e0f7ff;
    border-color: #00aaff;
  }
  .ranking-index {
    width: 22px;
    text-align: right;
    font-weight: 600;
    color: #6c757d;
  }
  .ranking-badge {
    margin-left: 4px;
    font-size: 0.7rem;
  }
  .save-rank-btn {
    width: 100%;
    margin-top: 0.5rem;
  }
  .col-pair { width: 90px; }
  .col-player { width: 250px; }
  .col-wins, .col-losses { width: 160px; }
  .col-sets { width: 120px; }
  .col-diff { width: 100px; }
  .col-points { width: 100px; }
</style>

<div class="container py-4">

  
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><?php echo e($event->name); ?> — Scoreboard</h4>

    <button id="toggleExcludeBtn" class="btn btn-sm btn-primary">
      <?php echo e($excluded ? 'Show All Regions' : 'Exclude ZF'); ?>

    </button>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($excluded): ?>
  <div class="alert alert-warning">
    Showing results <strong>without</strong> region: <b><?php echo e(strtoupper($excluded)); ?></b>
    <a href="<?php echo e(url()->current()); ?>" class="btn btn-sm btn-outline-secondary ms-3">Reset Filter</a>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="scoreboard-layout">

    
    <div class="scoreboard-main">
      <div class="global-sticky-header">
        <table class="table table-sm table-bordered mb-0 align-middle text-center">
          <thead class="table-light">
            <tr>
              <th class="col-pair">Pair</th>
              <th class="col-player">Player (Region)</th>
              <th class="col-wins">Wins</th>
              <th class="col-losses">Losses</th>
              <th class="col-sets">Sets (W/L)</th>
              <th class="col-diff">Set Diff</th>
              <th class="col-points">Points</th>
            </tr>
          </thead>
        </table>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playerStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupName => $players): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-primary text-white">
            <strong><?php echo e($groupName); ?></strong>
          </div>

          <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0 align-middle scoreboard-table text-center">
              <tbody>
                <?php $lastPair = null; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php
                    $pairIndex = ceil(($p['rank'] ?? 99) / 2);
                    $pairLabel = (($pairIndex * 2) - 1) . '/' . ($pairIndex * 2);
                    $setDiff = ($p['sets_won'] ?? 0) - ($p['sets_lost'] ?? 0);
                    $regionShort = $p['region_short'] ?? ($p['regions']['short_name'] ?? null);
                  ?>
                  <tr>
                    <td class="col-pair fw-bold">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lastPair !== $pairIndex): ?>
                        <?php echo e($pairLabel); ?>

                        <?php $lastPair = $pairIndex; ?>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>

                    <td class="col-player text-start">
                      <?php echo e($p['name']); ?>

                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionShort): ?>
                        <span class="badge bg-label-info ranking-badge"><?php echo e($regionShort); ?></span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      (<?php echo e($p['rank']); ?>)
                    </td>

                    <td class="col-wins">
                      <?php echo e($p['wins'] ?? 0); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $p['won_against']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div>
    <?php echo e(is_array($opponent) ? $opponent['name'] : $opponent); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($opponent) && !empty($opponent['score'])): ?>
      <span class="text-muted">(<?php echo e($opponent['score']); ?>)</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </td>

                    <td class="col-losses">
                      <?php echo e($p['losses'] ?? 0); ?>

                     <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($p['lost_to'])): ?>
  <div class="small text-danger mt-1">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $p['lost_to']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div>
    <?php echo e(is_array($opponent) ? $opponent['name'] : $opponent); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($opponent) && !empty($opponent['score'])): ?>
      <span class="text-muted">(<?php echo e($opponent['score']); ?>)</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </td>

                    <td class="col-sets fw-bold"><?php echo e($p['sets_won'] ?? 0); ?>–<?php echo e($p['sets_lost'] ?? 0); ?></td>

                    <td class="col-diff fw-bold">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($setDiff > 0): ?>
                        <span class="text-success">+<?php echo e($setDiff); ?></span>
                      <?php elseif($setDiff < 0): ?>
                        <span class="text-danger"><?php echo e($setDiff); ?></span>
                      <?php else: ?>
                        <span class="text-muted"><?php echo e($setDiff); ?></span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>

                    <td class="col-points fw-bold"><?php echo e($p['points'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="ranking-sidebar">
      <h5 class="fw-bold mb-3">Draggable Rankings</h5>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $flatPlayersByGroup; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupKey => $players): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="ranking-block">
          <h6 class="fw-bold mb-2"><?php echo e($groupKey); ?></h6>
          <div id="rankingList_<?php echo e(Str::slug($groupKey)); ?>" class="ranking-list">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php $regionShort = $p['region_short'] ?? ($p['region']['short_name'] ?? null); ?>
              <div class="ranking-item" data-id="<?php echo e($p['id']); ?>">
                <div class="ranking-index"><?php echo e($index + 1); ?>.</div>
                <div>
                  <?php echo e($p['name']); ?>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionShort): ?>
                    <span class="badge bg-label-info ranking-badge"><?php echo e($regionShort); ?></span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <button class="btn btn-success btn-sm save-rank-btn mt-2" data-group="<?php echo e(Str::slug($groupKey)); ?>">
            💾 Save <?php echo e($groupKey); ?>

          </button>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

  </div>
</div>

<script>
$(function () {
  const btn = $('#toggleExcludeBtn');
  const regionShort = '<?php echo e(strtolower($event->regions->firstWhere("region_name", "like", "%ZF Mcawu%")?->short_name ?? "zfm")); ?>';

  btn.on('click', function () {
    const currentUrl = new URL(window.location.href);
    const exclude = currentUrl.searchParams.get('exclude');
    if (exclude === regionShort) {
      currentUrl.searchParams.delete('exclude');
      toastr.info('Showing all teams again');
    } else {
      currentUrl.searchParams.set('exclude', regionShort);
      toastr.info('Excluding ' + regionShort.toUpperCase() + ' teams');
    }
    window.location.href = currentUrl.toString();
  });

  // Renumber after drag
  function updateNumbers(listEl) {
    $(listEl).children('.ranking-item').each(function (i) {
      $(this).find('.ranking-index').text((i + 1) + '.');
    });
  }

  $('.ranking-list').each(function () {
    const list = this;
    new Sortable(list, {
      animation: 150,
      onStart: e => e.item.classList.add('dragging'),
      onEnd: e => {
        e.item.classList.remove('dragging');
        updateNumbers(list);
      }
    });
  });

  $('.save-rank-btn').on('click', function () {
    const group = $(this).data('group');
    const order = [];
    $('#rankingList_' + group + ' .ranking-item').each(function () {
      order.push($(this).data('id'));
    });

    console.log('Saving ranking for group:', group, order);
    toastr.success('Ranking order for ' + group + ' saved (demo)');
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\scoreboard\show-scoreboard.blade.php ENDPATH**/ ?>