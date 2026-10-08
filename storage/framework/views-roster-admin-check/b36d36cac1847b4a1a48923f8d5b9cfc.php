

<?php $__env->startSection('title', $series->name); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-1"><?php echo e($series->name); ?></h3>
        <div class="text-muted">
          <?php echo e($series->year); ?> • Best <?php echo e($stats['best_of']); ?> results
        </div>
      </div>

      <span class="badge <?php echo e($stats['published'] ? 'bg-success' : 'bg-secondary'); ?>">
        <?php echo e($stats['published'] ? 'Published' : 'Draft'); ?>

      </span>
    </div>
  </div>

  <?php
    $seriesWorkflowStep = match (true) {
      $stats['events'] === 0 => 1,
      ! $activeRankingStatus => 2,
      $activeRankingStatus === 'calculated' => 3,
      $series->leaderboard_published => 5,
      default => 4,
    };
    $seriesNextStep = match (true) {
      $stats['events'] === 0 => [
        'title' => 'Add the first event',
        'message' => 'Use Manage Events to add the tournaments that must count towards this series.',
      ],
      $activeRankingStatus === 'calculated' => [
        'title' => 'Review the ranking, then accept it as correct',
        'message' => 'Use Review Rankings to inspect the totals, scores and tie decisions. Only use Mark Rankings Reviewed after you are satisfied that it is correct.',
      ],
      $activeRankingStatus === 'reviewed' => [
        'title' => $reviewCampaign ? 'Finalize and publish the ranking' : 'Share the ranking for participant review',
        'message' => $reviewCampaign
          ? 'Open Participant Review to check replies and delivery, then finalize and publish when the review is complete.'
          : 'Open Ranking Lists and use Share for Review. Publishing does not send ranking emails automatically.',
      ],
      $activeRankingStatus === 'published' && ! $series->leaderboard_published => [
        'title' => 'Make the published ranking visible',
        'message' => 'The ranking is published but its public leaderboard is off. Open Series Settings to switch on public visibility when you are ready.',
      ],
      $series->leaderboard_published => [
        'title' => 'The ranking is live',
        'message' => 'Use View Published Rankings to check the public page. Recalculate only when event results or ranking rules have changed.',
      ],
      default => [
        'title' => 'Complete the setup, then calculate',
        'message' => 'Confirm the events, series settings and points allocation. Then use Recalculate Rankings to build the first ranking.',
      ],
    };
  ?>

  <div class="card mb-4 border-primary-subtle">
    <div class="card-body">
      <div class="d-flex align-items-start gap-3 mb-3">
        <span class="avatar avatar-sm flex-shrink-0">
          <span class="avatar-initial rounded bg-label-primary">
            <i class="ti ti-route"></i>
          </span>
        </span>
        <div>
          <h5 class="mb-1">Ranking progress</h5>
          <p class="text-muted mb-0">Your current stage and the available next actions are shown below.</p>
        </div>
      </div>

      <div class="row g-3 small">
        <?php
          $stepBadgeClass = fn (int $step) => match (true) {
            $step < $seriesWorkflowStep => 'bg-label-success',
            $step === $seriesWorkflowStep => 'bg-primary',
            default => 'bg-label-secondary',
          };
          $stepState = fn (int $step) => match (true) {
            $step < $seriesWorkflowStep => 'Done',
            $step === $seriesWorkflowStep => 'Current',
            default => 'Next',
          };
        ?>

        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 <?php echo e($seriesWorkflowStep === 1 ? 'border-primary bg-label-primary' : ''); ?>">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge <?php echo e($stepBadgeClass(1)); ?> me-1">1</span> Set up</div>
            <span class="badge <?php echo e($stepBadgeClass(1)); ?>"><?php echo e($stepState(1)); ?></span>
          </div>
          <div class="text-muted">Add the events, choose how many results count, and confirm the points allocation.</div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($seriesWorkflowStep === 1): ?>
            <div class="d-grid gap-1 mt-3">
              <a href="<?php echo e(route('series.events', $series)); ?>" class="btn btn-sm btn-primary">Manage Events</a>
              <a href="<?php echo e(route('series.settings', $series)); ?>" class="btn btn-sm btn-outline-primary">Series Settings</a>
              <a href="<?php echo e(route('ranking.points', $series)); ?>" class="btn btn-sm btn-outline-primary">Points Allocation</a>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 <?php echo e($seriesWorkflowStep === 2 ? 'border-primary bg-label-primary' : ''); ?>">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge <?php echo e($stepBadgeClass(2)); ?> me-1">2</span> Calculate</div>
            <span class="badge <?php echo e($stepBadgeClass(2)); ?>"><?php echo e($stepState(2)); ?></span>
          </div>
          <div class="text-muted">Build the ranking after the event results and ranking rules are ready.</div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($seriesWorkflowStep === 2): ?>
            <div class="alert alert-primary py-2 px-3 mt-3 mb-0">Use <strong>Recalculate Rankings</strong> in the Rankings panel below.</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 <?php echo e($seriesWorkflowStep === 3 ? 'border-primary bg-label-primary' : ''); ?>">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge <?php echo e($stepBadgeClass(3)); ?> me-1">3</span> Review</div>
            <span class="badge <?php echo e($stepBadgeClass(3)); ?>"><?php echo e($stepState(3)); ?></span>
          </div>
          <div class="text-muted">Inspect totals, scores, exclusions and tie decisions, then accept the ranking as correct.</div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($seriesWorkflowStep === 3): ?>
            <div class="d-grid gap-1 mt-3">
              <a href="<?php echo e(route('ranking.series.list', $series)); ?>" class="btn btn-sm btn-primary">Review Rankings</a>
              <a href="<?php echo e(route('ranking.series.audit', $series)); ?>" class="btn btn-sm btn-outline-primary">Audit Rankings</a>
              <div class="text-primary mt-1"><i class="ti ti-arrow-down me-1"></i>Then mark it reviewed below.</div>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="border rounded h-100 p-3 <?php echo e($seriesWorkflowStep === 4 ? 'border-primary bg-label-primary' : ''); ?>">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold"><span class="badge <?php echo e($stepBadgeClass(4)); ?> me-1">4</span> Publish</div>
            <span class="badge <?php echo e($stepBadgeClass(4)); ?>"><?php echo e($stepState(4)); ?></span>
          </div>
          <div class="text-muted">Share for participant review if required, finalize, publish and control public visibility.</div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($seriesWorkflowStep >= 4): ?>
            <div class="d-grid gap-1 mt-3">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeRankingStatus === 'reviewed'): ?>
                <a href="<?php echo e(route('ranking.series.list', $series)); ?>" class="btn btn-sm btn-primary">
                  <?php echo e($reviewCampaign ? 'Open Participant Review' : 'Share for Review'); ?>

                </a>
                <div class="text-primary mt-1"><i class="ti ti-arrow-down me-1"></i>Publish from the Rankings panel below.</div>
              <?php elseif($series->leaderboard_published): ?>
                <a href="<?php echo e(route('frontend.ranking.show', $series)); ?>" class="btn btn-sm btn-success">View Published Rankings</a>
              <?php else: ?>
                <a href="<?php echo e(route('series.settings', $series)); ?>" class="btn btn-sm btn-primary">Open Series Settings</a>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="alert alert-primary d-flex align-items-start gap-2 mt-3 mb-0 py-2" role="status">
        <i class="ti ti-arrow-right mt-1"></i>
        <div><strong>What to do next: <?php echo e($seriesNextStep['title']); ?>.</strong> <?php echo e($seriesNextStep['message']); ?></div>
      </div>
    </div>
  </div>

  <div class="row g-3">

    
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-trophy ti-md text-success"></i>
          <h5 class="mb-0">Rankings</h5>
        </div>

        <div class="card-body d-grid gap-2">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->leaderboard_published): ?>
            <a href="<?php echo e(route('frontend.ranking.show', $series)); ?>"
               class="btn btn-success">
              View Published Rankings
            </a>
          <?php elseif($activeRankingStatus === 'reviewed'): ?>
            <a href="<?php echo e(route('ranking.series.list', $series)); ?>" class="btn btn-outline-success">
              <i class="ti ti-mail-forward me-1"></i><?php echo e($reviewCampaign ? 'View Participant Review' : 'Share Rankings for Review'); ?>

            </a>
            <button type="button"
                    class="btn btn-success ranking-lifecycle-action"
                    data-url="<?php echo e(route('ranking.series.ranking.publish', $series)); ?>"
                    data-confirm="<?php echo e($reviewCampaign ? 'Close the participant review and publish these rankings? No ranking email will be sent.' : 'Publish this reviewed ranking? No ranking email will be sent.'); ?>">
              <i class="ti ti-world-upload me-1"></i><?php echo e($reviewCampaign ? 'Finalize & Publish Rankings' : 'Publish Rankings'); ?>

            </button>
          <?php elseif($activeRankingStatus === 'calculated'): ?>
            <a href="<?php echo e(route('ranking.series.list', $series)); ?>"
               class="btn btn-outline-info">
              <i class="ti ti-list-check me-1"></i>Review Rankings
            </a>
            <button type="button"
                    class="btn btn-info ranking-lifecycle-action"
                    data-url="<?php echo e(route('ranking.series.ranking.review', $series)); ?>"
                    data-modal-title="Accept ranking as correct?"
                    data-confirm="This confirms that you have reviewed the ranking totals, scores and tie decisions and accept them as correct. You can publish the ranking after this step."
                    data-confirm-label="Yes, Mark as Reviewed">
              <i class="ti ti-check me-1"></i>Mark Rankings Reviewed
            </button>
            <small class="text-muted">Review the details first. Marking reviewed records your acceptance and unlocks publication.</small>
          <?php else: ?>
            <button type="button" class="btn btn-outline-secondary" disabled>
              No Rankings Ready to Publish
            </button>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <form method="POST" action="<?php echo e(route('ranking.calculate', $series)); ?>">
            <?php echo csrf_field(); ?>
            <button class="btn btn-outline-success w-100">
              Recalculate Rankings
            </button>
          </form>
        </div>
      </div>
    </div>

    
    <div class="col-xl-4 col-md-6">
      <div class="card h-100 border-start border-warning border-3">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-adjustments ti-md text-warning"></i>
          <h5 class="mb-0">Series Setup</h5>
        </div>

      <div class="card-body d-grid gap-2">

  <a href="<?php echo e(route('series.events', $series)); ?>"
     class="btn btn-outline-secondary">
    Manage Events
  </a>

  <a href="<?php echo e(route('series.settings', $series)); ?>"
     class="btn btn-outline-warning">
    Series Settings
  </a>

  <a href="<?php echo e(route('ranking.points', $series)); ?>"
     class="btn btn-outline-primary">
    Points Allocation
  </a>

  <a href="<?php echo e(route('ranking.series.list', $series)); ?>"
     class="btn btn-outline-info">
    Ranking Lists
  </a>

  <a href="<?php echo e(route('ranking.series.audit', $series)); ?>"
     class="btn btn-outline-secondary">
    <i class="ti ti-clipboard-check me-1"></i>
    Audit Rankings
  </a>

  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#seriesEmailModal">
    <i class="ti ti-mail me-1"></i> Email All Players
  </button>

</div>

      </div>
    </div>

    
    <div class="col-xl-4 col-md-12">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-chart-bar ti-md text-info"></i>
          <h5 class="mb-0">Series Info</h5>
        </div>

        <div class="card-body">
          <ul class="list-unstyled mb-0 d-grid gap-1">
            <li>
              Events
              <span class="fw-semibold float-end"><?php echo e($stats['events']); ?></span>
            </li>
            <li>
              Rank Type
              <span class="fw-semibold float-end"><?php echo e($stats['rank_type']); ?></span>
            </li>
            <li>
              Best Results Counted
              <span class="fw-semibold float-end"><?php echo e($stats['best_of']); ?></span>
            </li>
          </ul>
        </div>
      </div>
    </div>

  </div>

  
  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0">Events in Series</h5>
    </div>

    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series->events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><?php echo e($event->name); ?></td>
              <td><?php echo e(optional($event->start_date)->format('d M Y')); ?></td>
              <td class="text-end">
                <a href="<?php echo e(route('admin.events.overview', $event)); ?>"
                   class="btn btn-sm btn-outline-primary">
                  Open Event
                </a>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>


<div class="modal fade" id="rankingLifecycleModal" tabindex="-1" aria-labelledby="rankingLifecycleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rankingLifecycleModalLabel">Confirm ranking action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="rankingLifecycleModalMessage"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Go Back</button>
        <button type="button" class="btn btn-info" id="confirmRankingLifecycleAction">
          Confirm
        </button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="seriesEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Email All Players in <?php echo e($series->name); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">From Name</label>
          <input type="text" id="seriesEmailFromName" class="form-control" value="Cape Tennis Admin">
        </div>
        <div class="mb-3">
          <label class="form-label">Reply To</label>
          <input type="email" id="seriesEmailReplyTo" class="form-control"
                 value="<?php echo e(auth()->user()->email ?? ''); ?>" placeholder="your@email.com">
        </div>
        <div class="mb-3">
          <label class="form-label">Subject <span class="text-danger">*</span></label>
          <input type="text" id="seriesEmailSubject" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Message <span class="text-danger">*</span></label>
          <div id="seriesEmailEditor" style="min-height: 200px;"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="btnSendSeriesEmail">
          <i class="ti ti-send me-1"></i> Review All Recipients
        </button>
      </div>
    </div>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const lifecycleModalElement = document.getElementById('rankingLifecycleModal');
  const lifecycleModal = new bootstrap.Modal(lifecycleModalElement);
  const lifecycleModalTitle = document.getElementById('rankingLifecycleModalLabel');
  const lifecycleModalMessage = document.getElementById('rankingLifecycleModalMessage');
  const lifecycleConfirmButton = document.getElementById('confirmRankingLifecycleAction');
  let pendingLifecycleButton = null;

  document.querySelectorAll('.ranking-lifecycle-action').forEach(button => {
    button.addEventListener('click', function () {
      pendingLifecycleButton = button;
      lifecycleModalTitle.textContent = button.dataset.modalTitle || 'Confirm ranking action';
      lifecycleModalMessage.textContent = button.dataset.confirm;
      lifecycleConfirmButton.textContent = button.dataset.confirmLabel || 'Confirm';
      lifecycleConfirmButton.className = button.classList.contains('btn-success')
        ? 'btn btn-success'
        : 'btn btn-info';
      lifecycleModal.show();
    });
  });

  lifecycleConfirmButton.addEventListener('click', async function () {
      const button = pendingLifecycleButton;
      if (!button) return;

      button.disabled = true;
      lifecycleConfirmButton.disabled = true;

      try {
        const response = await fetch(button.dataset.url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
          },
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Ranking action failed.');

        toastr.success(payload.message);
        lifecycleModal.hide();
        window.location.reload();
      } catch (error) {
        toastr.error(error.message || 'Ranking action failed.');
        button.disabled = false;
        lifecycleConfirmButton.disabled = false;
      }
  });

  lifecycleModalElement.addEventListener('hidden.bs.modal', function () {
    pendingLifecycleButton = null;
    lifecycleConfirmButton.disabled = false;
  });

  const quill = new Quill('#seriesEmailEditor', {
    theme: 'snow',
    placeholder: 'Compose your message...',
  });

  document.getElementById('seriesEmailModal').addEventListener('show.bs.modal', () => { window.seriesEmailCampaignKey = null; });
  document.getElementById('btnSendSeriesEmail').addEventListener('click', function () {
    const btn = this;
    const subject = document.getElementById('seriesEmailSubject').value.trim();
    const message = quill.root.innerHTML.trim();

    if (!subject) {
      toastr.error('Subject is required.');
      return;
    }
    window.seriesEmailCampaignKey ??= window.crypto?.randomUUID ? window.crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const value = Math.floor(Math.random() * 16); return (c === 'x' ? value : (value & 3) | 8).toString(16); });
    const seriesEmailCampaignKey = window.seriesEmailCampaignKey;
    if (!message || message === '<p><br></p>') {
      toastr.error('Message is required.');
      return;
    }



    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing review...';

    fetch("<?php echo e(route('series.email.players', $series)); ?>", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        campaign_key: seriesEmailCampaignKey,
        emailSubject: subject,
        message: message,
        fromName: document.getElementById('seriesEmailFromName').value.trim(),
        replyTo: document.getElementById('seriesEmailReplyTo').value.trim(),
      }),
    })
    .then(r => {
      if (!r.ok) throw new Error('Server returned ' + r.status);
      return r.json();
    })
    .then(data => {
      if (data.review_required && data.review_url) { window.CapeMailReview.open(data.review_url); return; }
      // A completed server response ends this intent even if queue submission failed.
      window.seriesEmailCampaignKey = null;
      if (data.report_url) window.location.assign(data.report_url);
      if (data.report_urls?.length > 1) {
        const reports = document.createElement('div');
        reports.className = 'alert alert-info mt-3';
        const heading = document.createElement('strong');
        heading.textContent = 'Per-event email reports';
        reports.append(heading);
        const list = document.createElement('ul');
        data.report_urls.forEach(report => {
          const item = document.createElement('li');
          const link = document.createElement('a');
          link.href = report.url;
          link.textContent = `Event ${report.event_id}: ${report.queued} queued, ${report.skipped} skipped, ${report.failed} queue failures`;
          item.append(link);
          list.append(item);
        });
        reports.append(list);
        document.querySelector('.container-xl').prepend(reports);
      }
      if (data.success) {
        window.seriesEmailCampaignKey = null;
        const feedback = data.skipped || data.failed ? 'warning' : 'success';
        toastr[feedback](data.message, 'Emails Queued', { timeOut: 5000, closeButton: true });
        bootstrap.Modal.getInstance(document.getElementById('seriesEmailModal')).hide();

        // Show persistent success alert on page
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show mt-3';
        alert.innerHTML = '<i class="ti ti-check me-2"></i><strong>Done!</strong> ' + data.message +
          '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.querySelector('.container-xl').prepend(alert);
      } else {
        toastr.error(data.message || 'Failed to send emails.', 'Error');
      }
    })
    .catch(err => {
      console.error(err);
      toastr.error('An error occurred while sending emails. Check the console for details.', 'Error', { timeOut: 5000 });
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-send me-1"></i> Review All Recipients';
    });
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\series-home.blade.php ENDPATH**/ ?>