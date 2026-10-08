<div class="col-xl-12">
  <?php
    $primaryDashboardTab = ($tabs['events'] ?? false) ? 'events'
      : (($tabs['rankings'] ?? false) ? 'rankings'
      : (($tabs['users'] ?? false) ? 'users'
      : (($tabs['players'] ?? false) ? 'players' : 'activity')));
  ?>

  <div class="nav-align-top mb-4">

    
    <ul class="nav nav-pills dashboard-tabs mb-4" role="tablist">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['events'] ?? false): ?>
      <li class="nav-item" role="presentation">
        <button
          class="nav-link <?php echo e($primaryDashboardTab === 'events' ? 'active' : ''); ?>"
          data-bs-toggle="tab"
          data-bs-target="#tab-events"
          type="button"
          role="tab"
          aria-selected="true">
          My Events
        </button>
      </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['rankings'] ?? false): ?>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link <?php echo e($primaryDashboardTab === 'rankings' ? 'active' : ''); ?>"
            data-bs-toggle="tab"
            data-bs-target="#tab-rankings"
            type="button"
            role="tab"
            aria-selected="false">
            Rankings
          </button>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['users'] ?? false): ?>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link <?php echo e($primaryDashboardTab === 'users' ? 'active' : ''); ?>"
            data-bs-toggle="tab"
            data-bs-target="#tab-users"
            type="button"
            role="tab"
            aria-selected="false">
            Users
          </button>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['players'] ?? false): ?>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link <?php echo e($primaryDashboardTab === 'players' ? 'active' : ''); ?>"
            data-bs-toggle="tab"
            data-bs-target="#tab-players"
            type="button"
            role="tab"
            aria-selected="false">
            Players
          </button>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['activity'] ?? false): ?>
        <li class="nav-item" role="presentation">
          <button
            class="nav-link <?php echo e($primaryDashboardTab === 'activity' ? 'active' : ''); ?>"
            data-bs-toggle="tab"
            data-bs-target="#tab-activity"
            type="button"
            role="tab"
            aria-selected="false">
            <i class="ti ti-history me-1"></i> Activity Log
          </button>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>

    
    <div class="tab-content">

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['events'] ?? false): ?>
      <div class="tab-pane fade <?php echo e($primaryDashboardTab === 'events' ? 'show active' : ''); ?>" id="tab-events" role="tabpanel">
        <div class="card dashboard-events-card mb-4">
          <div class="card-header dashboard-section-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-1">My events</h5>
              <p class="text-muted small mb-0">Manage registrations, schedules and event settings.</p>
            </div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('superUser')): ?>
              <button class="btn btn-primary btn-sm"
                      data-bs-toggle="modal"
                      data-bs-target="#addEvent">
                <i class="ti ti-plus me-1"></i> Create Event
              </button>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $managedEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <?php
                $isUpcoming = $event->start_date?->isFuture();
                $isCurrent = ! $isUpcoming && (! $event->end_date || $event->end_date->copy()->endOfDay()->isFuture());
              ?>
              <div class="dashboard-event-card mb-3" data-event-id="<?php echo e($event->id); ?>">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                  <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                      <h5 class="mb-0"><?php echo e($event->name); ?></h5>
                      <span class="badge <?php echo e($isUpcoming ? 'bg-label-info' : ($isCurrent ? 'bg-label-success' : 'bg-label-secondary')); ?>">
                        <?php echo e($isUpcoming ? 'Upcoming' : ($isCurrent ? 'In progress' : 'Completed')); ?>

                      </span>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($event->published)): ?>
                        <span class="badge bg-label-warning">Not published</span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="dashboard-event-card__meta d-flex flex-wrap gap-3">
                      <span><i class="ti ti-calendar me-1"></i><?php echo e($event->start_date?->format('d M Y') ?? 'Date not set'); ?></span>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series): ?>
                        <span><i class="ti ti-layers me-1"></i><?php echo e($event->series->name); ?></span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                  </div>

                  <div class="dashboard-event-card__actions align-self-lg-start">
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-draw.view', $event)): ?>
                      <a class="btn btn-sm btn-primary" href="<?php echo e(route('admin.events.overview', $event)); ?>"><i class="ti ti-layout-grid me-1"></i>Open event</a>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route($event->isTeam() ? 'admin.events.teams' : 'admin.events.entries.new', $event)); ?>"><?php echo e($event->isTeam() ? 'Teams' : 'Entries'); ?></a>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('headOffice.show', $event->id)); ?>">Draws</a>
                      <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route($event->isTeam() ? 'backend.scoreboard.team.show' : 'admin.events.results.individual', $event)); ?>">Results</a>
                    <?php endif; ?>

                    <div class="dropdown">
                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More</button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-finance.view', $event)): ?>
                          <a class="dropdown-item" href="<?php echo e(route('admin.events.finances', $event)); ?>"><i class="ti ti-report-money me-2"></i>Finances</a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.settings.manage', $event)): ?>
                          <a class="dropdown-item" href="<?php echo e(route('admin.events.settings', $event)); ?>"><i class="ti ti-settings me-2"></i>Settings</a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-category.manage', $event)): ?>
                          <a class="dropdown-item" href="<?php echo e(route('admin.events.categories', $event)); ?>"><i class="ti ti-list-details me-2"></i>Categories</a>
                        <?php endif; ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
                          <a class="dropdown-item" href="<?php echo e(route('convenor.show', $event->id)); ?>"><i class="ti ti-users me-2"></i>Event directors</a>
                          <a class="dropdown-item" href="<?php echo e(route('admin.events.announcements', $event)); ?>"><i class="ti ti-megaphone me-2"></i>Announcements</a>
                        <?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasAnyRole(['super-user', 'admin', 'convenor']) && auth()->user()->can('event-draw.view', $event)): ?>
                          <a class="dropdown-item" href="<?php echo e(route('admin.events.transactions', $event)); ?>"><i class="ti ti-credit-card me-2"></i>Transactions</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series && auth()->user()->can('view', $event->series)): ?>
                          <div class="dropdown-divider"></div>
                          <a class="dropdown-item" href="<?php echo e(route('series.show', $event->series)); ?>"><i class="ti ti-layers me-2"></i>Series &amp; rankings</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'super-user')): ?>
                          <div class="dropdown-divider"></div>
                          <a class="dropdown-item" href="<?php echo e(route('admin.events.copy', $event)); ?>"><i class="ti ti-copy me-2"></i>Copy event</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <div class="text-center py-5">
                <span class="badge bg-label-secondary p-3 mb-3"><i class="ti ti-calendar-off ti-lg"></i></span>
                <h5>No events to manage yet</h5>
                <p class="text-muted mb-0">Events will appear here when you are assigned as an event administrator.</p>
              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($managedEvents->hasPages()): ?>
              <div class="pt-3 border-top">
                <?php echo e($managedEvents->links('pagination::simple-bootstrap-5')); ?>

              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      
      <div class="tab-pane fade <?php echo e($primaryDashboardTab === 'rankings' ? 'show active' : ''); ?>" id="tab-rankings" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">Series List</h5>
          <div class="table-responsive">
            <table class="table datatable-series border-top w-100">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Series</th>
                  <th>Setup</th>
                  <th>Publish</th>
                  <th>Action</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      
      <div class="tab-pane fade <?php echo e($primaryDashboardTab === 'users' ? 'show active' : ''); ?>" id="tab-users" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">User List</h5>
          <div class="table-responsive">
            <table class="table datatable-users border-top w-100">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Action</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      
      <div class="tab-pane fade <?php echo e($primaryDashboardTab === 'players' ? 'show active' : ''); ?>" id="tab-players" role="tabpanel">
        <div class="mb-4">
          <h5 class="card-header">Player List</h5>
          <div class="table-responsive">
            <table class="table datatable-players border-top w-100">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Profile</th>
                  <th>Results</th>
                  <th>Details</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>
      </div>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tabs['activity'] ?? false): ?>
      <div class="tab-pane fade <?php echo e($primaryDashboardTab === 'activity' ? 'show active' : ''); ?>" id="tab-activity" role="tabpanel">
        <div class="mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ti ti-history me-1"></i> Activity Log</h5>
            <div class="d-flex align-items-center gap-2">
              <label class="small mb-0 me-2">Filter</label>
              <select id="activity-filter-log" class="form-select form-select-sm me-2">
                <option value="">All</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $logNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ln): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($ln); ?>"><?php echo e($ln); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>

              <div class="form-check form-switch me-2">
                <input class="form-check-input" type="checkbox" id="activity-toggle-view" checked>
                <label class="form-check-label small" for="activity-toggle-view">Grouped</label>
              </div>

              <span class="badge bg-label-secondary">Last 50</span>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover table-striped border-top w-100" id="datatable-activity">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User</th>
                  <th>Log</th>
                  <th>Action</th>
                  <th>Details</th>
                  <th class="d-none">Log Names</th>
                </tr>
              </thead>
              <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activityByUser; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr>
                    <td><?php echo e(optional($row->last_at)->format('d M Y H:i') ?? '—'); ?></td>
                    <td><?php echo e($row->causer?->userName ?? $row->causer?->name ?? 'System'); ?></td>
                    <td><span class="badge bg-label-primary"><?php echo e($row->count); ?></span></td>
                    <td><?php echo e($row->example_description ?? '—'); ?></td>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($row->properties) && count((array)$row->properties)): ?>
                        <button class="btn btn-xs btn-outline-secondary"
                                type="button"
                                data-bs-toggle="popover"
                                data-bs-trigger="focus"
                                data-bs-html="true"
                                data-bs-content="<?php $__currentLoopData = (array)$row->properties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><strong><?php echo e($key); ?>:</strong> <?php echo e(is_array($val) ? json_encode($val) : $val); ?><br><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>"
                                data-log-names="<?php echo e(implode(',', $row->log_names ?? [])); ?>">
                          <i class="ti ti-info-circle"></i>
                        </button>
                      <?php else: ?>
                        —
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="d-none log-names"><?php echo e(implode(',', $row->log_names ?? [])); ?></td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>

            
            <table class="table table-hover table-striped border-top w-100 d-none" id="datatable-activity-raw">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>User</th>
                  <th>Log</th>
                  <th>Action</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activityLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr>
                    <td><?php echo e($log->created_at->format('d M Y H:i')); ?></td>
                    <td><?php echo e($log->causer?->userName ?? $log->causer?->name ?? 'System'); ?></td>
                    <td><?php echo e($log->log_name); ?></td>
                    <td><?php echo e($log->description); ?></td>
                    <td>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->properties && $log->properties->count()): ?>
                        <button class="btn btn-xs btn-outline-secondary"
                                type="button"
                                data-bs-toggle="popover"
                                data-bs-trigger="focus"
                                data-bs-html="true"
                                data-bs-content="<?php $__currentLoopData = $log->properties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><strong><?php echo e($key); ?>:</strong> <?php echo e(is_array($val) ? json_encode($val) : $val); ?><br><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>">
                          <i class="ti ti-info-circle"></i>
                        </button>
                      <?php else: ?>
                        —
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\templates\adminDashboardTemplate.blade.php ENDPATH**/ ?>