

<?php $__env->startSection('title', 'Bank Refunds'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  <?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => 'Refund operations','eyebrow' => 'Administration','subtitle' => 'Process pending refunds and keep a clear audit trail. Processed records an action in Cape Tennis; use PayFast Status to verify provider settlement.','icon' => 'ti-receipt-refund']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Refund operations','eyebrow' => 'Administration','subtitle' => 'Process pending refunds and keep a clear audit trail. Processed records an action in Cape Tennis; use PayFast Status to verify provider settlement.','icon' => 'ti-receipt-refund']); ?>
   <?php $__env->slot('meta', null, []); ?> <div class="d-flex gap-2">
      <span class="badge bg-label-warning"><?php echo e($refunds->total() + $pendingTeamRefunds->count()); ?> pending</span>
      <span class="badge bg-label-success"><?php echo e($completedRefunds->total() + $completedTeamRefunds->count()); ?> processed</span>
      <span class="badge bg-label-secondary"><?php echo e($waivedRefunds->total() + $waivedTeamRefunds->count()); ?> waived</span>
    </div> <?php $__env->endSlot(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('pf_query_result')): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <i class="ti ti-search me-1"></i> <?php echo e(session('pf_query_result')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo e($errors->first()); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo e(session('success')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app()->environment('local')): ?>
    <div class="mb-2">
      <small class="text-muted">Debug - registration pending: <?php echo e($refunds->count() ?? 0); ?> | team pending: <?php echo e($pendingTeamRefunds->count() ?? 0); ?></small>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count()): ?>
        <div class="small mt-1">Team IDs: <?php echo e($pendingTeamRefunds->pluck('id')->join(', ')); ?></div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((empty($refunds) || $refunds->isEmpty()) && (empty($pendingTeamRefunds) || $pendingTeamRefunds->isEmpty())): ?>
    <div class="alert alert-success">
      <i class="ti ti-circle-check me-1"></i>No pending refunds require action.
    </div>
  <?php else: ?>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Event</th>
            <th>Player(s)</th>
            <th>User</th>
            <th>PayFast ID</th>
            <th>Gross</th>
            <th>Fee</th>
            <th>Net</th>
            <th>Withdrawn</th>
            <th></th>
          </tr>
        </thead>
        <tbody>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $refunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td>
              <strong><?php echo e($reg->categoryEvent->event->name); ?></strong><br>
              <small class="text-muted">
                <?php echo e($reg->categoryEvent->name ?? ''); ?>

              </small>
            </td>

            <td><?php echo e($reg->display_name); ?></td>

            <td>
              <?php echo e($reg->user->name ?? '—'); ?><br>
              <small class="text-muted">
                <?php echo e($reg->user->email ?? ''); ?>

              </small>
            </td>

            <td>
              <code><?php echo e($reg->pf_transaction_id ?? '—'); ?></code>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->pf_transaction_id && $reg->refund_account_name): ?>
                <br><small class="text-success"><i class="ti ti-building-bank"></i> Bank details ✓</small>
              <?php elseif($reg->pf_transaction_id): ?>
                <br><small class="text-warning"><i class="ti ti-alert-triangle"></i> No bank details</small>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td>R<?php echo e(number_format($reg->refund_gross, 2)); ?></td>
            <td class="text-danger">
              R<?php echo e(number_format($reg->refund_fee, 2)); ?>

            </td>
            <td class="fw-bold text-success">
              R<?php echo e(number_format($reg->refund_net, 2)); ?>

            </td>

            <td>
              <?php echo e(optional($reg->withdrawn_at)->format('Y-m-d')); ?>

            </td>

            <td class="text-end">
              <a href="<?php echo e(route('admin.refunds.bank.show', $reg)); ?>" class="btn btn-sm btn-outline-primary me-1">
                <i class="ti ti-eye me-1" aria-hidden="true"></i>View
              </a>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->pf_transaction_id): ?>
                <a href="<?php echo e(route('admin.refunds.bank.payfast-query', $reg)); ?>"
                   class="btn btn-sm btn-outline-secondary me-1"
                   title="Query PayFast refund status">
                  <i class="ti ti-credit-card me-1" aria-hidden="true"></i>PF Status
                </a>
                
                <form method="POST"
                      action="<?php echo e(route('admin.refunds.bank.request-bank-details', $reg)); ?>"
                      onsubmit="return confirm('Send bank details request email to <?php echo e($reg->user->email ?? 'the player'); ?>?');"
                      class="d-inline me-1">
                  <?php echo csrf_field(); ?>
                  <button class="btn btn-sm btn-outline-info" title="Email player to submit bank details">
                    <i class="ti ti-mail me-1" aria-hidden="true"></i>Bank Details
                  </button>
                </form>
                <form method="POST"
                      action="<?php echo e(route('admin.refunds.bank.complete', $reg)); ?>"
                      onsubmit="return confirm('Process PayFast refund of R<?php echo e(number_format($reg->refund_net, 2)); ?> for <?php echo e($reg->display_name); ?>? This will submit the refund to PayFast.');"
                      class="d-inline me-1">
                  <?php echo csrf_field(); ?>
                  <button class="btn btn-sm btn-warning text-dark" title="Submit refund via PayFast API">
                    <i class="ti ti-credit-card-refund me-1"></i>Submit to PayFast
                  </button>
                </form>
              <?php else: ?>
                <form method="POST"
                      action="<?php echo e(route('admin.refunds.bank.complete', $reg)); ?>"
                      onsubmit="return confirm('Mark this bank refund as manually paid?');"
                      class="d-inline">
                  <?php echo csrf_field(); ?>
                  <button class="btn btn-sm btn-success">
                    <i class="ti ti-check me-1"></i>Record manual payment
                  </button>
                </form>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <button type="button"
                      class="btn btn-sm btn-outline-danger js-waive-refund"
                      data-waive-url="<?php echo e(route('admin.refunds.bank.waive', $reg)); ?>"
                      data-refund-name="<?php echo e($reg->display_name); ?>"
                      title="Close without paying">
                <i class="ti ti-ban me-1"></i>Waive
              </button>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($pendingTeamRefunds) && $pendingTeamRefunds->count()): ?>
          <tr>
            <td colspan="9"><strong>Team Refunds</strong></td>
          </tr>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pendingTeamRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td>
                <strong><?php echo e(optional($t->event)->name ?? 'Event #' . ($t->event_id ?? '')); ?></strong><br>
                <small class="text-muted">Team ID: <?php echo e($t->team_id); ?></small>
              </td>
              <td><?php echo e(optional($t->player)->name ?? 'Player #' . ($t->player_id ?? '')); ?></td>
              <td>
                <?php echo e($t->user->name ?? '—'); ?><br>
                <small class="text-muted"><?php echo e($t->user->email ?? ''); ?></small>
              </td>
              <td><code><?php echo e($t->payfast_pf_payment_id ?? '—'); ?></code></td>
              <td>R<?php echo e(number_format($t->refund_gross, 2)); ?></td>
              <td class="text-danger">R<?php echo e(number_format($t->refund_fee, 2)); ?></td>
              <td class="fw-bold text-success">R<?php echo e(number_format($t->refund_net, 2)); ?></td>
              <td><?php echo e(optional($t->updated_at)->format('Y-m-d')); ?></td>
              <td class="text-end">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->payfast_pf_payment_id): ?>
                  <form method="POST" action="<?php echo e(route('admin.refunds.bank.complete.team', $t)); ?>" onsubmit="return confirm('Process PayFast refund of R<?php echo e(number_format($t->refund_net, 2)); ?>? This will submit to PayFast.');" class="d-inline me-1">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-sm btn-warning text-dark" title="Submit refund via PayFast API">
                    <i class="ti ti-credit-card-refund me-1"></i>Submit to PayFast
                    </button>
                  </form>
                <?php else: ?>
                  <form method="POST" action="<?php echo e(route('admin.refunds.bank.complete.team', $t)); ?>" onsubmit="return confirm('Record this team bank refund as manually paid?');" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-sm btn-success"><i class="ti ti-check me-1"></i>Record manual payment</button>
                  </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button type="button"
                        class="btn btn-sm btn-outline-danger js-waive-refund"
                        data-waive-url="<?php echo e(route('admin.refunds.bank.waive.team', $t)); ?>"
                        data-refund-name="<?php echo e(optional($t->player)->name ?? 'Team refund #' . $t->id); ?>"
                        title="Close without paying">
                  <i class="ti ti-ban me-1"></i>Waive
                </button>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </tbody>
      </table>
    </div>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refunds->hasPages()): ?>
    <div class="mt-3"><?php echo e($refunds->links()); ?></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($completedRefunds) && $completedRefunds->count()): ?>
    <h4 class="mt-4 mb-1">Processed Refunds</h4>
    <p class="text-muted">These were recorded as processed by Cape Tennis. PayFast rows should still be checked for their current provider status.</p>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>Event</th>
              <th>Player(s)</th>
              <th>User</th>
              <th>PayFast ID</th>
              <th>Net Refunded</th>
              <th>Recorded At</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $completedRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td>
                  <strong><?php echo e(optional($reg->categoryEvent?->event)->name ?? '—'); ?></strong><br>
                  <small class="text-muted"><?php echo e($reg->categoryEvent->name ?? ''); ?></small>
                </td>
                <td><?php echo e($reg->display_name); ?></td>
                <td>
                  <?php echo e($reg->user->name ?? '—'); ?><br>
                  <small class="text-muted"><?php echo e($reg->user->email ?? ''); ?></small>
                </td>
                <td><code><?php echo e($reg->pf_transaction_id ?? '—'); ?></code></td>
                <td class="fw-bold text-success">R<?php echo e(number_format($reg->refund_net, 2)); ?></td>
                <td><?php echo e(optional($reg->refunded_at)->format('Y-m-d')); ?></td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->pf_transaction_id): ?>
                    <span class="badge bg-label-info">Submitted to PayFast</span>
                  <?php else: ?>
                    <span class="badge bg-label-success">Manual payment recorded</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->pf_transaction_id): ?>
                    <a href="<?php echo e(route('admin.refunds.bank.payfast-query', $reg)); ?>"
                       class="btn btn-sm btn-outline-secondary"
                       title="Query PayFast refund status">
                      <i class="ti ti-credit-card me-1" aria-hidden="true"></i>PF Status
                    </a>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($completedRefunds->hasPages()): ?>
      <div class="mt-3"><?php echo e($completedRefunds->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($waivedRefunds->count() + $waivedTeamRefunds->count()) > 0): ?>
    <h4 class="mt-4 mb-1">Waived Refunds</h4>
    <p class="text-muted">Closed without payment. These records remain visible for audit purposes.</p>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead>
            <tr>
              <th>Event</th>
              <th>Player</th>
              <th>Amount not paid</th>
              <th>Waived at</th>
              <th>Reason</th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $waivedRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><?php echo e(optional($reg->categoryEvent?->event)->name ?? '—'); ?></td>
                <td><?php echo e($reg->display_name); ?></td>
                <td>R<?php echo e(number_format($reg->refund_net, 2)); ?></td>
                <td><?php echo e(optional($reg->refund_waived_at)->format('Y-m-d H:i')); ?></td>
                <td class="text-wrap" style="min-width: 16rem"><?php echo e($reg->refund_waiver_reason); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $completedTeamRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td>
                  <strong><?php echo e(optional($order->event)->name ?? '—'); ?></strong><br>
                  <small class="text-muted">Team refund</small>
                </td>
                <td><?php echo e(optional($order->player)->name ?? 'Team refund #' . $order->id); ?></td>
                <td>
                  <?php echo e($order->user->name ?? '—'); ?><br>
                  <small class="text-muted"><?php echo e($order->user->email ?? ''); ?></small>
                </td>
                <td><code><?php echo e($order->payfast_pf_payment_id ?? '—'); ?></code></td>
                <td class="fw-bold text-success">R<?php echo e(number_format($order->refund_net, 2)); ?></td>
                <td><?php echo e(optional($order->refunded_at)->format('Y-m-d')); ?></td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->payfast_pf_payment_id): ?>
                    <span class="badge bg-label-info">Submitted to PayFast</span>
                  <?php else: ?>
                    <span class="badge bg-label-success">Manual payment recorded</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $waivedTeamRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><?php echo e(optional($order->event)->name ?? '—'); ?></td>
                <td><?php echo e(optional($order->player)->name ?? 'Team refund #' . $order->id); ?></td>
                <td>R<?php echo e(number_format($order->refund_net, 2)); ?></td>
                <td><?php echo e(optional($order->refund_waived_at)->format('Y-m-d H:i')); ?></td>
                <td class="text-wrap" style="min-width: 16rem"><?php echo e($order->refund_waiver_reason); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($waivedRefunds->hasPages()): ?>
      <div class="mt-3"><?php echo e($waivedRefunds->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="modal fade" id="waiveRefundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" id="waiveRefundForm" class="modal-content">
        <?php echo csrf_field(); ?>
        <div class="modal-header">
          <h5 class="modal-title">Waive refund</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning">
            <strong>No money will be paid.</strong> This closes the pending refund and keeps it in the audit history.
          </div>
          <p id="waiveRefundName" class="fw-semibold"></p>
          <label for="waiveReason" class="form-label">Reason <span class="text-danger">*</span></label>
          <textarea id="waiveReason" name="reason" class="form-control" rows="3" minlength="5" maxlength="500" required placeholder="Why is this refund being waived?"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="ti ti-ban me-1"></i>Waive without payment</button>
        </div>
      </form>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modalElement = document.getElementById('waiveRefundModal');
  const form = document.getElementById('waiveRefundForm');
  const name = document.getElementById('waiveRefundName');
  const reason = document.getElementById('waiveReason');

  document.querySelectorAll('.js-waive-refund').forEach(function (button) {
    button.addEventListener('click', function () {
      form.action = button.dataset.waiveUrl;
      name.textContent = button.dataset.refundName;
      reason.value = '';
      bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\refunds\bank.blade.php ENDPATH**/ ?>