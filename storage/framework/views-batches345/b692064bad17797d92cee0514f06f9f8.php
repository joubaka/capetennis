<?php $__env->startSection('title', 'Team Fixtures'); ?>

<?php $__env->startSection('content'); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->event->standings_published): ?>
<div class="container-xxl pt-3 d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.team-draw.standings', $draw)); ?>">Team standings</a><a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.events.standings', $draw->event)); ?>">Full event standings</a></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="container-xxl py-4" <?php if($draw->published): ?> data-live-results="team-fixture-list" <?php endif; ?>>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
            Team Fixtures
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($draw) && $draw): ?>
                <small class="text-muted">— <?php echo e($draw->drawName); ?></small>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </h2>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            
                            <th class="d-none d-sm-table-cell">#</th>
                            <th class="d-none d-sm-table-cell">Round</th>
                            
                            
                            <th class="position-relative">
                                Scheduled
                                <span 
                                    class="position-absolute top-0 end-0 me-1 mt-1 d-none d-md-inline"
                                    style="font-size: 0.9rem; cursor: pointer;"
                                    title="Shows the day and time (hover for full date)">
                                    <i class="bi bi-info-circle text-info"></i>
                                </span>
                            </th>

                            <th class="d-none d-md-table-cell">Match #</th>
                            <th class="text-end">Home</th>
                            <th class="p-0"></th>
                            <th>Away</th>
                            <th>Result</th>
                            <th class="d-none d-lg-table-cell">Venue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fixtures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $display = $fx->scheduled_at ?? null;
                            $result = $fx->fixtureResults->count()
                                ? $fx->fixtureResults->map(fn($r) => "{$r->team1_score}-{$r->team2_score}")->implode(', ')
                                : null;

                            $homeClass = ''; $awayClass = '';
                            if ($fx->fixtureResults->count()) {
                                $winner = $fx->winnerSide();
                                if ($winner === 'home') {
                                    $homeClass = 'winner-home'; $awayClass = 'loser-home';
                                } elseif ($winner === 'away') {
                                    $homeClass = 'loser-home'; $awayClass = 'winner-home';
                                }
                            }
                        ?>
                        <tr>
                            <td class="text-muted d-none d-sm-table-cell"><?php echo e($fx->id); ?></td>
                            <td class="fw-bold text-primary d-none d-sm-table-cell"><?php echo e($fx->round_nr ?? '—'); ?></td>
                            
                            
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($display): ?>
                                    <?php
                                        $carbon = \Carbon\Carbon::parse($display);
                                        $short = $carbon->format('D H:i');
                                        $full = $carbon->format('l Y-m-d H:i');
                                    ?>
                                    <span class="badge bg-light border text-dark text-nowrap" title="<?php echo e($full); ?>">
                                        <?php echo e($short); ?>

                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light border text-muted">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>

                            <td class="fw-bold text-secondary d-none d-md-table-cell"><?php echo e($fx->rubber_sequence ?: ($fx->home_rank_nr ?? '—')); ?></td>
                            
                            <td class="fw-semibold text-end <?php echo e($homeClass); ?> text-wrap" style="max-width:150px;">
                                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            </td>
                            <td class="text-center p-0" style="width:24px;">
                                <small class="text-muted">vs</small>
                            </td>
                            <td class="fw-semibold <?php echo e($awayClass); ?> text-wrap" style="max-width:150px;">
                                <?php echo $__env->make('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($result): ?>
                                    <span class="badge bg-success text-white px-2 py-1" style="font-weight:600;"><?php echo e($result); ?></span>
                                <?php else: ?>
                                    <span class="text-muted smaller">Pending</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <span class="badge bg-light border text-dark">
                                    <?php echo e(optional($fx->venue)->name ?? '—'); ?>

                                </span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No fixtures found.</td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<style>
.winner-home { background-color: rgba(40,167,69,.12)!important; }
.loser-home { background-color: rgba(220,53,69,.12)!important; }
.draw-cell { background-color: rgba(255,193,7,.12)!important; }
th, td { vertical-align: middle !important; }
.text-wrap { white-space: normal !important; word-break: break-word; }
.text-nowrap { white-space: nowrap !important; }

@media (max-width: 576px) {
    .table th, .table td { font-size: 0.75rem; padding: 0.4rem 0.2rem; }
    .table .badge { font-size: 0.75rem; padding: 0.2rem 0.3rem; }
    .card-body { padding: 0; }
    h2 { font-size: 1.1rem; }
    .smaller { font-size: 0.7rem; }
}
</style>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixtures\team-fixtures.blade.php ENDPATH**/ ?>