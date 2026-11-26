
<?php $__env->startSection('title', 'Account statement'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('account.statement-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => 'Account statement','eyebrow' => 'Cape Tennis','subtitle' => 'Your entry fees, clothing payments and completed refunds.','icon' => 'ti-file-invoice']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Account statement','eyebrow' => 'Cape Tennis','subtitle' => 'Your entry fees, clothing payments and completed refunds.','icon' => 'ti-file-invoice']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<div class="card mb-4 statement-controls"><div class="card-body">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adminView ?? false): ?>
        <div class="alert alert-info"><strong>Super Admin: viewing another account</strong><br><?php echo e($account->name); ?> · <?php echo e($account->email); ?> · Account #<?php echo e($account->id); ?></div>
        <a href="<?php echo e(route('superadmin.account-statements.index')); ?>" class="btn btn-outline-secondary mb-3">Choose another user</a>
    <?php else: ?>
        <a href="<?php echo e(route('backend.dashboard')); ?>" class="btn btn-outline-secondary mb-3">Back to my account</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="get" action="<?php echo e($statementUrl ?? route('account.statement')); ?>" class="row g-3 align-items-end">
        <div class="col-12 col-lg-6"><label for="statement-period" class="form-label">Statement period</label><select id="statement-period" name="all_time" class="form-select"><option value="1" <?php if($allTime ?? false): echo 'selected'; endif; ?>>All time — from the start</option><option value="0" <?php if(! ($allTime ?? false)): echo 'selected'; endif; ?>>Selected recorded dates</option></select></div>
        <div class="col-12 col-lg-6"><label for="statement-category" class="form-label">Payments included</label><select id="statement-category" name="category" class="form-select"><option value="all" <?php if($category === 'all'): echo 'selected'; endif; ?>>Entry fees and clothing</option><option value="entry" <?php if($category === 'entry'): echo 'selected'; endif; ?>>Entry fees only</option><option value="clothing" <?php if($category === 'clothing'): echo 'selected'; endif; ?>>Clothing only</option></select></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-from" class="form-label">Recorded from</label><input id="statement-from" class="form-control" type="date" name="from" required value="<?php echo e(old('from', $from)); ?>"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-to" class="form-label">Recorded to</label><input id="statement-to" class="form-control" type="date" name="to" required value="<?php echo e(old('to', $to)); ?>"></div>
        <div class="col-12 col-sm-6 col-lg-3"><label for="statement-player" class="form-label">Player name (optional)</label><input id="statement-player" class="form-control" name="player" maxlength="100" value="<?php echo e(old('player', $player)); ?>" placeholder="All players you paid for"></div>
        <div class="col-12 col-lg-3 d-flex flex-wrap gap-2"><button class="btn btn-primary">View</button><button name="format" value="pdf" class="btn btn-outline-primary">Download PDF</button><button name="format" value="csv" class="btn btn-outline-secondary">CSV</button></div>
    </form>
    <p class="text-muted small mt-3 mb-0">All time includes recorded payments from the start. For a selected period, choose up to two years. Historical records with unavailable payment dates appear separately below.</p>
</div></div>
<section class="card ct-statement-document"><div class="card-body p-3 p-sm-4">
    <?php echo $__env->make('account.statement-heading', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('account.statement-warnings', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="statement-section">
        <h2 class="statement-section-title">Payment transactions</h2>
        <p class="statement-caption"><?php echo e($payments->total()); ?> records. Totals cover all matching pages.</p>
        <div class="table-responsive"><?php echo $__env->make('account.statement-table', ['statementRows' => $payments], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
        <div class="statement-pagination mt-3"><?php echo e($payments->links('pagination::bootstrap-5')); ?></div>
    </div>
    <div class="statement-section">
        <p class="statement-caption"><?php echo e($historicalPayments->total()); ?> historical paid records shown separately; excluded from known totals.</p>
        <?php echo $__env->make('account.statement-history', ['historyRows' => $historicalPayments], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="statement-pagination mt-3"><?php echo e($historicalPayments->links('pagination::bootstrap-5')); ?></div>
    </div>
    <div class="statement-notes"><?php echo $__env->make('account.statement-notes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    <p class="statement-footer">Generated <?php echo e($generated_at->format('Y-m-d H:i T')); ?> · Cape Tennis · Account #<?php echo e($account->id); ?></p>
</div></section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\account\statement.blade.php ENDPATH**/ ?>