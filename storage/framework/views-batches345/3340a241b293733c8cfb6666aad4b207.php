<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <a href="<?php echo e(route('superadmin.player-duplicates.index')); ?>" class="small">&larr; Duplicate candidate queue</a>
      <h4 class="mt-2 mb-1">Compare profiles #<?php echo e($first->id); ?> and #<?php echo e($second->id); ?></h4>
      <span class="badge bg-label-<?php echo e($analysis['confidence']['class']); ?>"><?php echo e($analysis['confidence']['label']); ?></span>
    </div>
    <div class="btn-group" role="group" aria-label="Choose canonical profile">
      <a href="<?php echo e(route('superadmin.player-duplicates.review', [$first, $second])); ?>?keep=<?php echo e($first->id); ?>" class="btn btn-sm <?php echo e($analysis['keep']->is($first) ? 'btn-primary' : 'btn-outline-primary'); ?>">Keep #<?php echo e($first->id); ?></a>
      <a href="<?php echo e(route('superadmin.player-duplicates.review', [$first, $second])); ?>?keep=<?php echo e($second->id); ?>" class="btn btn-sm <?php echo e($analysis['keep']->is($second) ? 'btn-primary' : 'btn-outline-primary'); ?>">Keep #<?php echo e($second->id); ?></a>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publishedWorkflow): ?>
    <div class="alert alert-warning">
      <strong>2026 published-ranking merge workflow.</strong>
      This operation is limited to 2026 ranking collisions. The current published snapshot will be archived, all player history will be consolidated, and the corrected ranking will be rebuilt for review. It will not be published automatically.
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analysis['blockers']): ?>
    <div class="alert alert-danger">
      <strong>Merge blocked.</strong>
      <ul class="mb-0 mt-2">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $analysis['blockers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blocker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li class="mb-2">
          <strong><?php echo e(str_replace('_', ' ', ucfirst($blocker['domain']))); ?>:</strong> <?php echo e($blocker['message']); ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publishedWorkflow && $blocker['domain'] === 'identity' && !$identityOverride): ?>
            <div class="mt-2">
              <a class="btn btn-sm btn-outline-danger" href="<?php echo e(request()->fullUrlWithQuery(['identity_override' => 1])); ?>">
                Confirm same player and enable identity override
              </a>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($blocker['contexts'])): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($blocker['contexts'][0]['type'] ?? null) === 'series_ranking'): ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $blocker['contexts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $context): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="border rounded bg-white p-2 mt-2 small">
                  <strong>Series #<?php echo e($context['series_id']); ?>, category #<?php echo e($context['category_id'] ?? 'none'); ?></strong>
                  <div class="mt-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $context['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <span class="badge bg-label-danger me-1">row #<?php echo e($row['id']); ?> · player #<?php echo e($row['player_id']); ?> · <?php echo e($row['status'] ?: 'blank status'); ?></span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                  <div class="text-muted mt-1">Series lifecycle: <?php echo e(collect($context['series_status_counts'])->map(fn($count, $status) => $status.': '.$count)->implode(', ')); ?></div>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php elseif(($blocker['contexts'][0]['type'] ?? null) === 'tournament_registration_overlap'): ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $blocker['contexts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $context): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="border rounded bg-white p-2 mt-2 small">
                  <strong><?php echo e($context['event_name'] ?? 'Event #'.($context['event_id'] ?? 'unknown')); ?> / <?php echo e($context['category_name'] ?? 'category #'.($context['category_id'] ?? 'unknown')); ?></strong>
                  <div class="text-muted">Category event #<?php echo e($context['category_event_id']); ?></div>
                  <div class="mt-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $context['entries'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <span class="badge bg-label-<?php echo e($entry['paid'] ? 'warning' : 'secondary'); ?> me-1">
                        entry #<?php echo e($entry['entry_id']); ?> · registration #<?php echo e($entry['registration_id']); ?> · player #<?php echo e($entry['player_id']); ?> · <?php echo e($entry['status'] ?: 'blank status'); ?><?php echo e($entry['paid'] ? ' · paid' : ''); ?>

                      </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                  <div class="text-danger mt-1">The entries are not an unambiguous paid-versus-abandoned pair, so they require manual review.</div>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
            <div class="table-responsive mt-2">
              <table class="table table-sm table-bordered bg-white mb-1">
                <thead><tr><th>Record</th><th>Fixture / draw</th><th>Event</th><th>Results</th><th>Action</th></tr></thead>
                <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $blocker['contexts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $context): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr>
                    <td>#<?php echo e($context['record_id']); ?></td>
                    <td>
                      <strong>Fixture #<?php echo e($context['fixture_id'] ?: 'unknown'); ?></strong>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($context['draw_id']): ?><div class="small">Draw #<?php echo e($context['draw_id']); ?><?php echo e($context['draw_name'] ? ' — '.$context['draw_name'] : ''); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($context['fixture_created_at']): ?><div class="small text-muted">Created <?php echo e($context['fixture_created_at']); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($context['event_id']): ?>
                        <strong>#<?php echo e($context['event_id']); ?> <?php echo e($context['event_name'] ?: 'Unnamed event'); ?></strong>
                        <div class="small"><?php echo e($context['event_start_date'] ?: 'No start date'); ?><?php echo e($context['event_end_date'] ? ' to '.$context['event_end_date'] : ''); ?></div>
                      <?php else: ?>
                        <span class="badge bg-label-warning">No linked event</span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($context['result_count'] > 0): ?>
                        <span class="badge bg-label-danger"><?php echo e($context['result_count']); ?> saved result rows</span>
                      <?php else: ?>
                        <span class="badge bg-label-secondary">No saved results</span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($context['event_id']): ?>
                        <a href="<?php echo e(route('admin.events.individual.hq', $context['event_id'])); ?>" class="btn btn-outline-primary btn-sm">Open event fixtures</a>
                      <?php else: ?>
                        <span class="small text-muted">Inspect database record before correction</span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
    </div>
  <?php else: ?>
    <div class="alert alert-warning"><strong>Permanent identity change.</strong> All linked history will point to #<?php echo e($analysis['keep']->id); ?> and source #<?php echo e($analysis['remove']->id); ?> will be removed after a zero-reference check.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header"><strong>Profile field decisions</strong></div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Field</th><th>Keep #<?php echo e($analysis['keep']->id); ?></th><th>Source #<?php echo e($analysis['remove']->id); ?></th><th>Final value</th></tr></thead>
        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $analysis['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $comparison): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr class="<?php echo e($comparison['different'] ? 'table-warning' : ''); ?>">
            <td><?php echo e(str_replace('_', ' ', ucfirst($field))); ?></td>
            <td><?php echo e(filled($comparison['keep']) ? $comparison['keep'] : 'Not set'); ?></td>
            <td><?php echo e(filled($comparison['remove']) ? $comparison['remove'] : 'Not set'); ?></td>
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comparison['different']): ?>
                <select name="field_sources[<?php echo e($field); ?>]" form="merge-form" class="form-select form-select-sm" <?php echo e($analysis['can_merge'] ? '' : 'disabled'); ?>>
                  <option value="keep" <?php if(old("field_sources.{$field}", $comparison['recommended']) === 'keep'): echo 'selected'; endif; ?>>Use #<?php echo e($analysis['keep']->id); ?> value</option>
                  <option value="remove" <?php if(old("field_sources.{$field}", $comparison['recommended']) === 'remove'): echo 'selected'; endif; ?>>Use #<?php echo e($analysis['remove']->id); ?> value</option>
                </select>
              <?php else: ?>
                <span class="text-muted">Same value</span>
                <input type="hidden" name="field_sources[<?php echo e($field); ?>]" value="keep" form="merge-form">
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><strong>Linked-record impact</strong></div>
    <div class="card-body">
      <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="border rounded p-3"><strong>Canonical #<?php echo e($analysis['keep']->id); ?></strong><div class="text-muted small"><?php echo e($analysis['impact']['keep']['usage_total']); ?> current references</div></div></div>
        <div class="col-md-6"><div class="border rounded p-3"><strong>Source #<?php echo e($analysis['remove']->id); ?></strong><div class="text-muted small"><?php echo e($analysis['impact']['remove']['usage_total']); ?> references will move</div></div></div>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $analysis['impact']['references']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $table => $columns): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php ($sourceCount = collect($columns)->sum(fn($counts) => $counts['remove'])); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sourceCount > 0): ?>
          <div class="d-flex justify-content-between border-bottom py-2"><span><?php echo e(str_replace('_', ' ', ucfirst($table))); ?></span><strong><?php echo e($sourceCount); ?></strong></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p class="text-muted mb-0">No historical references were found.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php ($registrationHistoryCount = collect($analysis['impact']['registration_history'])->sum(fn($columns) => collect($columns)->sum(fn($counts) => $counts['remove']))); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationHistoryCount > 0): ?>
        <div class="alert alert-info mt-3 mb-0">
          <strong><?php echo e($registrationHistoryCount); ?> tournament result/draw references are registration-based.</strong>
          Their registration IDs and recorded outcomes will not be changed; ownership of those registrations moves to the canonical player so future ranking calculations retain the same results.
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($analysis['impact']['ranking_rebuild_series_ids'] ?? [])): ?>
        <div class="alert alert-warning mt-3 mb-0">
          <strong>Calculated ranking collision will be resolved automatically.</strong>
          Series <?php echo e(collect($analysis['impact']['ranking_rebuild_series_ids'])->map(fn($id) => '#'.$id)->implode(', ')); ?> will be rebuilt from the preserved registrations and tournament results after the profiles are combined. Existing published ranking snapshots are not republished or changed by this merge.
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $analysis['impact']['registration_overlap_resolutions'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $resolution): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="alert alert-success mt-3 mb-0">
          <strong>Abandoned registration will be resolved automatically.</strong>
          <?php echo e($resolution['event_name'] ?? 'Event #'.$resolution['event_id']); ?> / <?php echo e($resolution['category_name'] ?? 'category #'.$resolution['category_id']); ?>:
          unpaid registration #<?php echo e($resolution['duplicate_registration_id']); ?> (order #<?php echo e($resolution['duplicate_order_id']); ?>)
          will be marked withdrawn, while registration #<?php echo e($resolution['canonical_registration_id']); ?> is retained using
          <?php echo e(implode(' and ', $resolution['canonical_evidence'])); ?> evidence. Both orders and all saved results remain in the audit history.
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($analysis['impact']['owners_to_transfer'])): ?>
        <div class="mt-3 small text-muted">Linked user IDs to transfer: <?php echo e(implode(', ', $analysis['impact']['owners_to_transfer'])); ?></div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-danger">
        <div class="card-header"><strong>Super Admin confirmation</strong></div>
        <div class="card-body">
          <form id="merge-form" method="POST" action="<?php echo e($publishedWorkflow ? route('superadmin.player-duplicates.merge-published') : route('superadmin.player-duplicates.merge')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="keep_player_id" value="<?php echo e($analysis['keep']->id); ?>">
            <input type="hidden" name="remove_player_id" value="<?php echo e($analysis['remove']->id); ?>">
            <input type="hidden" name="impact_digest" value="<?php echo e($analysis['digest']); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($identityOverride): ?>
              <input type="hidden" name="identity_override" value="1">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="mb-3">
              <label class="form-label">Reason for merging</label>
              <textarea name="reason" class="form-control" rows="3" required minlength="10" maxlength="2000" <?php echo e($analysis['can_merge'] ? '' : 'disabled'); ?>><?php echo e(old('reason')); ?></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Type exactly: <code><?php echo e($identityOverride ? 'MERGE PUBLISHED IDENTITY OVERRIDE' : ($publishedWorkflow ? 'MERGE PUBLISHED' : $analysis['confirmation_phrase'])); ?></code></label>
              <input name="confirmation" class="form-control" required autocomplete="off" value="<?php echo e(old('confirmation')); ?>" <?php echo e($analysis['can_merge'] ? '' : 'disabled'); ?>>
            </div>
            <button class="btn <?php echo e($publishedWorkflow ? 'btn-warning' : 'btn-danger'); ?>" <?php echo e($analysis['can_merge'] ? '' : 'disabled'); ?>><i class="ti ti-git-merge me-1"></i><?php echo e($publishedWorkflow ? 'Archive, merge and rebuild 2026 ranking' : 'Confirm permanent merge'); ?></button>
            <div class="small text-muted mt-2">The merge will be rejected if any linked data changed since this page loaded.</div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><strong>Do not merge</strong></div>
        <div class="card-body">
          <form method="POST" action="<?php echo e(route('superadmin.player-duplicates.decision', [$first, $second])); ?>">
            <?php echo csrf_field(); ?>
            <label class="form-label">Review note</label>
            <textarea name="reason" class="form-control mb-3" rows="3" required minlength="5"></textarea>
            <div class="d-grid gap-2">
              <button name="decision" value="not_duplicate" class="btn btn-outline-danger">Mark as not duplicates</button>
              <button name="decision" value="review_later" class="btn btn-outline-secondary">Review later</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\player-duplicate-review.blade.php ENDPATH**/ ?>