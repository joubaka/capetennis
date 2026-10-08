<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Player performance pilot'); ?>

<?php $__env->startSection('content'); ?>
<div class="card mb-4">
    <div class="card-body">
        <h1 class="h3">Player performance pilot</h1>
        <a class="btn btn-primary mb-3" href="<?php echo e(route('backend.player-performance.directory')); ?>">Find any player's rating</a>
        <p class="alert alert-warning text-dark">
            Private Super Admin preview. Every score is provisional and uncalibrated. This measures tournament finishes,
            not an official UTR or a universal playing ability rating. Compare only equivalent age/category cohorts.
            Doubles scores reflect partnership finishes.
        </p>
        <form method="get" action="<?php echo e(route('backend.player-performance.index')); ?>" class="row g-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['a' => 'A category (required to calculate)', 'b' => 'Comparable B category (optional)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tier => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="<?php echo e($tier); ?>_category_id"><?php echo e($label); ?></label>
                    <select class="form-select" name="<?php echo e($tier); ?>_category_id" id="<?php echo e($tier); ?>_category_id">
                        <option value="">Choose category</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories->take(500); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($category->id); ?>" <?php if((string)($settings[$tier.'_category_id'] ?? '') === (string)$category->id): echo 'selected'; endif; ?>>
                                <?php echo e($category->name); ?> (#<?php echo e($category->id); ?>)
                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="col-12 col-md-4">
                <label class="form-label" for="discipline">Discipline</label>
                <select name="discipline" id="discipline" class="form-select">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['singles', 'doubles']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $discipline): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option <?php if($settings['discipline'] === $discipline): echo 'selected'; endif; ?>><?php echo e($discipline); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label" for="months">Recent months (1–24)</label>
                <input class="form-control" id="months" name="months" type="number" min="1" max="24" value="<?php echo e($settings['months']); ?>">
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label" for="as_of">As of</label>
                <input class="form-control" id="as_of" name="as_of" type="date" max="<?php echo e(today()->toDateString()); ?>" value="<?php echo e($settings['as_of']); ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Preview scores</button>
            </div>
        </form>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div class="alert alert-danger mt-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div><?php echo e($error); ?></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categories->count() > 500): ?>
            <p class="mt-3">Only the first 500 categories are listed.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <details class="mt-3">
            <summary>Proposed rules and exclusions</summary>
            <p class="mt-2">
                A finishes score 50–100; B finishes score 0–50. These bands are arbitrary pilot assumptions,
                not calibrated player strengths. Score = band minimum + 50 × (ranked field − position) / (ranked field − 1).
                Recent results receive weight 0.5^(days since event end / 180); the displayed score is their weighted
                average, rounded once to one decimal.
            </p>
            <p>
                Only published events and published results ending in the selected window count. The latest saved
                correction is used. Every field must have at least two unique consecutive positions and match its
                saved ranked registrations to recorded category membership. Extra unranked entrants do not erase published finishes. Missing ranked membership, repeated players, incomplete
                positions, and mixed disciplines exclude the whole field. Ranked field means saved finishing records,
                not an independently verified starter count. Qualification is not assessed and earns no bonus.
                Team-event results are excluded, including their singles subdraws. No existing ranking or player
                record is changed.
            </p>
        </details>
    </div>
</div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preview['truncated']): ?>
        <div class="alert alert-warning text-dark">
            Preview limited to the most recent 50 category fields. Older fields within the window were omitted;
            scores describe this limited sample.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p>
        <?php echo e($preview['summary']['included_fields']); ?> eligible fields · <?php echo e($preview['summary']['skipped_fields']); ?> skipped fields ·
        <?php echo e($preview['summary']['scored_players']); ?> scored players of <?php echo e($players->total()); ?> with saved results.
    </p>
    <p><?php echo e($settings['discipline']); ?> cohort preview · <?php echo e($players->total()); ?> players · qualification history: not assessed.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5"><a href="<?php echo e(route('backend.player-performance.show', $player['id'])); ?>"><?php echo e($player['name']); ?></a></h2>
                <p>
                    <strong><?php echo e($player['score'] === null ? 'No eligible score' : number_format($player['score'], 1).'/100 — provisional'); ?></strong> ·
                    <?php echo e($player['count']); ?> contributing tournament <?php echo e($player['count'] === 1 ? 'result' : 'results'); ?> ·
                    Last eligible event: <?php echo e($player['last_played'] ?? 'None'); ?>

                </p>
                <details>
                    <summary>Finishes and scoring evidence (<?php echo e($player['results']->count()); ?>)</summary>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-nowrap">Event / date</th>
                                    <th class="text-nowrap">Category / division</th>
                                    <th class="text-nowrap">Finish / ranked field</th>
                                    <th class="text-nowrap">Points / weight</th>
                                    <th class="text-nowrap">Included / skipped</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $player['results']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td><?php echo e($result['event']); ?><br><?php echo e($result['date']); ?></td>
                                        <td><?php echo e($result['category']); ?><br><?php echo e($result['tier']); ?></td>
                                        <td><?php echo e($result['position']); ?> / <?php echo e($result['field_capped'] ? 'at least ' : ''); ?><?php echo e($result['field_size']); ?></td>
                                        <td><?php echo e($result['points'] === null ? '—' : number_format($result['points'], 1)); ?> / <?php echo e(number_format($result['weight'], 3)); ?></td>
                                        <td><?php echo e($result['reason'] ?? 'Included'); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="alert alert-info">No player results in this published cohort and date window.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo e($players->links()); ?>

    <details class="card p-3 mt-3">
        <summary>Excluded fields, including fields without players</summary>
        <ul class="mt-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $preview['evidence']->whereNotNull('reason')->unique(fn ($row) => $row['event_id'].':'.$row['category_id']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $result): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <li><?php echo e($result['event']); ?> · <?php echo e($result['date']); ?> · <?php echo e($result['category']); ?> (<?php echo e($result['tier']); ?>): <?php echo e($result['reason']); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <li>No fields excluded.</li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
    </details>
<?php else: ?>
    <p>
        Choose the A category to begin. Pair B only when it represents the same age and competition cohort;
        division labels are selected explicitly and never inferred from names.
    </p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\player-performance\index.blade.php ENDPATH**/ ?>