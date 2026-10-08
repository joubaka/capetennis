


<?php $__env->startSection('title', 'Rankings — ' . $series->name); ?>

<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .rank-card .card-header {
    background:#e9ecef;
    border-bottom:1px solid #dee2e6
  }
  .rank-card .card {
    border-radius:.75rem;
    box-shadow:0 .25rem .75rem rgba(0,0,0,.05)
  }
  .rank-card table th {
    text-transform:uppercase;
    letter-spacing:.04em;
    font-size:.75rem;
    color:#6c757d
  }
  .rank-card .total {font-weight:700}
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  
  <h4 class="fw-bold py-2 mb-4">
    <span class="text-muted fw-light">Series /</span> Rankings — <?php echo e($series->name); ?>

  </h4>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('calc_report')): ?>
    <?php $report = session('calc_report'); ?>
    <div class="alert alert-info">
      <div class="fw-bold mb-2">Rankings recalculated</div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $report; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="mb-2 p-2 border rounded">
          <div class="d-flex justify-content-between">
            <span><strong><?php echo e($r['list_name']); ?></strong></span>
            <span class="badge bg-label-<?php echo e($r['status']==='ok' ? 'success' : 'secondary'); ?>">
              <?php echo e($r['status']); ?>

            </span>
          </div>
          <div class="small text-muted">
            Events in list: <?php echo e($r['events_count']); ?> • Players scored: <?php echo e($r['players_scored']); ?>

          </div>
          <div class="mt-1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $r['categories']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <div><?php echo e($c['event']); ?> — <?php echo e($c['category']); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <em>No categories</em>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($r['notes'])): ?>
            <div class="mt-1 text-warning small">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $r['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <?php echo e($n); ?><br> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="row">
    
    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Create Ranking List</h5></div>
        <div class="card-body">
          <form id="createListForm" action="<?php echo e(route('ranking.lists.store', $series->id)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div class="mb-2">
              <label class="form-label">List Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select" required>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($cat->id); ?>"><?php echo e($cat->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
            </div>
            <button class="btn btn-primary w-100" type="submit">Create</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Available Category-Events</h5></div>
        <div class="card-body">
          <div id="availableCats" class="list-group">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series_categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="mb-2 fw-bold"><?php echo e($event->name); ?></div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $catEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="javascript:;" class="list-group-item list-group-item-action"
                   data-category-event-id="<?php echo e($catEvent->pivot->id); ?>">
                  <?php echo e($event->name); ?> — <?php echo e($catEvent->name); ?>

                </a>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <small class="text-muted d-block mt-2">Drag onto a list →</small>
        </div>
      </div>
    </div>

    
    <div class="col-lg-6">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $series->ranking_lists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $list): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="card mb-3" data-list-id="<?php echo e($list->id); ?>">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div>
              <input class="form-control form-control-sm border-0 fw-semibold" style="width:auto"
                     value="<?php echo e($list->name); ?>" data-rename-input="<?php echo e($list->id); ?>">
              <small class="text-muted"><?php echo e($list->category->name ?? ''); ?></small>
            </div>
            <button class="btn btn-sm btn-outline-danger" data-delete-list="<?php echo e($list->id); ?>">Delete</button>
          </div>
          <div class="card-body">
            <div class="list-group droppable" data-list-body="<?php echo e($list->id); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $list->rank_cats->sortBy('order'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="javascript:;" class="list-group-item d-flex justify-content-between align-items-center"
                   data-category-event-id="<?php echo e($rc->category_event_id); ?>">
                  <?php echo e($rc->eventCategory->event->name); ?> — <?php echo e($rc->eventCategory->category->name); ?>

                  <span class="badge bg-label-danger" data-remove-cat="<?php echo e($list->id); ?>">&times;</span>
                </a>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="alert alert-info">No ranking lists yet. Create one on the left.</div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Settings</h5></div>
        <div class="card-body">
          <form id="settingsForm" action="<?php echo e(route('ranking.settings.update', $series->id)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div class="mb-2">
              <label class="form-label">Best N scores</label>
              <input type="number" min="1" name="nums" class="form-control"
                     value="<?php echo e($series->best_num_of_scores ?? 3); ?>">
            </div>
            <div class="mb-2">
              <label class="form-label">Points (Position → Score)</label>
              <div id="pointsRepeater">
                <?php
                  $points = $points ?? \App\Models\Point::where('series_id', $series->id)
                              ->orderBy('position')->get();
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i=1; $i<=25; $i++): ?>
                  <div class="d-flex gap-2 mb-1">
                    <input type="text" class="form-control form-control-sm" value="<?php echo e($i); ?>" disabled>
                    <input type="number" name="position[<?php echo e($i-1); ?>]" class="form-control form-control-sm"
                           value="<?php echo e(optional($points->firstWhere('position',$i))->score ?? (26-$i)*100); ?>">
                  </div>
                <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>
            <input type="hidden" name="events" value="<?php echo e($series->events->pluck('id')->implode(',')); ?>">
            <button class="btn btn-sm btn-primary w-100" type="submit">Save Settings</button>
          </form>
        </div>
      </div>

      <form id="calcForm" action="<?php echo e(route('ranking.calculate', $series->id)); ?>" method="POST" class="card">
        <?php echo csrf_field(); ?>
        <div class="card-body">
          <button class="btn btn-info w-100" type="submit">Recalculate Rankings</button>
        </div>
      </form>
    </div>
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($report['lists'])): ?>
    <div class="container-xxl py-4">
      <h3 class="mb-4">Calculated Rankings</h3>

     <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $series->ranking_lists->chunk(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chunk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="row g-4">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $chunk; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $list): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-md-6">
          <div class="card rank-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h4 class="mb-0">
                <span class="badge rounded-pill bg-primary">
                  <?php echo e($list->name ?? ($list->category->name ?? 'Ranking List')); ?>

                </span>
              </h4>
              <small class="text-muted"><?php echo e($list->category->name ?? 'No category'); ?></small>
            </div>

            <div class="card-body p-0">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($list->ranking_scores->count()): ?>
                <div class="table-responsive">
                  <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px">#</th>
                        <th>Player</th>
                        <th style="width:350px">Events & Points</th>
                        <th style="width:100px" class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php $rank = 1; ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($list->ranking_scores ?? collect())->sortByDesc('total_points'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $score): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                          <td><?php echo e($rank++); ?></td>
                       <td>
  <a href="javascript:;" class="split-toggle"
     data-score-id="<?php echo e($score->id); ?>"
     data-primary="<?php echo e($score->primarySchool); ?>"
     data-high="<?php echo e($score->highSchool); ?>">
    <?php echo e($score->player?->fullName ?? 'Unknown'); ?> <?php echo e($score->player?->id); ?>

  </a>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($score->primarySchool): ?>
    <span class="badge bg-success ms-1 split-toggle"
          data-score-id="<?php echo e($score->id); ?>"
          data-primary="1" data-high="0">U/13</span>
  <?php elseif($score->highSchool): ?>
    <span class="badge bg-info ms-1 split-toggle"
          data-score-id="<?php echo e($score->id); ?>"
          data-primary="0" data-high="1">U/14</span>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>


                          <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $score->legs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                              <span class="badge bg-label-primary me-1">
                                <?php echo e($leg->event_name); ?>:
                                <?php echo e($leg->points); ?>

                                <small class="text-muted">(<?php echo e($leg->position); ?>)</small>
                              </span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          </td>
                          <td class="text-end total"><?php echo e($score->total_points); ?></td>
                        </tr>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="p-3 text-muted">No scores yet.</div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="alert alert-info">No ranking lists yet.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="accordion mt-4" id="debugAccordion">
    <div class="accordion-item">
      <h2 class="accordion-header" id="h1">
        <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#c1">
          Debug Trace
        </button>
      </h2>
      <div id="c1" class="accordion-collapse collapse">
        <div class="accordion-body">
          <pre class="small mb-0"><?php echo e(json_encode($report['debug'] ?? [], JSON_PRETTY_PRINT)); ?></pre>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>




<?php $__env->startSection('page-script'); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).on('click', '.split-toggle', function () {
  const scoreId = $(this).data('score-id');
  const isPrimary = $(this).data('primary') == 1;
  const isHigh = $(this).data('high') == 1;

  Swal.fire({
    title: 'Assign player to group',
    input: 'select',
    inputOptions: {
      'primary': 'U/13 (Primary School)',
      'high': 'U/14 (High School)',
      'clear': 'Clear assignment'
    },
    inputValue: isPrimary ? 'primary' : (isHigh ? 'high' : 'clear'),
    showCancelButton: true
  }).then(result => {
    if (result.isConfirmed) {
      $.ajax({
        url: "<?php echo e(url('backend/ranking-scores')); ?>/" + scoreId + "/school",
        type: 'POST', // ✅ force POST
        data: {
          _token: '<?php echo e(csrf_token()); ?>',
          group: result.value
        },
        success: () => location.reload(),
        error: (xhr) => {
          console.error('❌ Error:', xhr.status, xhr.responseText);
        }
      });
    }
  });
});
</script>


<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\ranking\admin.blade.php ENDPATH**/ ?>