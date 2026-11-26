
<?php $__env->startSection('title', 'Round robin and placement playoffs'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.draw.partials.guide-visibility', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="container-xxl" id="placement-competition" data-url="<?php echo e(route('team-draw.competition.store', $event)); ?>">
  <style>#placement-competition .btn,#placement-competition select,#placement-competition input{min-height:44px}#placement-competition .match-rows{display:grid;gap:1rem}#placement-competition .match-row{padding:1rem;border:1px solid #d8dfe5;border-radius:.5rem}#placement-competition .row>*{min-width:0}</style>
  <a href="<?php echo e(route('headOffice.show', $event)); ?>" class="btn btn-link px-0 mb-2">← Back to event</a>
  <h1 class="h4"><?php echo e($event->name); ?>: create competition &amp; programme</h1>
  <ol class="d-flex flex-wrap gap-3 small" aria-label="Draw setup progress"><li>Competition: teams</li><li>Participants</li><li>Competition format</li><li>Match setup</li><li>Programme &amp; schedule</li><li>Review &amp; publish</li></ol>
  <p class="text-primary" data-competition-step role="status">Step 2 of 6 · Participants</p>
  <section data-competition-panel="2">
  <p>Choose an age group. Each region combines its boys and girls teams into one side, in one round-robin group. The top four qualify for 1st/2nd and 3rd/4th playoffs.</p>
  <p>Starting formats use each selected team's configured size: half as many doubles per gender, and mixed doubles for every boy and girl. Playoffs also start with singles for every player. Edit either phase before creating the group.</p>
  <div class="alert alert-info">Scoring: <strong>To be decided.</strong> You can create and schedule matches now. Confirm scoring before publishing ties, entering results or qualifying teams.</div>
  </section>
  <form id="competition-form">
    <?php echo csrf_field(); ?>
    <section data-competition-panel="2">
    <label class="form-label" for="competition-group">Age group</label>
    <select class="form-select mb-3" id="competition-group" required><option value="">Choose an age group</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($index); ?>"><?php echo e($group['label']); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select>
    <div id="competition-team-summary" class="mb-3" aria-live="polite"></div>
    </section>
    <section data-competition-panel="3" hidden>
      <h2 class="h5">Competition format</h2>
      <label class="form-check border rounded p-3"><input type="radio" class="form-check-input" checked name="competition_format" value="round_robin_playoffs"><span class="form-check-label">Round robin with placement playoffs</span></label>
      <p class="mt-3">Every regional side plays each other once. After every group encounter is complete and standings are confirmed, the top four qualify for 1st/2nd and 3rd/4th playoffs.</p>
    </section>
    <section data-competition-panel="4" hidden>
    <h2 class="h5">Match setup</h2>
    <p data-competition-selected-summary class="fw-semibold"></p>
    <p>Review the matches for both phases. Saving creates the group encounters and keeps the playoff format for qualification later. You will continue to the programme and schedule.</p>
    <p><strong>Scoring: to be decided.</strong> After saving, use “Confirm scoring before publication” in the setup guide to choose the scoring rules. Programme planning can continue first.</p>
    <div class="alert alert-secondary">Pairing positions: Boy 1 = 1, Girl 1 = 2, Boy 2 = 3, Girl 2 = 4, and so on. Players may play in more than one match. Each mixed pair must contain one boy and one girl.</div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['round_robin' => 'Round-robin matches per regional encounter', 'playoff' => 'Playoff matches per regional encounter']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $phase => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <details class="card mb-3"><summary class="card-header"><h2 class="h5 d-inline"><?php echo e($label); ?></h2> <span data-count="<?php echo e($phase); ?>"></span></summary><div class="card-body">
        <div class="match-rows" data-phase="<?php echo e($phase); ?>"></div>
        <button class="btn btn-outline-secondary mt-3" type="button" data-add="<?php echo e($phase); ?>">Add match</button>
      </div></details>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
    <div id="competition-message" role="status" aria-live="polite" class="mb-3"></div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-secondary" data-competition-back hidden>Back</button>
      <button type="button" class="btn btn-primary" data-competition-next>Continue</button>
      <button class="btn btn-primary" id="competition-submit" disabled hidden>Save matches &amp; continue to programme</button>
    </div>
  </form>
  <script type="application/json" id="competition-groups"><?php echo json_encode($groups, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('js/team-placement-competition.js')); ?>?v=<?php echo e(filemtime(public_path('js/team-placement-competition.js'))); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\team-draw\competition.blade.php ENDPATH**/ ?>