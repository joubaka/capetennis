<?php
  $eventWorkspaceRegionalOnly = $eventWorkspaceRegionalOnly ?? false;
  $eventWorkspaceActive = $eventWorkspaceActive ?? match (true) {
    request()->routeIs('backend.event-communications.*', 'backend.interprovincial-trials.communications.*') => 'communications',
    request()->routeIs('backend.interprovincial-trials.*') => 'invitations',
    request()->routeIs('backend.event-venue-schedule.*') => 'schedule',
    request()->routeIs('headOffice.*', 'admin.events.draws') => 'draws',
    request()->routeIs('admin.events.results.*', 'backend.scoreboard.team.show') => 'results',
    request()->routeIs('admin.events.standings') => 'standings',
    request()->routeIs('admin.events.finances*') => 'finances',
    request()->routeIs('admin.events.settings*') => 'settings',
    request()->routeIs('admin.events.entries*', 'admin.events.teams') => 'entries',
    request()->routeIs(
      'convenor.*', 'admin.events.categories', 'admin.events.announcements*',
      'backend.events.disciplinary.*', 'admin.events.transactions', 'transactions.pdf'
    ) => 'more',
    default => 'overview',
  };
?>
<?php if (isset($component)) { $__componentOriginal6f4f3916e7dba721ed8a718ed00dbe8f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f4f3916e7dba721ed8a718ed00dbe8f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.context-nav','data' => ['label' => 'Event navigation']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.context-nav'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'Event navigation']); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventWorkspaceRegionalOnly): ?>
    <a href="<?php echo e(route('backend.team-selection.index', $event)); ?>" <?php if($eventWorkspaceActive !== 'communications'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-users" aria-hidden="true"></i>Teams</a>
    <a href="<?php echo e(route('backend.event-communications.index', $event)); ?>" <?php if($eventWorkspaceActive === 'communications'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-mail" aria-hidden="true"></i>Communications</a>
  <?php else: ?>
  <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-draw.view', $event)): ?>
    <a href="<?php echo e(route('admin.events.overview', $event)); ?>" <?php if($eventWorkspaceActive === 'overview'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-layout-grid" aria-hidden="true"></i>Event overview</a>
    <a href="<?php echo e(route($event->isTeam() ? 'admin.events.teams' : 'admin.events.entries.new', $event)); ?>" <?php if($eventWorkspaceActive === 'entries'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-users" aria-hidden="true"></i><?php echo e($event->isTeam() ? 'Teams' : 'Entries'); ?></a>
    <a href="<?php echo e(route('headOffice.show', $event->id)); ?>" <?php if($eventWorkspaceActive === 'draws'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-tournament" aria-hidden="true"></i>Draws</a>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
      <a href="<?php echo e(route('backend.event-venue-schedule.index', $event)); ?>" <?php if($eventWorkspaceActive === 'schedule'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-calendar-event" aria-hidden="true"></i>Schedule</a>
    <?php endif; ?>
    <a href="<?php echo e(route($event->isTeam() ? 'admin.events.standings' : 'admin.events.results.individual', $event)); ?>" <?php if(in_array($eventWorkspaceActive, ['results', 'standings'])): ?> aria-current="page" <?php endif; ?>><i class="ti ti-trophy" aria-hidden="true"></i>Standings</a>
  <?php endif; ?>
  <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-finance.view', $event)): ?>
    <a href="<?php echo e(route('admin.events.finances', $event)); ?>" <?php if($eventWorkspaceActive === 'finances'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-report-money" aria-hidden="true"></i>Finances</a>
  <?php endif; ?>
  <?php
    $canManageInterprovincialInvitations = $event->isInterprovincialTrials()
      && (auth()->user()?->hasRole('super-user')
        || (auth()->user()?->hasRole('admin') && auth()->user()?->is_event_admin($event->id)));
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$event->isInterprovincialTrials() && auth()->user() && app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event)): ?>
    <a href="<?php echo e(route('backend.event-communications.index', $event)); ?>" <?php if($eventWorkspaceActive === 'communications'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-mail" aria-hidden="true"></i>Communications</a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isInterprovincialTrials() && auth()->user() && app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->canManage($event, auth()->user())): ?>
    <a href="<?php echo e(route('backend.interprovincial-trials.communications.index', $event)); ?>" <?php if($eventWorkspaceActive === 'communications'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-mail" aria-hidden="true"></i>Communications</a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageInterprovincialInvitations): ?>
    <a href="<?php echo e(route('backend.interprovincial-trials.invitations.index', $event)); ?>"
       <?php if($eventWorkspaceActive === 'invitations'): ?> aria-current="page" <?php endif; ?>>
      <i class="ti ti-mail-forward" aria-hidden="true"></i>Nominations &amp; invitations
    </a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.settings.manage', $event)): ?>
    <a href="<?php echo e(route('admin.events.settings', $event)); ?>" <?php if($eventWorkspaceActive === 'settings'): ?> aria-current="page" <?php endif; ?>><i class="ti ti-settings" aria-hidden="true"></i>Settings</a>
  <?php endif; ?>
  <?php
    $canSeeEventTools = auth()->user()?->can('event-draw.view', $event)
      || auth()->user()?->can('event-category.manage', $event)
      || auth()->user()?->can('event.manage', $event);
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSeeEventTools): ?>
    <div class="event-workspace-more dropdown">
      <button class="event-workspace-more__toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" <?php if($eventWorkspaceActive === 'more'): ?> aria-current="page" <?php endif; ?>>
        <i class="ti ti-dots" aria-hidden="true"></i>More
      </button>
      <div class="dropdown-menu dropdown-menu-end">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-draw.view', $event)): ?>
          <a class="dropdown-item" href="<?php echo e(route('admin.events.transactions', $event)); ?>"><i class="ti ti-credit-card" aria-hidden="true"></i>Transactions</a>
        <?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-category.manage', $event)): ?>
          <a class="dropdown-item" href="<?php echo e(route('admin.events.categories', $event)); ?>"><i class="ti ti-list-details" aria-hidden="true"></i>Categories</a>
        <?php endif; ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
          <a class="dropdown-item" href="<?php echo e(route('convenor.show', $event->id)); ?>"><i class="ti ti-users" aria-hidden="true"></i>Event directors</a>
          <a class="dropdown-item" href="<?php echo e(route('admin.events.announcements', $event)); ?>"><i class="ti ti-megaphone" aria-hidden="true"></i>Announcements</a>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Models\SiteSetting::disciplinarySystemEnabled()): ?>
            <a class="dropdown-item" href="<?php echo e(route('backend.events.disciplinary.index', $event)); ?>"><i class="ti ti-scale" aria-hidden="true"></i>Discipline &amp; incidents</a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series): ?>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="<?php echo e(route('series.show', $event->series)); ?>"><i class="ti ti-layers" aria-hidden="true"></i><?php echo e($event->series->name); ?></a>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$event->isTeam()): ?>
            <a class="dropdown-item" href="<?php echo e(route('ranking.series.list', $event->series)); ?>" target="_blank" rel="noopener"><i class="ti ti-printer" aria-hidden="true"></i>Print series rankings</a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f4f3916e7dba721ed8a718ed00dbe8f)): ?>
<?php $attributes = $__attributesOriginal6f4f3916e7dba721ed8a718ed00dbe8f; ?>
<?php unset($__attributesOriginal6f4f3916e7dba721ed8a718ed00dbe8f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f4f3916e7dba721ed8a718ed00dbe8f)): ?>
<?php $component = $__componentOriginal6f4f3916e7dba721ed8a718ed00dbe8f; ?>
<?php unset($__componentOriginal6f4f3916e7dba721ed8a718ed00dbe8f); ?>
<?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('schedule') === 'applied'): ?>
  <div class="alert alert-success" role="status">
    <strong>Schedule applied.</strong> Review each draw's order of play, then publish it when you are ready.
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\partials\workspace-nav.blade.php ENDPATH**/ ?>