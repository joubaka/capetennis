<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-git-merge me-2 text-primary"></i>Review bulk duplicate merge</h4>
      <p class="text-muted mb-0"><?php echo e($batch['selected_count']); ?> selected: <?php echo e(count($batch['analyses'])); ?> ready and <?php echo e(count($batch['skipped'])); ?> skipped.</p>
    </div>
    <a href="<?php echo e(route('superadmin.player-duplicates.index')); ?>" class="btn btn-outline-secondary">Back to candidates</a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="ti ti-alert-triangle fs-4"></i>
    <div><strong>Unsafe plans are skipped before confirmation.</strong> The remaining ready group follows the suggested keep/remove directions as one atomic action. Every plan is checked again before any profile is removed.</div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch['skipped']): ?>
    <div class="card border-danger mb-4">
      <div class="card-header d-flex align-items-center gap-2 text-danger">
        <i class="ti ti-player-skip-forward fs-5"></i><strong>Skipped automatically (<?php echo e(count($batch['skipped'])); ?>)</strong>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Candidate</th><th>What would have happened</th><th>Why it was skipped</th><th>Next option</th></tr></thead>
          <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $batch['skipped']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $skipped): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($skipped['keep'] && $skipped['remove']): ?>
                  <strong>#<?php echo e($skipped['remove']->id); ?> <?php echo e($skipped['remove']->full_name); ?></strong>
                  <div class="small text-muted">paired with #<?php echo e($skipped['keep']->id); ?> <?php echo e($skipped['keep']->full_name); ?></div>
                <?php else: ?>
                  <strong>Profiles #<?php echo e($skipped['first_id']); ?> and #<?php echo e($skipped['second_id']); ?></strong>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($skipped['keep'] && $skipped['remove']): ?>
                  <span class="small">Remove #<?php echo e($skipped['remove']->id); ?> into retained profile #<?php echo e($skipped['keep']->id); ?></span>
                <?php else: ?>
                  <span class="small text-muted">Could not safely determine direction</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <ul class="mb-0 ps-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $skipped['reasons']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reason): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($reason); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $skipped['contexts'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $context): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <div class="border rounded p-2 mt-2 small">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($context['type'] ?? null) === 'series_ranking'): ?>
                      <strong>Series #<?php echo e($context['series_id']); ?>, category #<?php echo e($context['category_id'] ?? 'none'); ?></strong>
                      <div class="mt-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $context['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <span class="badge bg-label-danger me-1">row #<?php echo e($row['id']); ?> · player #<?php echo e($row['player_id']); ?> · <?php echo e($row['status'] ?: 'blank status'); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </div>
                      <div class="text-muted mt-1">Series lifecycle: <?php echo e(collect($context['series_status_counts'])->map(fn($count, $status) => $status.': '.$count)->implode(', ')); ?></div>
                    <?php elseif(($context['type'] ?? null) === 'tournament_registration_overlap'): ?>
                      <strong><?php echo e($context['event_name'] ?? 'Event #'.($context['event_id'] ?? 'unknown')); ?> / <?php echo e($context['category_name'] ?? 'category #'.($context['category_id'] ?? 'unknown')); ?></strong>
                      <div class="text-muted">Category event #<?php echo e($context['category_event_id']); ?></div>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $context['entries'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="badge bg-label-<?php echo e($entry['paid'] ? 'warning' : 'secondary'); ?> me-1">registration #<?php echo e($entry['registration_id']); ?> · <?php echo e($entry['status'] ?: 'blank'); ?><?php echo e($entry['paid'] ? ' · paid' : ''); ?></span>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                      <strong>Fixture #<?php echo e($context['fixture_id'] ?? 'unknown'); ?></strong>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($context['event_id'] ?? null): ?>
                        — Event #<?php echo e($context['event_id']); ?> <?php echo e($context['event_name'] ?? 'Unnamed event'); ?>

                      <?php else: ?>
                        — no linked event
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <span class="badge bg-label-<?php echo e(($context['result_count'] ?? 0) > 0 ? 'danger' : 'secondary'); ?> ms-1"><?php echo e($context['result_count'] ?? 0); ?> result rows</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($skipped['keep'] && $skipped['remove']): ?>
                  <a href="<?php echo e(route('superadmin.player-duplicates.review', [$skipped['keep'], $skipped['remove']])); ?>" class="btn btn-outline-danger btn-sm">Open full review</a>
                <?php else: ?>
                  <span class="badge bg-label-secondary">Review separately</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch['analyses']): ?>
  <div class="card border-success mb-4">
    <div class="card-header text-success"><i class="ti ti-circle-check me-1"></i><strong>Ready to merge (<?php echo e(count($batch['analyses'])); ?>)</strong></div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Keep canonical profile</th><th>Merge and remove source</th><th>History protected</th><th>Automatic profile values</th></tr></thead>
        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $batch['analyses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $analysis): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $recommendedValues = collect($analysis['fields'])
              ->filter(fn($field) => $field['different'] && $field['recommended'] === 'remove');
            $registrationHistoryCount = collect($analysis['impact']['registration_history'])
              ->sum(fn($columns) => collect($columns)->sum(fn($counts) => $counts['keep'] + $counts['remove']));
          ?>
          <tr>
            <td>
              <strong>#<?php echo e($analysis['keep']->id); ?> <?php echo e($analysis['keep']->full_name); ?></strong>
              <div class="small text-success"><?php echo e($analysis['impact']['keep']['usage_total']); ?> linked records</div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analysis['overlap_resolution'] ?? false): ?>
                <div class="small text-primary mt-1"><i class="ti ti-route me-1"></i><?php echo e($analysis['overlap_resolution']); ?></div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td><strong>#<?php echo e($analysis['remove']->id); ?> <?php echo e($analysis['remove']->full_name); ?></strong><div class="small text-muted"><?php echo e($analysis['impact']['remove']['usage_total']); ?> linked records will move</div></td>
            <td>
              <span class="badge bg-label-primary"><?php echo e($registrationHistoryCount); ?> registration/result references</span>
              <div class="small text-muted mt-1">Registration IDs, tournament results and ranking attribution remain attached to #<?php echo e($analysis['keep']->id); ?>.</div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($analysis['impact']['ranking_rebuild_series_ids'] ?? [])): ?>
                <div class="small text-warning mt-1">
                  <i class="ti ti-refresh me-1"></i>Rebuild calculated ranking for series <?php echo e(collect($analysis['impact']['ranking_rebuild_series_ids'])->map(fn($id) => '#'.$id)->implode(', ')); ?>. Published snapshots remain unchanged.
                </div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $analysis['impact']['registration_overlap_resolutions'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resolution): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="small text-success mt-1">
                  <i class="ti ti-recycle me-1"></i><?php echo e($resolution['event_name'] ?? 'Event #'.$resolution['event_id']); ?> / <?php echo e($resolution['category_name'] ?? 'category #'.$resolution['category_id']); ?>:
                  withdraw unpaid registration #<?php echo e($resolution['duplicate_registration_id']); ?> and retain #<?php echo e($resolution['canonical_registration_id']); ?>. Orders/results stay preserved.
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recommendedValues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $comparison): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="small"><strong><?php echo e(str_replace('_', ' ', ucfirst($field))); ?>:</strong> <?php echo e(filled($comparison['remove']) ? $comparison['remove'] : 'Blank'); ?></div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <span class="small text-muted">Keep canonical values</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <form method="POST" action="<?php echo e(route('superadmin.player-duplicates.bulk-merge')); ?>" class="card">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="batch_mode" value="<?php echo e($batch['mode'] ?? 'quick'); ?>">
    <input type="hidden" name="batch_digest" value="<?php echo e($batch['digest']); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $batch['analyses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $analysis): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <input type="hidden" name="pairs[<?php echo e($index); ?>][first_id]" value="<?php echo e(min($analysis['keep']->id, $analysis['remove']->id)); ?>">
      <input type="hidden" name="pairs[<?php echo e($index); ?>][second_id]" value="<?php echo e(max($analysis['keep']->id, $analysis['remove']->id)); ?>">
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Audit reason for all selected merges</label>
        <textarea name="reason" class="form-control" rows="2" minlength="10" maxlength="2000" required><?php echo e(old('reason', 'Confirmed one-sided-history duplicates after matching identity details.')); ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Type exactly: <code><?php echo e($batch['confirmation_phrase']); ?></code></label>
        <input name="confirmation" class="form-control" value="<?php echo e(old('confirmation')); ?>" autocomplete="off" required>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted">Restricted to Super Admins. The reviewed batch digest is checked again before any profile is changed.</span>
        <button class="btn btn-danger"><i class="ti ti-git-merge me-1"></i>Merge all <?php echo e(count($batch['analyses'])); ?> selected profiles</button>
      </div>
    </div>
  </form>
  <?php else: ?>
    <div class="alert alert-secondary mb-0"><strong>Nothing will be merged.</strong> Every selected candidate was skipped. Use the full-review links above or return to the candidate list.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\player-duplicate-bulk-review.blade.php ENDPATH**/ ?>