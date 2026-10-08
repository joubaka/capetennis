<div class="col-xl-12">
  <div class="row g-4">
    <div class="col-lg-8">
      <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo $__env->make('frontend.event.partials.trial-programme', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">Trial nominations</h5></div>
        <div class="card-body">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publishedCategories->isEmpty()): ?>
            <p class="text-muted mb-0">No trial nominations have been published.</p>
          <?php else: ?>
            <div class="row g-3">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $publishedCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-md-6">
                  <section class="border rounded p-3 h-100">
                    <h6><?php echo e($categoryEvent->category?->name ?? 'Category'); ?></h6>
                    <ul class="list-group list-group-flush">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $categoryEvent->nominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                          $trialInvitation = $nomination->actionableInvitation;
                          $ownsPlayer = auth()->check() && in_array((int) $nomination->player_id, auth()->user()->ownedPlayerIds(), true);
                          $tupleMatches = $trialInvitation
                            && (int) $trialInvitation->event_id === (int) $event->id
                            && (int) $trialInvitation->category_event_id === (int) $categoryEvent->id
                            && (int) $trialInvitation->nomination_id === (int) $nomination->id
                            && (int) $trialInvitation->player_id === (int) $nomination->player_id;
                          $focused = (int) request()->query('player') === (int) $nomination->player_id
                            && (int) request()->query('nomination') === (int) $nomination->id;
                          $registrationOpen = $event->published && $event->hasOpenRegistrationLifecycle() && (int) $event->signUp === 1
                            && (!$event->registrationClosesAt() || now()->lte($event->registrationClosesAt()->endOfDay()));
                        ?>
                        <li id="trial-nomination-<?php echo e($nomination->id); ?>" class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2 <?php echo e($focused ? 'border border-primary rounded px-2 bg-label-primary' : ''); ?>" <?php if($focused): ?> tabindex="-1" autofocus <?php endif; ?>>
                          <span><?php echo e($nomination->display_name); ?></span>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomination->player_id === null): ?>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                              <span class="badge bg-label-warning">Profile required</span>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?>
                                <a class="btn btn-sm btn-primary" href="<?php echo e(route('interprovincial-trials.nominations.profile', [$event, $categoryEvent, $nomination])); ?>">Create player profile</a>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                          <?php elseif(!$trialInvitation && $registrationOpen): ?>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                              <span class="badge bg-label-secondary">Not registered</span>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                                <form method="POST" action="<?php echo e(route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination])); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                              <?php else: ?>
                                <a class="btn btn-sm btn-primary" href="<?php echo e(route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id])); ?>">Sign in to register</a>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                          <?php elseif($tupleMatches): ?>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($trialInvitation->status, ['queued', 'sent', 'open_registration'], true)): ?>
                              <span class="badge bg-label-secondary">Not registered</span>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?><form method="POST" action="<?php echo e(route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination])); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                <?php else: ?><a class="btn btn-sm btn-primary" href="<?php echo e(route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id])); ?>">Sign in to register</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              <?php else: ?>
                                <span class="badge bg-label-secondary">Registration closed</span>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ownsPlayer): ?><form method="POST" action="<?php echo e(route('interprovincial-trials.invitations.decline', $trialInvitation)); ?>" onsubmit="return confirm('Decline this invitation?');"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-outline-danger">Decline</button></form><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT): ?>
                              <span class="badge bg-label-secondary">Not registered</span>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?><form method="POST" action="<?php echo e(route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination])); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-primary">Resume registration</button></form>
                                <?php else: ?><a class="btn btn-sm btn-primary" href="<?php echo e(route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id])); ?>">Sign in to resume</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::PAID_CONFIRMED): ?>
                              <span class="badge bg-label-success">Registered</span>
                            <?php elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::DECLINED): ?>
                              <span class="badge bg-label-secondary">Declined</span>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?><form method="POST" action="<?php echo e(route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination])); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                <?php else: ?><a class="btn btn-sm btn-primary" href="<?php echo e(route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id])); ?>">Sign in to register</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php elseif($trialInvitation->status === \App\Models\InterprovincialTrialInvitation::WITHDRAWN): ?>
                              <span class="badge bg-label-secondary">Not registered</span>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationOpen): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?><form method="POST" action="<?php echo e(route('interprovincial-trials.nominations.register', [$event, $categoryEvent, $nomination])); ?>"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-primary">Register</button></form>
                                <?php else: ?><a class="btn btn-sm btn-primary" href="<?php echo e(route('login', ['redirect' => route('events.show', ['event' => $event, 'player' => $nomination->player_id, 'nomination' => $nomination->id], false).'#trial-nomination-'.$nomination->id])); ?>">Sign in to register</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </li>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <li class="list-group-item px-0 text-muted">No nominated players.</li>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                  </section>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\eventTypes\interprovincial-trials.blade.php ENDPATH**/ ?>