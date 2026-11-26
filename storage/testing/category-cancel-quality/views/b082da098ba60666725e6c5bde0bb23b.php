

<?php $__env->startSection('title', 'Event Details'); ?>


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/spinkit/spinkit.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-profile.css')); ?>">
<style>
  .event-ctr-certified {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .3rem .75rem .3rem .35rem;
    border: 1px solid #dce5ef;
    border-radius: 999px;
    background: #f1f5f9;
    color: #172e45;
    font-size: .8rem;
    font-weight: 700;
    margin-bottom: .75rem;
  }
  .event-ctr-certified .ti { color: #285a87; }
  .event-withdraw-entry { max-width: 100%; white-space: normal; text-align: left; min-height: 44px; }
  .event-withdraw-entry > span { min-width: 0; overflow-wrap: anywhere; }
  .event-withdraw-entry small { display: block; font-weight: 400; }
  .public-event-view .event-section-card {
    border: 1px solid #e1e8ee;
    box-shadow: 0 .25rem 1rem rgba(23, 46, 69, .06);
  }

  .user-profile-header-banner img {
    height: 132px;
    object-position: center 44%;
  }

  .user-profile-header { margin-top: -2.5rem; }

  .user-profile-header h4 {
    color: #172e45;
    font-size: clamp(1.25rem, 2.2vw, 1.65rem);
    font-weight: 650;
  }

  .public-event-view .event-section-heading {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
  }

  .public-event-view .event-section-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: .65rem;
    background: rgba(20, 121, 110, .12);
    color: #14796e;
  }

  .public-event-view .event-section-icon-svg {
    fill: none;
    height: 1.35rem;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.8;
    width: 1.35rem;
  }

  .public-event-view .event-information-content {
    color: #4b465c;
    font-size: .975rem;
    line-height: 1.75;
    overflow-wrap: anywhere;
  }

  .public-event-view .event-information-content > :last-child {
    margin-bottom: 0;
  }

  .public-event-view .event-information-content p {
    margin-bottom: 1rem;
  }

  .public-event-view .event-information-content h1,
  .public-event-view .event-information-content h2,
  .public-event-view .event-information-content h3,
  .public-event-view .event-information-content h4,
  .public-event-view .event-information-content h5,
  .public-event-view .event-information-content h6 {
    margin-top: 1.75rem;
    margin-bottom: .65rem;
    color: #2f2b3d;
    font-size: 1.05rem;
    font-weight: 600;
  }

  .public-event-view .event-information-content ul,
  .public-event-view .event-information-content ol {
    padding-left: 1.3rem;
    margin-bottom: 1rem;
  }

  .public-event-view .event-information-content li + li {
    margin-top: .35rem;
  }

  .public-event-view .event-document {
    padding: .75rem;
    border: 1px solid rgba(75, 70, 92, .12);
    border-radius: .5rem;
  }

  .public-event-view .event-category {
    border-color: #e1e8ee !important;
    overflow: hidden;
  }

  .public-event-view .event-category .list-group-item {
    gap: .75rem;
    padding-inline: .75rem;
  }

  .individual-event-view .event-draws-card {
    border: 1px solid #dbe5ec;
    box-shadow: 0 .35rem 1.25rem rgba(23, 46, 69, .08);
    overflow: hidden;
  }
  .individual-event-view .event-draws-header {
    align-items: flex-start;
    background: linear-gradient(135deg, rgba(20, 121, 110, .09), rgba(60, 105, 180, .06));
    border-bottom: 1px solid #e2eaf0;
    display: flex;
    gap: .75rem;
  }
  .individual-event-view .event-draws-header-icon {
    align-items: center;
    background: #14796e;
    border-radius: .65rem;
    color: #fff;
    display: inline-flex;
    flex: 0 0 auto;
    font-size: 1.2rem;
    height: 2.5rem;
    justify-content: center;
    width: 2.5rem;
  }
  .individual-event-view .event-draw-grid { display: grid; gap: .5rem; }
  .individual-event-view .event-draw-card {
    background: linear-gradient(180deg, #fff 0%, #fbfcfd 100%);
    border: 1px solid #dbe5ec;
    border-left: 3px solid #14796e;
    border-radius: .75rem;
    display: grid;
    gap: .65rem;
    grid-template-columns: minmax(0, 1fr);
    padding: .75rem;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
  }
  .individual-event-view .event-draw-card:hover {
    border-color: #a9c9c4;
    box-shadow: 0 .4rem 1rem rgba(23, 46, 69, .08);
    transform: translateY(-1px);
  }
  .individual-event-view .event-draw-card-heading {
    align-items: flex-start;
    display: flex;
    gap: .65rem;
    justify-content: space-between;
  }
  .individual-event-view .event-draw-name {
    overflow-wrap: anywhere;
    color: #172e45;
    font-size: 1rem;
    font-weight: 750;
    line-height: 1.3;
    min-width: 0;
  }
  .individual-event-view .event-draw-state {
    background: rgba(20, 121, 110, .1);
    border-radius: 999px;
    color: #11665d;
    flex: 0 1 auto;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .02em;
    padding: .25rem .5rem;
    text-transform: uppercase;
  }
  .individual-event-view .event-draw-meta {
    color: #65788b;
    display: grid;
    font-size: .82rem;
    gap: .25rem;
    margin-top: .35rem;
  }
  .individual-event-view .event-draw-meta > span {
    align-items: start;
    display: grid;
    gap: .45rem;
    grid-template-columns: 1rem minmax(0, 1fr);
    line-height: 1.35;
  }
  .individual-event-view .event-draw-meta > span > i {
    color: #14796e;
    font-size: 1rem;
    margin-top: .05rem;
  }
  .individual-event-view .event-draw-meta > span > span { overflow-wrap: anywhere; min-width: 0; }
  .individual-event-view .event-draw-card-heading { flex-wrap: wrap; }
  .individual-event-view .event-draw-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: flex-start;
  }
  .individual-event-view .event-draw-actions .btn {
    align-items: center;
    display: inline-flex;
    flex: 1 1 6rem;
    justify-content: center;
    min-height: 2.75rem;
  }
  .individual-event-view .event-draw-actions .event-draw-score {
    flex: 0 0 auto;
    padding-inline: .7rem;
  }
  @media (min-width: 992px) {
    .individual-event-view .event-draw-card {
      align-items: center;
      column-gap: 1rem;
      grid-template-columns: minmax(0, 1fr) minmax(0, auto);
      padding: .65rem .8rem;
    }
    .individual-event-view .event-draw-card > div { min-width: 0; }
    .individual-event-view .event-draw-actions {
      justify-content: flex-end;
      max-width: 28rem;
    }
    .individual-event-view .event-draw-actions .btn {
      flex: 0 1 auto;
      min-height: 2.25rem;
    }
  }
  #event-draws-match-times { scroll-margin-top: 8rem; }
  #event-draws-match-times:focus,
  #event-draws-match-times:target {
    outline: 3px solid rgba(20, 121, 110, .28);
    outline-offset: 2px;
  }

  @media (max-width: 767.98px) {
    .public-event-view .card-body,
    .public-event-view .event-card-padding {
      padding: 1.1rem !important;
    }

    .public-event-view .event-information-content {
      font-size: .925rem;
      line-height: 1.65;
    }

    .user-profile-header-banner img { height: 88px; }
    .user-profile-header { margin-top: -2.25rem; }
    .user-profile-header .user-profile-img { height: 76px; max-width: 76px; width: 76px; }
    .user-profile-header .flex-grow-1 { margin-top: .75rem !important; }
    .user-profile-header .mx-4 { margin-inline: 1.1rem !important; }
    .public-event-view .event-category .list-group-item { align-items: flex-start !important; flex-direction: column; }
    .individual-event-view .event-draw-card { padding: .9rem; }
    .individual-event-view .event-draw-actions .btn { min-height: 44px; }
    .individual-event-view .event-draw-actions .btn:not(.event-draw-score) { flex-basis: 100%; }
  }

  @media (max-width: 991.98px) {
    .user-profile-header .user-profile-img {
      width: 88px;
    }
  }

  .user-profile-header .user-profile-img {
    height: 120px;
    max-width: 120px;
    object-fit: contain;
  }

  @media (max-width: 991.98px) {
    .user-profile-header .user-profile-img {
      height: 88px;
      max-width: 88px;
    }
  }
</style>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/pickr/pickr.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/quill/katex.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/quill/quill.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<input type="hidden" id="event_id" value="<?php echo e($event->id); ?>">

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success') && !session('masters_decline_success')): ?>
  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="ti ti-check me-1"></i> <?php echo e(session('success')); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<div class="row event-hero">
  <div class="col-12">
    <div class="card mb-4">
      <div class="user-profile-header-banner">
        <img src="<?php echo e(asset('assets/img/pages/profile-banner.png')); ?>" class="rounded-top" alt="">
      </div>

      <div class="user-profile-header d-flex flex-column flex-sm-row mb-4">
        <div class="flex-shrink-0 mt-n2 mx-sm-0 mx-auto">
          <img
            src="<?php echo e($event->logo ? asset('assets/img/logos/'.$event->logo) : asset('assets/img/misc/placeholder-logo.png')); ?>"
            class="rounded user-profile-img"
            alt="<?php echo e($event->name); ?> logo">
        </div>

        <div class="flex-grow-1 mt-3 mt-sm-5">
          <div class="mx-4">
            <h4><?php echo e($event->name); ?></h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isCtrCertified()): ?>
              <span class="event-ctr-certified" aria-label="CTR Certified">
                <?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
                <span><span class="visually-hidden">CTR </span>Certified</span>
                <i class="ti ti-shield-check" aria-hidden="true"></i>
              </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <ul class="list-inline d-flex gap-2 flex-wrap">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isIndividual()): ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->venue_notes): ?>
                <li class="list-inline-item">
                  <i class="ti ti-map-pin"></i> <?php echo e($event->venue_notes); ?>

                </li>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
            <div class="d-inline-flex align-items-center gap-2 flex-wrap rounded bg-label-primary px-3 py-2" aria-label="Tournament match progress">
              <i class="ti ti-ball-tennis" aria-hidden="true"></i>
              <span><strong><?php echo e(number_format($matchProgress['finished'])); ?> / <?php echo e(number_format($matchProgress['total'])); ?></strong> matches finished</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php echo $__env->make('frontend.event.partials.team-payment-recovery', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<div class="row">
  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row mb-4 align-items-center">

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signUp === 'open' && $event->isIndividual() && ! $event->isMasters() && ! $event->isInterprovincialTrials()
    && (int) $event->id !== \App\Services\CavaliersTrialCandidates::TRIALS_EVENT): ?>
  <a class="btn btn-success btn-sm m-2" href="<?php echo e(auth()->check() ? route('register.register', $event->id) : route('login', ['redirect' => route('register.register', $event->id, false)])); ?>">
    <i class="ti ti-user-check"></i> Sign Up
  </a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('masters_decline_success')): ?>
  <div class="modal fade" id="mastersDeclineSuccessModal" tabindex="-1" aria-labelledby="mastersDeclineSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="mastersDeclineSuccessModalLabel"><i class="ti ti-circle-check text-success me-1"></i> Invitation response recorded</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-0"><?php echo e(session('masters_decline_success')); ?></div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if( $event->isIndividual()): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $userRegistrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php
    $canWithdraw = auth()->check()
      ? $registration->canWithdraw(auth()->user())
      : ['ok' => false];
  ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canWithdraw['ok']): ?>
  <button type="button"
          class="btn btn-danger btn-sm m-1 withDrawPlayer event-withdraw-entry"
          data-url="<?php echo e(route('registrations.withdraw', $registration)); ?>"
          data-player-name="<?php echo e($registration->display_name); ?>"
          data-division-name="<?php echo e($registration->categoryEvent?->category?->name ?? 'Division not specified'); ?>"
          aria-label="Withdraw <?php echo e($registration->display_name); ?> from <?php echo e($registration->categoryEvent?->category?->name ?? 'Division not specified'); ?>">
    <i class="ti ti-x me-1" aria-hidden="true"></i>
    <span>Withdraw <?php echo e($registration->display_name); ?>

      <small><?php echo e($registration->categoryEvent?->category?->name ?? 'Division not specified'); ?></small>
    </span>
  </button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->status === 'withdrawn'): ?>
  <div class="mt-1">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->refund_status === 'pending' && $registration->refund_method === 'bank'): ?>
      <span class="btn btn-outline-warning btn-sm disabled">
        <i class="ti ti-clock"></i>
        <?php echo e($registration->display_name); ?> – Bank refund pending
      </span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) auth()->id() === (int) $registration->user_id): ?>
        <a href="<?php echo e(route('registrations.refund.choose', $registration)); ?>" class="btn btn-outline-primary btn-sm">Review bank details</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php elseif($registration->refund_status === 'completed' && $registration->refund_method === 'bank'): ?>
      <span class="btn btn-outline-success btn-sm disabled">
        <i class="ti ti-check"></i>
        <?php echo e($registration->display_name); ?> – Bank refund completed
      </span>

    <?php elseif($registration->refund_status === 'completed' && $registration->refund_method === 'wallet'): ?>
      <span class="btn btn-outline-primary btn-sm disabled">
        <i class="ti ti-wallet"></i>
        <?php echo e($registration->display_name); ?> – Refunded to wallet
      </span>

    <?php else: ?>
      <span class="btn btn-outline-secondary btn-sm disabled">
        <i class="ti ti-minus"></i>
        <?php echo e($registration->display_name); ?> – No refund
      </span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventWideAdministrator): ?>
  <a class="btn btn-secondary m-2" href="<?php echo e(route('admin.events.overview',$event)); ?>">
    <i class="ti ti-shield ti-xs"></i> Administrator
  </a>
<?php elseif($canManageRegionalTeamSelection): ?>
  <a class="btn btn-primary m-2" href="<?php echo e(route('backend.team-selection.index', $event)); ?>">
    <i class="ti ti-map-pin-cog ti-xs"></i> Regional administration
  </a>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </ul>
  </div>
</div>


<div class="public-event-view">
  <?php echo $__env->make('frontend.event.partials._convener-standings', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('frontend.event.partials._type-content', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-script'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/event-rich-text.css')); ?>">
<script src="<?php echo e(asset('js/event-rich-text.js')); ?>"></script>

<?php echo $__env->make('frontend.event.partials.team-payment-recovery-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<script>
'use strict';

window.APP_URL  = <?php echo json_encode(url('/'), 15, 512) ?>;
window.EVENT_ID = <?php echo json_encode($event->id, 15, 512) ?>;
window.auth = {
  loggedIn: <?php echo json_encode(auth()->check(), 15, 512) ?>,
  loginUrl: <?php echo json_encode(route('login'), 15, 512) ?>
};
/* ===============================
   DEBUG: EVENT DATE DATA
=============================== */
window.EVENT_DATA = {
  id: <?php echo e($event->id); ?>,
  start_date: <?php echo json_encode(optional($event->start_date)?->toDateTimeString(), 15, 512) ?>,
  end_date: <?php echo json_encode(optional($event->end_date)?->toDateTimeString(), 15, 512) ?>,
  entry_deadline: <?php echo json_encode(optional($entryCloseAt)->toDateTimeString(), 15, 512) ?>,
  withdrawal_deadline: <?php echo json_encode(optional($event->withdrawal_deadline)?->toDateTimeString(), 15, 512) ?>,
};

console.log('[EVENT_DATA]', window.EVENT_DATA);

<?php if(session('masters_decline_success')): ?>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('mastersDeclineSuccessModal');
  if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
});
<?php endif; ?>


</script>

<script src="<?php echo e(asset('assets/js/pages-profile.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/forms-editors.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/select2-search-addon.js')); ?>"></script>
<script src="<?php echo e(asset(mix('js/event-show.js'))); ?>?v=<?php echo e(config('app.asset_version')); ?>"></script>



<script>
$(document).on('click', '.deleteFileButton', function (e) {
  e.preventDefault();

  const fileId = $(this).data('id');
  if (!fileId) return;

  const url = "<?php echo e(route('file.destroy', '__ID__')); ?>".replace('__ID__', fileId);

  Swal.fire({
    title: 'Delete file?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete'
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.post(url, {
      _method: 'DELETE',
      _token: $('meta[name="csrf-token"]').attr('content')
    }).done((data) => {
      if (!data || data.success !== true) {
        Swal.fire('Error', data?.msg || 'Delete failed. Please try again.', 'error');
        return;
      }
      location.reload();
    }).fail((response) => {
      Swal.fire('Error', response.responseJSON?.msg || 'The file could not be deleted. Please try again.', 'error');
    });
  });
});
</script>
<?php echo $__env->make('frontend.event.partials.player-clothing-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\show.blade.php ENDPATH**/ ?>