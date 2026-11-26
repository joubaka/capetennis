<?php ($rand = fn($cents) => 'R'.number_format($cents / 100, 2)); ?>
<section class="card mb-4" data-financial-projection aria-label="Projected event finances">
<div class="card-body">
<h2 class="h5">Your projected event finances</h2>
<p class="small text-muted">Assumes every place in your entry limits is paid. Processing is fixed at 5% and Cape Tennis at R15 per entry. Rental covers assigned courts only.</p>
<div data-projection-unavailable role="status" <?php if($projection): ?> hidden <?php endif; ?>>Enter valid courts, entry limits, fees and costs to see an updated projection.</div>
<div data-projection-values <?php if(!$projection): ?> hidden <?php endif; ?>>
<div class="table-responsive"><table class="table"><thead><tr><th>Category</th><th>Entries</th><th>Entry fee</th><th>Collected</th><th>Processing</th><th>Cape Tennis</th><th>Retained</th></tr></thead><tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Marketing\PanarottisCalculator::CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><th scope="row"><?php echo e($category); ?></th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['entries', 'fee', 'gross', 'processing', 'platform', 'retained']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td class="text-nowrap" data-projection-cell="<?php echo e($i); ?>-<?php echo e($field); ?>"><?php echo e($projection ? ($field === 'entries' ? $projection['rows'][$i][$field] : $rand($projection['rows'][$i][$field])) : '—'); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<tr class="fw-bold"><th>Total</th><td data-projection-total="entries"><?php echo e($projection['totals']['entries'] ?? '—'); ?></td><td>—</td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['gross', 'processing', 'platform', 'retained']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td class="text-nowrap" data-projection-total="<?php echo e($field); ?>"><?php echo e($projection ? $rand($projection['totals'][$field]) : '—'); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
</tbody></table></div>
<dl class="row mt-3">
<dt class="col-7">Convenor</dt><dd class="col-5 text-end" data-projection-total="convenor"><?php echo e($projection ? $rand($projection['totals']['convenor']) : '—'); ?></dd>
<dt class="col-7">Assigned-court rental</dt><dd class="col-5 text-end" data-projection-total="rental"><?php echo e($projection ? $rand($projection['totals']['rental']) : '—'); ?></dd>
<dt class="col-7">Other expenses</dt><dd class="col-5 text-end" data-projection-total="other_costs"><?php echo e($projection ? $rand($projection['totals']['other_costs']) : '—'); ?></dd>
</dl>
<p class="small text-muted" data-projection-rental-formula><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projection): ?><?php echo e($projection['assumptions']['courts']); ?> assigned courts × <?php echo e($projection['assumptions']['hours']); ?> hours × R<?php echo e($projection['assumptions']['rental']); ?> per court/hour.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
<div class="p-3 rounded <?php echo e($projection && $projection['totals']['remaining'] < 0 ? 'bg-label-danger' : 'bg-label-primary'); ?>" data-projection-result>
<div class="fw-semibold" data-projection-result-label><?php echo e($projection && $projection['totals']['remaining'] < 0 ? 'Projected loss' : 'Academy has left'); ?></div>
<div class="fs-3 fw-bold" data-projection-total="remaining"><?php echo e($projection ? $rand($projection['totals']['remaining']) : '—'); ?></div>
</div>
</div>
<p class="small text-muted mt-3 mb-0">Planning only, before any expenses you have not included. The 5% is a processing allowance, not a verified provider tariff; actual schedule and rental arrangements still need review.</p>
</div></section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\host-events\financial-projection.blade.php ENDPATH**/ ?>