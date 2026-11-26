<?php
  $activeTab = $workspaceTab ?? 'profile';
  $tabs = ['profile' => ['Profile', route('my.tennis', $headerQuery + ['tab' => 'profile'])]];
  if (($selectedPlayer ?? null) || ($workspace['related'] ?? false)) {
      $tabs['activity'] = ['Activity', route('my.tennis', $headerQuery + ['tab' => 'activity'])];
  }
  if ($headerPlayer && $headerCanViewRating) {
      $tabs += [
          'rating' => ['Rating', $headerRatingUrl],
          'record' => ['Results', route('my.tennis.record', $headerPlayer)],
          'opponents' => ['Opponents', route('my.tennis.record', ['player' => $headerPlayer, 'section' => 'opponents'])],
          'pathway' => ['Tournament options', route('my.tennis.pathway', $headerPlayer)],
      ];
  } elseif ($headerPlayer && $headerRelated) {
      foreach (['rating' => 'Rating', 'record' => 'Results', 'opponents' => 'Opponents', 'pathway' => 'Tournament options'] as $feature => $label) {
          $tabs[$feature] = [$label, route('my.tennis.feature-preview', ['player' => $headerPlayer, 'feature' => $feature])];
      }
  }
  $previewTabs = !$headerCanViewRating ? ['rating', 'record', 'opponents', 'pathway'] : [];
?>
<nav class="ct-my-tennis-nav" id="my-tennis-tabs" aria-label="My Tennis views">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab => [$label, $url]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php ($isPreviewTab = in_array($tab, $previewTabs, true)); ?>
    <a <?php if($isPreviewTab): ?> data-plus-preview-tab <?php else: ?> data-my-tennis-link <?php endif; ?> href="<?php echo e($url); ?>" <?php if($tab === $activeTab): ?> aria-current="page" <?php endif; ?>
      <?php if($tab === 'rating' && $headerCanViewRating): ?> id="my-tennis-rating-tab" <?php endif; ?>><span><?php echo e($label); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPreviewTab): ?><i class="ti ti-lock" aria-hidden="true"></i><span class="visually-hidden"> · Plus preview</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</nav>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\dashboard-tabs.blade.php ENDPATH**/ ?>