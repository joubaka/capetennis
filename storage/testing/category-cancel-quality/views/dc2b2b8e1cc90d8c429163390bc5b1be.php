<details id="pathway" class="pathway-public ctr-disclosure" aria-labelledby="public-pathway-title" data-public-pathway>
  <summary><h2 id="public-pathway-title">Explore the tournament pathway</h2></summary>
  <p>Explore with your player or coach. Published event information stays free; Player Plus adds personal rating context where evidence is available.</p>
  <?php echo $__env->make('frontend.my-tennis.pathway-journey', ['journeyPublic' => true, 'publicPathwayTargets' => config('pathway.targets', [])], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <details class="ctr-disclosure"><summary>See a fictional Player Plus example</summary>
  <aside class="pathway-demo" aria-label="Fictional Player Plus pathway example">
    <span class="pathway-eyebrow">Fictional example · no actual player data</span>
    <h3 class="h4 mt-2">Your game. Your next challenge.</h3>
    <p>A personal Plus pathway puts your saved ball-level rating in context, shows your current guidance stage when evidence supports it, and brings relevant published opportunities together.</p>
    <?php
      $sampleLower = config('pathway.wilson_a_above');
      $sampleUpper = config('pathway.tsa_above');
      $sampleBandsValid = is_numeric($sampleLower) && is_numeric($sampleUpper) && is_finite((float) $sampleLower) && is_finite((float) $sampleUpper) && $sampleLower >= 0 && $sampleUpper <= 100 && $sampleUpper > $sampleLower;
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleBandsValid): ?>
      <?php ($sampleScore = ($sampleLower + $sampleUpper) / 2); ?>
      <div class="pathway-sample-card"><span class="pathway-eyebrow">Example personal view</span><h4 class="h5">Wilson A · You are here</h4><p>Fictional Green/Yellow CTR: <strong><?php echo e(number_format($sampleScore, 1)); ?></strong></p>
        <div class="pathway-band" role="img" aria-label="Fictional Green/Yellow CTR <?php echo e(number_format($sampleScore, 1)); ?>, within the Wilson A guidance band."><span class="pathway-band-marker" style="left:<?php echo e($sampleScore); ?>%"><?php echo e(number_format($sampleScore, 1)); ?> ↓</span><div class="pathway-band-track" aria-hidden="true"><span style="width:<?php echo e($sampleLower); ?>%"></span><span style="width:<?php echo e($sampleUpper - $sampleLower); ?>%"></span><span style="width:<?php echo e(100 - $sampleUpper); ?>%"></span></div><div class="pathway-band-labels" aria-hidden="true"><span>Wilson B</span><span>Wilson A</span><span>TSA</span></div></div>
        <p class="small mb-0">Next step: explore Wilson A opportunities with your coach and check event requirements.</p></div>
    <?php else: ?>
      <p>Sample rating bands are unavailable while guidance configuration is reviewed.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="small">Sample only. Your view depends on your own comparable published evidence. No rating or event availability is guaranteed.</p>
    <a href="#ctr-offer-title">Explore Player Plus <span aria-hidden="true">→</span></a>
  </aside>
  </details>
  <section id="pathway-events" aria-labelledby="public-pathway-events-title">
    <h3 class="h4" id="public-pathway-events-title">Explore published opportunities</h3>
    <nav class="pathway-filter" aria-label="Choose an event stage">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['red' => 'Red ball', 'orange' => 'Orange ball', 'Wilson B' => 'Wilson B', 'Wilson A' => 'Wilson A', 'TSA tournaments' => 'TSA']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stageKey => $stageLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stageKey === 'TSA tournaments'): ?>
          <a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">TSA · external <span class="visually-hidden">(opens in a new tab)</span></a>
        <?php else: ?>
          <a href="<?php echo e(route('ctr.index', ['stage' => $stageKey])); ?>#pathway-events" <?php if($publicPathwayStage === $stageKey): ?> aria-current="page" <?php endif; ?>><?php echo e($stageLabel); ?></a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </nav>
    <p class="small"><?php echo e($publicPathwayStage); ?> opportunities · check age, division and entry requirements. This public view is not a personal eligibility assessment. TSA is an external circuit, not a Cape Tennis hosted classification.</p>
    <div class="pathway-public-events">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $publicPathwayEvents['events']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $publicEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <article class="pathway-public-event"><span class="badge bg-label-primary"><?php echo e($publicPathwayStage); ?></span><h4 class="h6 mt-2"><a href="<?php echo e($publicEvent['url']); ?>"><?php echo e($publicEvent['name']); ?></a></h4><p class="small"><?php echo e($publicEvent['date']); ?> · <?php echo e($publicEvent['venue']); ?></p><p class="small"><?php echo e(implode(', ', $publicEvent['categories'])); ?></p><strong class="small"><?php echo e($publicEvent['status']); ?></strong><p class="mt-2 mb-0"><a href="<?php echo e($publicEvent['url']); ?>">Check entry requirements →</a></p></article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <p>No upcoming published events with confirmed matching stage details are listed here yet. <a href="<?php echo e(route('home')); ?>">Browse all events</a> or discuss other opportunities with your coach.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicPathwayEvents['limited']): ?><p class="small mt-2">This is a limited selection. <a href="<?php echo e(route('home')); ?>">Browse all events</a>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publicPathwayStage === 'TSA tournaments'): ?><p class="mt-3"><a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">Explore the TSA website <span class="visually-hidden">(opens in a new tab)</span></a></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
</details>
<details class="ctr-disclosure"><summary>Share the pathway with players and parents</summary>
<section id="pathway-coach-kit" class="pathway-public" aria-labelledby="pathway-coach-title" data-pathway-share>
  <img class="pathway-print-poster" src="<?php echo e(asset('assets/img/pathway-coach-poster.svg')); ?>" alt="Cape Tennis pathway court poster with the public guide QR code" width="1080" height="1350">
  <p class="pathway-eyebrow">For coaches, players and parents</p><h2 id="pathway-coach-title">Share a bigger picture of their tennis.</h2>
  <p>Start a conversation about their next tournament. Share this public guide with your academy or family group.</p>
  <div class="pathway-share-grid"><div>
    <label class="fw-semibold mb-2" for="pathway-share-message">Ready-to-share message</label>
    <textarea id="pathway-share-message" readonly>Not sure which tournament to enter next? Explore the Cape Tennis pathway, from Red and Orange ball through Wilson and TSA opportunities. See how it works, then explore Player Plus for your player's personal pathway. https://capetennis.co.za/ctr#pathway</textarea>
    <div class="pathway-share-actions"><button type="button" class="btn btn-primary" data-pathway-copy>Copy message</button><a class="btn btn-outline-primary" href="https://wa.me/?text=<?php echo e(rawurlencode('Explore the Cape Tennis pathway with your player: https://capetennis.co.za/ctr#pathway — discover tournament opportunities and explore Player Plus for your personal view.')); ?>" target="_blank" rel="noopener noreferrer">Share on WhatsApp <span class="visually-hidden">(opens in a new tab)</span></a><button type="button" class="btn btn-outline-primary" data-pathway-print>Print court poster</button></div>
    <p class="small mt-2" role="status" data-pathway-share-status></p>
    <p class="small">Public guide: <a href="https://capetennis.co.za/ctr#pathway">capetennis.co.za/ctr#pathway</a>. Sharing does not expose any player’s private ratings or send a message automatically.</p>
  </div><div><img class="pathway-share-qr" src="<?php echo e(asset('assets/img/pathway-share-qr.svg')); ?>" alt="QR code opening the public Cape Tennis pathway guide" width="180" height="180" loading="lazy"><p><a href="<?php echo e(asset('assets/img/pathway-share-qr.svg')); ?>" download="cape-tennis-pathway-qr.svg">Download QR code</a></p><p><a href="<?php echo e(asset('assets/img/pathway-coach-poster.svg')); ?>" download="cape-tennis-pathway-poster.svg">Download coach poster</a></p></div></div>
</section>
</details>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-public.blade.php ENDPATH**/ ?>