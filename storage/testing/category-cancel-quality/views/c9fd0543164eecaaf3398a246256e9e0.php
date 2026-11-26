<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'TeamSheet tennis results'); ?>
<?php $__env->startSection('content'); ?>
<div class="operational-page" data-teamsheet-tennis="verified-restricted-v1">
    <div class="card mb-3"><div class="card-body">
        <h1 class="h3">TeamSheet tennis results</h1>
        <p>Verified tennis singles results from TeamSheet ladders, leagues and tournaments can contribute to the shared player rating through a restricted read-only connection. Doubles, walkovers and disputed scores are excluded.</p>
        <p>Connect both player identities and verify an actual playing date. Scheduled dates and score-entry times need evidence. Possible duplicates are held during the rating calculation; a connected player and date alone do not guarantee inclusion.</p>
        <p>Results stop contributing after 24 hours without a successful sync. Updates to ratings run through the existing saved-rating workflow.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="alert alert-success" role="status"><?php echo e(session('success')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$ready): ?>
            <p class="alert alert-warning">The reviewed TeamSheet import migration has not been applied in this environment.</p>
        <?php elseif(!$configured): ?>
            <p class="alert alert-info">The TeamSheet restricted results connection has not been configured.</p>
        <?php else: ?>
            <p><strong><?php echo e($fresh ? 'Verified snapshot current' : 'Verified snapshot unavailable or stale'); ?></strong> · Last successful sync: <?php echo e($feed?->last_success_at ?? 'Never'); ?></p>
            <form method="post" action="<?php echo e(route('backend.integrations.teamsheet.tennis.sync')); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" style="min-height:44px">Sync verified results</button></form>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ready): ?>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h4">Connect a player identity</h2>
            <p>Use identity evidence to connect an imported TeamSheet player ID to an existing Cape Tennis profile. Names alone are insufficient; this creates no account ownership.</p>
            <form method="post" action="<?php echo e(route('backend.integrations.teamsheet.tennis.link')); ?>" class="row g-3"><?php echo csrf_field(); ?>
                <div class="col-sm-6"><label for="ts-player" class="form-label">TeamSheet player ID</label><input id="ts-player" name="teamsheet_player_id" type="number" min="1" required class="form-control" value="<?php echo e(old('teamsheet_player_id')); ?>"></div>
                <div class="col-sm-6"><label for="ct-player" class="form-label">Cape Tennis player ID</label><input id="ct-player" name="player_id" type="number" min="1" required class="form-control" value="<?php echo e(old('player_id')); ?>"></div>
                <div class="col-12"><label for="link-reason" class="form-label">Identity evidence</label><textarea id="link-reason" name="reason" minlength="10" maxlength="1000" required class="form-control"><?php echo e(old('reason')); ?></textarea></div>
                <div class="col-12"><button class="btn btn-primary" style="min-height:44px">Connect reviewed identity</button></div>
            </form>
        </div></div>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h4">Reviewed player connections</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <details class="border rounded p-3 mb-2"><summary style="min-height:44px">TeamSheet #<?php echo e($link->teamsheet_player_id); ?> → <?php echo e($link->name); ?> <?php echo e($link->surname); ?> (Cape Tennis #<?php echo e($link->player_id); ?>)</summary>
                    <p class="mt-2"><?php echo e($link->reason); ?></p>
                    <form method="post" action="<?php echo e(route('backend.integrations.teamsheet.tennis.unlink', $link->id)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <label class="form-label" for="unlink-<?php echo e($link->id); ?>">Reason for removing this connection</label><input id="unlink-<?php echo e($link->id); ?>" name="reason" minlength="10" maxlength="1000" required class="form-control mb-2">
                        <button class="btn btn-outline-danger" style="min-height:44px">Remove connection</button>
                    </form>
                </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p>No player connections yet.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php echo e($links->appends(request()->except('links_page'))->links()); ?>

        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h4">Imported verified results</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $matches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php ($state = $states[$match->id]); ?>
                <article class="border rounded p-3 mb-3" style="overflow-wrap:anywhere">
                    <h3 class="h6"><?php echo e($match->source_match_id); ?></h3>
                    <p>TeamSheet players #<?php echo e($state['payload']['side1']['teamsheet_player_id']); ?> / #<?php echo e($state['payload']['side2']['teamsheet_player_id']); ?> · <?php echo e(ucfirst($state['payload']['competition']['type'])); ?> #<?php echo e($state['payload']['competition']['id']); ?></p>
                    <p>Score: <?php echo e(collect($state['payload']['sets'])->map(fn($set) => $set['side1_games'].'-'.$set['side2_games'])->implode(' ')); ?> · Source date: <?php echo e(substr($state['payload']['played_at'], 0, 10)); ?> (<?php echo e($state['payload']['date_precision']); ?>)</p>
                    <p><strong><?php echo e(!$fresh ? 'Held: source unavailable or stale' : ($state['reason'] ?? 'Ready for duplicate checks at the next rating update')); ?></strong></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($state['date']): ?>
                        <p>Actual playing date: <?php echo e($state['date']); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$match->retracted): ?>
                        <details><summary style="min-height:44px">Review actual playing date</summary>
                            <form method="post" action="<?php echo e(route('backend.integrations.teamsheet.tennis.date', $match->id)); ?>" class="mt-2"><?php echo csrf_field(); ?>
                                <input type="hidden" name="source_version" value="<?php echo e($match->source_version); ?>">
                                <label for="date-<?php echo e($match->id); ?>" class="form-label">Actual match date</label><input type="date" id="date-<?php echo e($match->id); ?>" name="played_on" required max="<?php echo e(today()->toDateString()); ?>" class="form-control mb-2" value="<?php echo e($state['date']); ?>">
                                <label for="date-reason-<?php echo e($match->id); ?>" class="form-label">Playing-date evidence</label><textarea id="date-reason-<?php echo e($match->id); ?>" name="reason" minlength="10" maxlength="1000" required class="form-control mb-2"></textarea>
                                <button class="btn btn-outline-primary" style="min-height:44px">Save reviewed date</button>
                            </form>
                        </details>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p>No verified results have been imported.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php echo e($matches->appends(request()->except('page'))->links()); ?>

        </div></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\integrations\teamsheet-tennis.blade.php ENDPATH**/ ?>