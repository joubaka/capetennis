<?php $__env->startSection('title', ($player->full_name ?? $player->name) . ' — Ranking Detail'); ?>

<?php $__env->startSection('vendor-style'); ?>
<style>
  .detail-card { border: 1px solid var(--bs-border-color); border-radius: .5rem; }
  .detail-card .card-header { background: linear-gradient(90deg, rgba(0,123,255,0.06), rgba(13,110,253,0.02)); }
  .status-counted  { background-color: #198754; color: #fff; }
  .status-dropped  { background-color: #dc3545; color: #fff; }
  .status-auto     { background-color: #ffc107; color: #000; }
  .points-total    { font-weight: 700; font-size: 1.1rem; }
  .event-result-link { color: var(--bs-heading-color); font-weight: 600; text-decoration: none; }
  .event-result-link:hover { color: #14796e; text-decoration: underline; }
  .event-result-action { display: block; margin-top: .15rem; color: var(--bs-secondary-color); font-size: .75rem; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="col-12">

  
  <div class="mb-3">
    <a href="<?php echo e(route('frontend.ranking.show', $series)); ?>" class="btn btn-outline-secondary btn-sm">
      &larr; Back to Rankings
    </a>
  </div>

  
  <div class="card detail-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0"><?php echo e($player->full_name ?? $player->name); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $player->id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?></h5>
        <div class="text-muted small"><?php echo e($series->name); ?><?php echo e($series->year ? ' ' . $series->year : ''); ?></div>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingRecord): ?>
        <div class="text-end">
          <div class="text-muted small">Overall Rank</div>
          <span class="badge bg-primary fs-6">#<?php echo e($rankingRecord->rank_position); ?></span>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'super-user')): ?>
        <?php
          $whatsAppNumber = preg_replace('/[\s().-]+/', '', trim((string) $player->cellNr));
          if (str_starts_with($whatsAppNumber, '+')) {
            $whatsAppNumber = substr($whatsAppNumber, 1);
          } elseif (str_starts_with($whatsAppNumber, '00')) {
            $whatsAppNumber = substr($whatsAppNumber, 2);
          } elseif (preg_match('/^0[1-9][0-9]{8}$/', $whatsAppNumber)) {
            $whatsAppNumber = '27' . substr($whatsAppNumber, 1);
          }
          $hasWhatsAppNumber = preg_match('/^[1-9][0-9]{7,14}$/', $whatsAppNumber);
        ?>
        <div class="row g-3 mb-4" aria-label="Player contact details">
          <div class="col-sm-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small mb-1">Email</div>
              <div class="text-break"><?php echo e(filled($player->email) ? $player->email : 'Not provided'); ?></div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small mb-1">Telephone number</div>
              <div class="text-break"><?php echo e(filled($player->cellNr) ? $player->cellNr : 'Not provided'); ?></div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasWhatsAppNumber): ?>
                <a href="https://wa.me/<?php echo e($whatsAppNumber); ?>" class="btn btn-outline-success btn-sm mt-2" target="_blank" rel="noopener noreferrer">
                  <i class="ti ti-brand-whatsapp me-1" aria-hidden="true"></i> WhatsApp
                </a>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$rankingRecord): ?>
        <div class="alert alert-warning mb-0">
          No ranking data found for this player in <?php echo e($series->name); ?>.
        </div>
      <?php else: ?>
        
        <div class="row g-3 mb-4">
          <div class="col-sm-4">
            <div class="border rounded p-3 text-center">
              <div class="text-muted small mb-1">Category</div>
              <strong><?php echo e($rankingRecord->category->name ?? '—'); ?></strong>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="border rounded p-3 text-center">
              <div class="text-muted small mb-1">Rank Position</div>
              <strong>#<?php echo e($rankingRecord->rank_position); ?></strong>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="border rounded p-3 text-center">
              <div class="text-muted small mb-1">Total Points</div>
              <strong class="points-total"><?php echo e($rankingRecord->total_points); ?></strong>
            </div>
          </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($legs->isNotEmpty()): ?>
          <h6 class="mb-3">Event Breakdown</h6>
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Event</th>
                  <th>Category</th>
                  <th>Date</th>
                  <th class="text-center">Position</th>
                  <th class="text-center">Points</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php $countedTotal = 0; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $legs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $leg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php
                    $colour  = $leg['colour'] ?? 'grey';
                    $status  = $leg['status'] ?? 'dropped';
                    $isAuto  = !empty($leg['is_auto']);
                    $badgeClass = $isAuto ? 'status-auto' : ($status === 'counted' ? 'status-counted' : 'status-dropped');
                    $label   = $isAuto ? 'Auto-award' : ucfirst($status);
                    if ($status === 'counted') { $countedTotal += (int)($leg['points'] ?? 0); }
                  ?>
                  <tr>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($leg['event_url'])): ?>
                        <a href="<?php echo e($leg['event_url']); ?>" class="event-result-link">
                          <?php echo e($leg['event_name']); ?>

                          <i class="ti ti-arrow-up-right ms-1" aria-hidden="true"></i>
                        </a>
                        <span class="event-result-action">View <?php echo e($leg['event_destination']); ?></span>
                      <?php else: ?>
                        <span><?php echo e($leg['event_name']); ?></span>
                        <span class="event-result-action">Not available</span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="text-muted small"><?php echo e($leg['category_name'] ?? '—'); ?></td>
                    <td class="text-nowrap text-muted small">
                      <?php echo e($leg['event_date'] ? \Carbon\Carbon::parse($leg['event_date'])->format('d M Y') : '—'); ?>

                    </td>
                    <td class="text-center"><?php echo e($leg['position'] ?? '—'); ?></td>
                    <td class="text-center fw-bold"><?php echo e($leg['points'] ?? 0); ?></td>
                    <td class="text-center">
                      <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($label); ?></span>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <td colspan="4" class="text-end fw-bold">Counted Total</td>
                  <td class="text-center fw-bold points-total"><?php echo e($countedTotal); ?></td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-3 d-flex gap-2 flex-wrap">
            <span class="badge status-counted">Counted — contributes to ranking</span>
            <span class="badge status-dropped">Dropped — not counted</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($series->auto_award_rule)): ?>
              <span class="badge status-auto">Auto-award — awarded by rule</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info mb-0">No per-event breakdown available.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\ranking\player_detail.blade.php ENDPATH**/ ?>