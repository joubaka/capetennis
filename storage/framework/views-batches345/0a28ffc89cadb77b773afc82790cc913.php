<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <title><?php echo e($title); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/public-draw.css')); ?>?v=<?php echo e(filemtime(public_path('css/public-draw.css'))); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/flexible-monrad.css')); ?>?v=<?php echo e(filemtime(public_path('css/flexible-monrad.css'))); ?>">
  <?php echo $__env->make('draw.partials.bracket-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</head>
<body class="fm-surface">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($draw)): ?>
  <div class="fm-public-shell">
    <?php echo $__env->make('frontend.draw.partials.public-header', ['publicDrawPrintButtonId' => 'fm-print'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <nav class="ct-public-draw-nav" role="tablist" aria-label="Draw sections">
      <button id="fm-schedule-tab" type="button" class="nav-link" role="tab" aria-selected="false"
              aria-controls="fm-schedule-panel" data-fm-public-tab="schedule">Schedule</button>
      <button id="fm-draw-tab" type="button" class="nav-link active" role="tab" aria-selected="true"
              aria-controls="fm-draw-panel" data-fm-public-tab="draw">Draw</button>
    </nav>
  </div>
  <section id="fm-schedule-panel" class="fm-public-panel fm-public-timetable" role="tabpanel" aria-labelledby="fm-schedule-tab" hidden>
    <h2>Match times &amp; courts</h2>
    <p class="fm-public-timetable-help">
      Find your name, then confirm the date, time, venue and court.
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->settings?->showsFirstMatchOnly()): ?> Later matches are marked <strong>Followed by</strong> until their times are released. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->oop_published): ?>
      <div id="fm-timetable"></div>
    <?php else: ?>
      <div class="fm-public-schedule-pending" role="status">
        <strong>Match times have not been published yet.</strong>
        <span>The draw is available now; return here after the organiser releases the schedule.</span>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
  <div id="fm-draw-panel" class="fm-public-panel" role="tabpanel" aria-labelledby="fm-draw-tab">
    <?php echo $__env->make('backend.draw.partials.flexible-monrad-editor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>
<?php else: ?>
  <header class="fm-header">
    <div><span class="fm-eyebrow">CAPE TENNIS / DRAW BUILDER</span><h1><?php echo e($title); ?></h1></div>
    <nav aria-label="Draw actions"><button id="fm-print" type="button">Print draw</button></nav>
  </header>
  <?php echo $__env->make('backend.draw.partials.flexible-monrad-editor', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($draw)): ?>
<script>
  (() => {
    const buttons = [...document.querySelectorAll('[data-fm-public-tab]')];
    const panels = {
      schedule: document.getElementById('fm-schedule-panel'),
      draw: document.getElementById('fm-draw-panel'),
    };

    function selectTab(name, updateHash = false) {
      if (!panels[name]) name = 'schedule';
      Object.entries(panels).forEach(([key, panel]) => { panel.hidden = key !== name; });
      buttons.forEach(button => {
        const active = button.dataset.fmPublicTab === name;
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', String(active));
      });
      if (updateHash) history.replaceState(null, '', name === 'draw' ? '#draw' : '#schedule');
    }

    buttons.forEach(button => button.addEventListener('click', () => selectTab(button.dataset.fmPublicTab, true)));
    selectTab(['#schedule', '#oop', '#match-times'].includes(location.hash) ? 'schedule' : 'draw');
  })();
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\flexible-monrad.blade.php ENDPATH**/ ?>