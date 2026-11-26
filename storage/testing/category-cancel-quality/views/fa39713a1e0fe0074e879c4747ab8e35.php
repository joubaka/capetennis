<?php
    use App\Http\Controllers\Backend\CavaliersTrialRosterController;
    use App\Services\CavaliersTrialCandidates;
?>
<section class="card mb-4" data-cavaliers-roster
    data-selected-limit="<?php echo e(CavaliersTrialRosterController::SELECTED_LIMIT); ?>"
    data-reserve-limit="<?php echo e(CavaliersTrialRosterController::RESERVE_LIMIT); ?>">
    <div class="card-body">
        <h4 class="card-title mb-1">Trials roster draft</h4>
        <p class="text-muted">Private working list. Review selected players and five ordered reserves within each division's limit.</p>
        <div class="small fw-semibold mb-2" id="cavaliers-division-label">Division</div>
        <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-labelledby="cavaliers-division-label">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $division): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button type="button" class="btn btn-sm btn-outline-primary" data-division-choice="<?php echo e($division->id); ?>" aria-pressed="false">
                    <?php echo e($division->category->name); ?>

                </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <select data-division hidden>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $division): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($division->id); ?>"
                    data-selected-limit="<?php echo e(CavaliersTrialRosterController::selectedLimit($division)); ?>"
                    data-draft-url="<?php echo e(route('backend.cavaliers-roster.show', [$event, $division])); ?>"
                    data-nominations-url="<?php echo e(route('backend.cavaliers-roster.nominations', [$event, $division])); ?>"
                    data-search-url="<?php echo e(route('backend.cavaliers-roster.players', [$event, $division])); ?>"
                    <?php if((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT): ?>
                        data-candidate-url="<?php echo e(route('backend.cavaliers-roster.candidates', [$event, $division])); ?>"
                        data-setup-url="<?php echo e(route('backend.cavaliers-roster.setup', [$event, $division])); ?>"
                        data-pdf-url="<?php echo e(route('backend.cavaliers-roster.pdf', [$event, $division])); ?>"
                    <?php endif; ?>>
                    <?php echo e($division->category->name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
        <p class="small mb-2" data-status role="status" aria-live="polite"></p>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3 d-none" data-reload>Reload saved draft</button>
        <div data-editor hidden>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-save disabled>Save draft</button>
                <button type="button" class="btn btn-sm btn-primary" data-publish disabled>Publish selected players</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-unpublish disabled>Unpublish list</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT): ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo e(route('backend.cavaliers-report.index', $event)); ?>">Share nomination report</a>
                    <a class="btn btn-sm btn-outline-primary" data-export-pdf hidden>Export saved PDF</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-email-nominees data-bs-target="#sendMailModal"
                    data-bs-toggle="modal">Email event nominees</button>
            </div>
            <p class="small text-muted" data-publication-status></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT): ?>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <span class="fw-semibold">Step 1 · Inter-Districts</span>
                    <button type="button" class="btn btn-outline-secondary" data-open-setup aria-expanded="false" aria-controls="cavaliers-roster-setup">Setup</button>
                    <button type="button" class="btn btn-outline-primary" data-review-candidates>Review candidates</button>
                </div>
                <form class="border rounded p-3 mb-3" id="cavaliers-roster-setup" data-candidate-setup hidden>
                    <div class="row g-3 mb-3">
                        <fieldset class="col-12 col-md-6" data-setup-fieldset>
                            <legend class="h6">Include players from</legend>
                            <p class="small text-muted">Only published rosters for this division are used.</p>
                            <div data-candidate-regions></div>
                        </fieldset>
                        <fieldset class="col-12 col-md-6" data-setup-fieldset>
                            <legend class="h6">Exclude results against</legend>
                            <p class="small text-muted">Matches involving these regions are left out of the band calculation. A region can still supply candidates.</p>
                            <div data-excluded-regions></div>
                        </fieldset>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Save setup</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-close-setup>Cancel</button>
                </form>
                <section class="border rounded p-3 mb-3" data-candidate-review aria-labelledby="cavaliers-candidate-heading" hidden>
                    <h5 id="cavaliers-candidate-heading" class="h6">Review Inter-Districts and Platteland candidates</h5>
                    <p class="small text-muted">Step 1 uses Inter-Districts results with the Platteland band rules and includes source reserves if you manage that event. Step 2 adds locked Platteland reserves, then additional candidates in the saved order. A new draft starts with suggested players checked. A saved draft starts with its own players checked. Check or uncheck players, then apply the changes and save the draft.</p>
                    <p class="small text-muted" data-candidate-region-summary></p>
                    <p class="small text-warning" data-candidate-notice hidden></p>
                    <p class="small text-warning" data-platteland-notice hidden></p>
                    <ol class="list-group list-group-numbered mb-3" data-candidate-list></ol>
                    <p class="small mb-2" data-candidate-count aria-live="polite"></p>
                    <button type="button" class="btn btn-sm btn-primary" data-add-candidates>Apply to draft</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-close-candidates>Close review</button>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <form data-search class="row g-2 align-items-end mb-2">
                <div class="col-12 col-sm">
                    <label class="form-label" for="cavaliers-roster-search">Find a player</label>
                    <input class="form-control" id="cavaliers-roster-search" data-search-input type="search"
                        placeholder="Name or surname" minlength="2" maxlength="80" required autocomplete="off">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </div>
            </form>
            <ul class="list-group mb-3 d-none" data-search-results aria-label="Player search results"></ul>
            <div class="row g-3 mb-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['selected' => 'Selected', 'reserves' => 'Reserves']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <section class="col-12 <?php echo e($key === 'selected' ? 'col-lg-7' : 'col-lg-5'); ?>" aria-label="<?php echo e($label); ?>">
                        <h5 class="h6 d-flex flex-wrap gap-2 align-items-center rounded px-2 py-2 mb-2 <?php echo e($key === 'selected' ? 'bg-label-primary' : 'bg-label-info'); ?>">
                            <?php echo e($label); ?> <span class="badge bg-white text-dark" data-count="<?php echo e($key); ?>"></span>
                        </h5>
                        <ol class="list-group list-group-numbered" data-list="<?php echo e($key); ?>"></ol>
                        <p class="text-muted small mb-0" data-empty="<?php echo e($key); ?>" hidden>No players added.</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'reserves'): ?>
                            <h6 class="mt-3 mb-2 rounded px-2 py-2 bg-label-danger">
                                Declined <span class="badge bg-white text-dark" data-declined-count></span>
                            </h6>
                            <ul class="list-group" data-declined-list></ul>
                            <p class="text-muted small mb-0" data-declined-empty>No declines recorded.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'reserves' && (int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT): ?>
                            <h6 class="mt-3 mb-2 rounded px-2 py-2 bg-label-warning">Next 10 in merit order</h6>
                            <p class="text-muted small mb-2">Players outside the selected and reserve lists. Add, move and return actions save immediately, including any current draft edits. New players are added at the bottom of the chosen list. Publishing remains a separate step.</p>
                            <p class="text-muted small mb-0" data-merit-message role="status"></p>
                            <ol class="list-group list-group-numbered" data-merit-list aria-label="Next players in merit order"></ol>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </section>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <form class="border rounded p-3 mb-3" data-swap hidden>
                <label class="form-label" for="cavaliers-roster-swap" data-swap-label>Swap with</label>
                <select class="form-select mb-2" id="cavaliers-roster-swap" data-swap-target required></select>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">Swap players</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-cancel-swap>Cancel</button>
                </div>
            </form>
            <dialog class="border-0 rounded shadow p-3" style="width: min(36rem, 90vw); max-height: 80vh" data-publication-review aria-labelledby="cavaliers-publication-title">
                <h5 id="cavaliers-publication-title">Review public nominations</h5>
                <div data-publication-changes></div>
                <form method="dialog" class="d-flex flex-wrap gap-2 mt-3">
                    <button type="submit" value="cancel" class="btn btn-sm btn-outline-secondary" autofocus>Cancel</button>
                    <button type="submit" value="publish" class="btn btn-sm btn-primary">Publish list</button>
                </form>
            </dialog>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT): ?>
                <dialog class="border-0 rounded shadow p-3" style="width: min(32rem, 90vw)" data-player-info aria-labelledby="cavaliers-player-info-title">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <h5 id="cavaliers-player-info-title" class="h6 mb-0 text-break"></h5>
                        <form method="dialog">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" autofocus>Close</button>
                        </form>
                    </div>
                    <div class="text-break" data-player-info-content></div>
                </dialog>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</section>
<script defer src="<?php echo e(asset('assets/js/cavaliers-trial-roster.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/cavaliers-trial-roster.js'))); ?>"></script>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\nominations\cavaliers-roster.blade.php ENDPATH**/ ?>