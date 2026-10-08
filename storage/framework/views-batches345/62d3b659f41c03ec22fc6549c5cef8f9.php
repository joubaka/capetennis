
<?php $__env->startSection('title', 'Team scoring rules – '.$event->name); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <h1 class="h4"><?php echo e($event->name); ?>: team scoring rules</h1>
  <p>These defaults apply to new draws. Existing draws retain the rules and pairings used when they were generated.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form id="team-event-rules-form" method="POST" action="<?php echo e(route('backend.team-rules.update', $event)); ?>">
    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5">Points per rubber</h2>
      <p class="text-muted">Set wins required determines when a match is complete. Straight wins award the straight-win points; a deciding-set win and loss use their own points. Close losses use the greater of normal loss points and close-loss points.</p>
      <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Rubber</th><th>Sets to win</th><th>Straight win</th><th>Deciding win</th><th>Loss</th><th>Deciding loss</th><th>Close loss</th><th>Close game margin</th></tr></thead>
        <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rules['rubbers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $values): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr>
          <th><?php echo e(ucwords(str_replace('_', ' ', $type))); ?></th>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['sets_to_win','straight_win','deciding_win','loss','deciding_loss','close_loss','close_game_margin']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php ($value = $values[$field]); ?><td>
            <input class="form-control" style="min-width:80px" type="number" min="<?php echo e($field === 'sets_to_win' ? 1 : 0); ?>" max="<?php echo e($field === 'sets_to_win' ? 2 : 100); ?>" step="<?php echo e(in_array($field, ['sets_to_win','close_game_margin']) ? 1 : '0.5'); ?>" required
              aria-label="<?php echo e(ucwords(str_replace('_',' ',$type.' '.$field))); ?>" name="rules[rubbers][<?php echo e($type); ?>][<?php echo e($field); ?>]" value="<?php echo e(old('rules.rubbers.'.$type.'.'.$field, $value)); ?>">
          </td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
      </table></div>
    </div></div>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5">Team ties and ranking</h2>
      <p class="text-muted">A tie winner is the team winning more rubbers. A level total is a drawn tie. Optional tie points are added to rubber points.</p>
      <div class="row g-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['tie_win','tie_draw','tie_loss']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-sm-4"><label class="form-label" for="<?php echo e($field); ?>"><?php echo e(ucwords(str_replace('_',' ',$field))); ?> points</label><input id="<?php echo e($field); ?>" class="form-control" type="number" min="0" max="100" step="0.5" required name="rules[<?php echo e($field); ?>]" value="<?php echo e(old('rules.'.$field,$rules[$field])); ?>"></div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
      <p class="mt-3">Select ranking criteria in priority order. Teams still tied share the same rank.</p>
      <div class="row g-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = range(0,4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-sm-6 col-lg"><label class="form-label" for="order<?php echo e($index); ?>">Priority <?php echo e($index+1); ?></label><select class="form-select" id="order<?php echo e($index); ?>" name="rules[standings_order][]">
          <option value="">Unused</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orderOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($option); ?>" <?php if(old('rules.standings_order.'.$index,$rules['standings_order'][$index] ?? '') === $option): echo 'selected'; endif; ?>><?php echo e(ucwords(str_replace('_',' ',$option))); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select></div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
      <button class="btn btn-primary mt-3">Save event rules</button>
    </div></div>
  </form>
  <div class="card"><div class="card-body">
    <h2 class="h5">Create a pairing format</h2>
    <p>Start with a six-player or eight-player draft, or build your own. Select ranked roster positions for each side. Saving creates a new format; existing draws retain their pairings.</p>
    <div class="row g-2 mb-3 align-items-end"><div class="col-sm-8"><label class="form-label" for="format-preset">Starting preset</label><select id="format-preset" class="form-select">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $presets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>"><?php echo e($preset['name']); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select></div><div class="col-sm-4"><button type="button" class="btn btn-outline-primary" id="apply-preset">Load preset into draft</button></div></div>
    <p class="text-muted">Presets are editable starting points, with same-rank singles, crossed reverse singles and adjacent doubles. Check your event's competition rules before saving.</p>
    <form id="team-format-form" action="<?php echo e(route('team-draw.formats.store',$event)); ?>">
      <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="format-name">Format name</label><input id="format-name" name="name" class="form-control" required></div>
      <div class="col-sm-3"><label class="form-label" for="roster-min">Minimum roster</label><input id="roster-min" name="min_roster_size" type="number" min="1" max="12" value="6" class="form-control" required></div>
      <div class="col-sm-3"><label class="form-label" for="roster-max">Maximum roster</label><input id="roster-max" name="max_roster_size" type="number" min="1" max="12" value="6" class="form-control" required></div></div>
      <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="allow_player_reuse" checked> Players may play more than one rubber</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="is_default"> Use as this event's default format</label>
      <div id="format-rubbers" class="mt-3" aria-label="Rubber pairing draft"></div>
      <p id="format-summary" class="text-muted" aria-live="polite"></p>
      <div id="format-warnings" class="alert alert-warning d-none" role="status"></div>
      <button class="btn btn-outline-secondary" type="button" id="add-rubber">Add rubber</button>
      <button class="btn btn-primary" type="submit">Create format</button>
      <p id="format-message" class="mt-3" role="status"></p>
    </form>
  </div></div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script type="application/json" id="team-format-presets"><?php echo json_encode($presets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="<?php echo e(asset('js/team-event-rules.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-draw\rules.blade.php ENDPATH**/ ?>