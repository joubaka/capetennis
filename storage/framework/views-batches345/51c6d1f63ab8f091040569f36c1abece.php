

<?php $__env->startSection('title', isset($event) ? "Fixtures HQ — {$event->name}" : 'Fixtures HQ'); ?>


<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
  <style>
    .table-actions { min-width: 180px; }
    .fixture-row .scheduled { white-space: nowrap; }
  </style>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Fixtures HQ<?php echo e(isset($event) ? ' — '.$event->name : ''); ?></h4>
    <div>
      <a href="<?php echo e(route('backend.team-fixtures.create')); ?>" class="btn btn-outline-secondary">Create Rubber</a>
    </div>
  </div>

  
  <div class="card mb-3">
    <div class="card-body">
      <form method="POST" action="<?php echo e(route('backend.team-fixtures.store')); ?>" class="row g-2 align-items-end">
        <?php echo csrf_field(); ?>

        <div class="col-md-3">
          <label class="form-label">Draw</label>
          <select name="draw_id" class="form-select" required>
            <option value="">Select draw</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($d->id); ?>"><?php echo e($d->drawName); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Home Team</label>
          <select name="home_team_id" class="form-select select2" required>
            <option value="">Select home team</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($t->id); ?>"><?php echo e($t->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Away Team</label>
          <select name="away_team_id" class="form-select select2" required>
            <option value="">Select away team</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($t->id); ?>"><?php echo e($t->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Scheduled At</label>
          <input name="scheduled_at" class="form-control flatpickr" placeholder="YYYY-MM-DD HH:mm" />
        </div>

        <div class="col-md-2">
          <label class="form-label">Venue</label>
          <select name="venue_id" class="form-select">
            <option value="">—</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($v->id); ?>"><?php echo e($v->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Round</label>
          <input name="round_nr" type="number" min="1" value="1" class="form-control" required />
        </div>

        <div class="col-md-2">
          <label class="form-label">Tie</label>
          <input name="tie_nr" type="number" min="1" value="1" class="form-control" required />
        </div>

        <div class="col-md-2">
          <button class="btn btn-success w-100">Create Fixture</button>
        </div>
      </form>
    </div>
  </div>

  
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Draw</th>
            <th>Round</th>
            <th>Tie</th>
            <th>Home</th>
            <th>Away</th>
            <th>Scheduled</th>
            <th>Venue</th>
            <th class="text-end table-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr class="fixture-row" id="fx-<?php echo e($fx->id); ?>">
              <td><?php echo e($fx->id); ?></td>
              <td><?php echo e(optional($fx->draw)->drawName ?? '—'); ?></td>
              <td><?php echo e($fx->round_nr ?? '—'); ?></td>
              <td><?php echo e($fx->tie_nr ?? '—'); ?></td>
              <td><?php echo e($fx->teamTie?->home_side_name ?? 'Legacy / TBD'); ?></td>
              <td><?php echo e($fx->teamTie?->away_side_name ?? 'Legacy / TBD'); ?></td>
              <td class="scheduled">
                <?php echo e($fx->scheduled_at ? \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i') : '—'); ?>

              </td>
              <td><?php echo e(optional($fx->venue)->name ?? '—'); ?></td>
              <td class="text-end">
                <div class="btn-group">
                  <a href="<?php echo e(route('backend.team-fixtures.show', $fx->id)); ?>" class="btn btn-sm btn-outline-secondary">Show</a>
                  <a href="<?php echo e(route('backend.team-fixtures.edit', $fx->id)); ?>" class="btn btn-sm btn-outline-info">Edit</a>
                  <button type="button"
                          class="btn btn-sm btn-primary open-score-modal"
                          data-id="<?php echo e($fx->id); ?>" data-participant-revision="<?php echo e(app(\App\Services\TeamParticipantHistoryService::class)->revision($fx)); ?>"
                          data-home="<?php echo e($fx->teamTie?->home_side_name ?? 'Home'); ?>"
                          data-away="<?php echo e($fx->teamTie?->away_side_name ?? 'Away'); ?>"
                          data-bs-toggle="modal"
                          data-bs-target="#scoreModal">
                    Insert Scores
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No fixtures found.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="scoreForm" method="POST" action="">
      <?php echo csrf_field(); ?>
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Insert / Update Scores</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2"><strong id="scoreFixtureTeams"></strong></p>
          <input type="hidden" id="scoreFixtureId" name="fixture_id" />
          <div class="row g-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($i = 1; $i <= 3; $i++): ?>
              <div class="col-12 d-flex gap-2 align-items-center">
                <div style="width:80px">Set <?php echo e($i); ?></div>
                <input type="number" name="set<?php echo e($i); ?>_home" class="form-control" placeholder="Home" min="0" />
                <input type="number" name="set<?php echo e($i); ?>_away" class="form-control" placeholder="Away" min="0" />
              </div>
            <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Scores</button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.team-fixtures.partials.participant-revision-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    flatpickr('.flatpickr', { enableTime: true, dateFormat: "Y-m-d H:i" });
    $('.select2').select2({ width: '100%' });

    // Open score modal and set form action dynamically
    document.querySelectorAll('.open-score-modal').forEach(btn => {
      btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const home = this.dataset.home || 'Home';
        const away = this.dataset.away || 'Away';

        document.getElementById('scoreFixtureId').value = id;
        document.getElementById('scoreFixtureTeams').textContent = `${home} vs ${away}`;

        // set action to insertScore route
        const form = document.getElementById('scoreForm');
        form.action = `/backend/team-fixtures/${id}/insert-score`;
      });
    });
  });
</script>
<?php $__env->stopSection(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\admin.blade.php ENDPATH**/ ?>