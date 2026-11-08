<section class="card card-body mb-4 no-print" aria-labelledby="organiser-setup-heading" data-organiser-setup>
  <details>
  <summary style="min-height:44px;cursor:pointer">
    <span class="h5" id="organiser-setup-heading">Set up your tournament</span>
    <span class="small text-muted ms-2">Show / hide setup</span>
  </summary>
  <div class="mt-3">
  <p class="text-muted">Follow these steps for each age group or category. Your saved draws are below; use Continue setup to pick up where you left off.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isInterprovincialTrials()): ?>
    <p class="small">These are individual player trials. Create player draws from the eligible entries in each category; team tie setup is used for team competitions.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <ol class="row g-3 ps-4 mb-3">
    <li class="col-12 col-md-6 ps-2 pe-3">
      <strong>Check participants</strong>
      <p class="small mb-1">Confirm the categories and participating <?php echo e($event->isTeam() ? 'teams and player rosters' : 'players'); ?> before creating matches.</p>
      <a href="<?php echo e(route($event->isTeam() ? 'admin.events.teams' : 'admin.events.entries.new', $event)); ?>">Review <?php echo e($event->isTeam() ? 'teams' : 'entries'); ?></a>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-category.manage', $event)): ?>
        <a class="ms-2" href="<?php echo e(route('admin.events.categories', $event)); ?>">Review categories</a>
      <?php endif; ?>
    </li>
    <li class="col-12 col-md-6 ps-2 pe-3">
      <strong>Create or continue a draw</strong>
      <p class="small mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam()): ?>Choose Create new draw, then Team tie for teams playing singles and doubles, or Individual singles for player against player.<?php else: ?> Choose Create draw beside a category below, then choose a format and place the players.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> Review the selected participants and format, then generate matches once.</p>
    </li>
    <li class="col-12 col-md-6 ps-2 pe-3">
      <strong>Set venues and schedule</strong>
      <p class="small mb-1">Start with Edit venues &amp; courts, then set match duration and playing times. Preview the programme, check conflicts and save. Include all draws to plan shared courts together.</p>
      <a href="<?php echo e(route('backend.event-venue-schedule.index', $event)); ?>#venue-management">Set up venues and courts</a>
      <a class="d-block mt-1" href="<?php echo e(route('backend.event-venue-schedule.index', $event)); ?>">Plan schedule across all draws</a>
    </li>
    <li class="col-12 col-md-6 ps-2 pe-3">
      <strong>Review and publish</strong>
      <p class="small mb-1">Check pairings and saved times before publishing. Publish the draw and its match times separately; saving setup does not make them public.</p>
      <a href="<?php echo e(route('backend.event-venue-schedule.calendar', ['event' => $event, 'date' => 'all'])); ?>">Review saved order of play</a>
    </li>
  </ol>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isEmpty()): ?>
    <p class="alert alert-info mb-0" role="status">No draws saved yet. Start by checking participants, then <?php echo e($event->isTeam() ? 'choose Create new draw above' : 'create a draw from a category below'); ?>.</p>
  <?php else: ?>
    <p class="small text-muted mb-0">Keep existing draws when returning to setup. Open a saved draw to review its matches before changing the format or generating again.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  </details>
</section>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/headOffice/partials/organiser-setup.blade.php ENDPATH**/ ?>