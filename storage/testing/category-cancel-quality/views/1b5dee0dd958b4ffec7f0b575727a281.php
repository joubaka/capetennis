<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['priceCents' => null, 'plan' => 'offer', 'showPricing' => true]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['priceCents' => null, 'plan' => 'offer', 'showPricing' => true]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php ($priceCents = $priceCents ?? \App\Services\Performance\PlayerPlusService::priceCents()); ?>
<aside <?php echo e($attributes->class(['rounded p-3 plus-benefits-visual'])); ?> style="background:#fff;border:1px solid #dce5ef;color:#20384b" aria-label="Player Plus membership benefits" data-player-plus-benefits>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showPricing): ?>
  <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
    <strong>Optional Player Plus</strong>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan !== 'offer'): ?>
    <span style="color:#004177;font-size:1.25rem;font-weight:600">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> <small style="font-size:.8rem;font-weight:400">per player / <?php echo e($plan === 'monthly' ? 'month' : 'year'); ?></small></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan === 'offer'): ?><?php if (isset($component)) { $__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-pricing','data' => ['class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-pricing'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f)): ?>
<?php $attributes = $__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f; ?>
<?php unset($__attributesOriginalcb87716e04f6f80d0db3faaf0d26cc5f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f)): ?>
<?php $component = $__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f; ?>
<?php unset($__componentOriginalcb87716e04f6f80d0db3faaf0d26cc5f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="small mt-2 mb-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($plan === 'monthly'): ?>Monthly access for one named player, automatically renewed through PayFast until the payer cancels.<?php elseif($plan === 'annual_recurring'): ?>Annual access for one named player, automatically renewed through PayFast until the payer cancels.<?php elseif($plan === 'annual'): ?>12 months for one named player. This existing once-off purchase has no automatic renewal.<?php else: ?> Review the available plans for one named player.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> Shared with the player and their linked parents or guardians.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="fw-semibold mb-2">Know your game. Find your next step.</p>
  <p class="small mb-2">Ratings, progress and tournament guidance for junior players, beginners and their families.</p>
  <p class="small text-muted mb-3">Eligible results are needed for a rating; purchasing Plus does not guarantee one.</p>
    <ul class="small ps-3 mb-2">
      <li><strong class="plus-benefit-title"><i class="ti ti-chart-line" aria-hidden="true"></i>How am I progressing?</strong>Follow monthly reports, recent form and up to 12 months of rating history.</li>
      <li><strong class="plus-benefit-title"><?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['class' => 'me-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'me-1']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>What is my rating?</strong>See your Cape Tennis Rating (CTR), its supporting results and your age-group Top 10 where available.</li>
      <li><strong class="plus-benefit-title"><i class="ti ti-trophy" aria-hidden="true"></i>How do I compare with my opponent?</strong>Compare available opponent ratings and revisit your recorded meetings. Ratings must share a comparable scale.</li>
      <li><strong class="plus-benefit-title"><i class="ti ti-route" aria-hidden="true"></i>What could I play next?</strong>Explore ball-level progress and event options from Red Ball to Wilson and TSA. Plan with your coach and check entry rules.</li>
    </ul>
  <?php if (isset($component)) { $__componentOriginal3a9503c29e35807b9717ef2aa89db7a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3a9503c29e35807b9717ef2aa89db7a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-morning-updates','data' => ['class' => 'my-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-morning-updates'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'my-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3a9503c29e35807b9717ef2aa89db7a2)): ?>
<?php $attributes = $__attributesOriginal3a9503c29e35807b9717ef2aa89db7a2; ?>
<?php unset($__attributesOriginal3a9503c29e35807b9717ef2aa89db7a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3a9503c29e35807b9717ef2aa89db7a2)): ?>
<?php $component = $__componentOriginal3a9503c29e35807b9717ef2aa89db7a2; ?>
<?php unset($__componentOriginal3a9503c29e35807b9717ef2aa89db7a2); ?>
<?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    <a class="d-inline-block py-2" style="min-height:44px" href="<?php echo e(route('player-plus.notifications')); ?>">Manage your Plus email updates</a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <details>
    <summary class="small py-2" style="min-height:44px; cursor:pointer">How membership works</summary>
    <p class="small mb-2">Ratings can be provisional or unavailable. Membership does not change a rating, ranking, selection or tournament entry.</p>
    <p class="small mb-2">Pathway guidance supports discussion with a coach. It does not automatically promote a player or guarantee selection.</p>
    <p class="small mb-2">Paying for a player does not link them to your account or give you access to their private information.</p>
    <p class="small mb-0">Account signup and essential tournament information remain free. Plus is optional. Event email updates, including draw alerts, are coming soon and are not included in the current purchase.</p>
  </details>
  <a class="small d-inline-block py-2" style="min-height:44px" href="<?php echo e(route('player-plus.terms')); ?>">Purchase details and payment support</a>
  <style>
    .plus-benefits-visual { border-radius:16px!important;padding:clamp(1.25rem,3vw,1.75rem)!important; }
    .plus-benefits-visual > ul { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;list-style:none;padding:0!important;margin:1.25rem 0!important; }
    .plus-benefits-visual > ul > li { padding:1.1rem;background:#f2f6fb;border:1px solid #dce5ef;border-radius:12px;line-height:1.7;min-width:0; }
    .plus-benefits-visual .plus-benefit-title { display:flex;align-items:center;gap:.6rem;color:#172e45;font-size:.95rem;margin-bottom:.65rem; }
    .plus-benefits-visual .plus-benefit-title .ti { display:grid;place-items:center;flex:0 0 2.1rem;height:2.1rem;background:#e3edf7;color:#004177;border-radius:9px;font-size:1.25rem; }
    .plus-benefits-visual > ul > li:nth-child(4) { background:#fff0e1;border-color:#f5cfaa; }
    .plus-benefits-visual > ul > li:nth-child(4) .ti { background:#ffdfbf;color:#794313; }
    .plus-benefits-visual details { border-top:1px solid #dce5ef;margin-top:1rem;padding-top:.4rem; }
    @media(max-width:575.98px) { .plus-benefits-visual > ul { grid-template-columns:1fr; } }
  </style>
</aside>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\player-plus-benefits.blade.php ENDPATH**/ ?>