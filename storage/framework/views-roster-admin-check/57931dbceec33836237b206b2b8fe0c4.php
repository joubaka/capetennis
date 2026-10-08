<?php $__env->startSection('title', 'Series Rankings'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
  <h4 class="mb-4">Rankings: <?php echo e($series->name); ?></h4>

  <div class="row">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $finalRankings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryKey => $rankings): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php($ratingCategory = \App\Models\Category::find($categoryKey))
      <div class="col-md-6">
        <div class="card mb-4">
          <div class="card-header bg-light">
            <h5 class="mb-0">
              {{ $ratingCategory?->name ?? 'Unknown Category' }}
            </h5>
          </div>

          <div class="card-body p-0">
            <table class="table mb-0 table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Player</th>
                  <th>Scores</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($rankings as $i => $row)
                  @php
                    $best = collect($row['best']);
                  ?>
                  <tr>
                    <td><?php echo e($i + 1); ?></td>
                    <td><?php echo e($row['player']->name); ?> <?php echo e($row['player']->surname); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ratingCategory): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $row['player']->id,'context' => $ratingCategory]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row['player']->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($ratingCategory)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['scores']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scoreData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                          // Check if this score is one of the best (to color it)
                          $isBest = $best->contains(function ($b) use ($scoreData) {
                            return $b['score'] === $scoreData['score'] && $b['event'] === $scoreData['event'];
                          });

                          if ($isBest) {
                            // remove one match to avoid double matches with duplicates
                            $best = $best->reject(function ($b) use ($scoreData) {
                              return $b['score'] === $scoreData['score'] && $b['event'] === $scoreData['event'];
                            });
                          }
                        ?>
                        <span class="badge <?php echo e($isBest ? 'bg-success' : 'bg-secondary'); ?> me-1 mb-1">
                          <?php echo e($scoreData['score']); ?>

                          <div class="d-block small" style="font-size: 0.7em;"></div>
                        </span>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td><strong><?php echo e($row['total']); ?></strong></td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="col-12">
        <div class="alert alert-warning">No rankings available yet.</div>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\rankings\rankings.blade.php ENDPATH**/ ?>