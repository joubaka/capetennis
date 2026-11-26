<?php $__env->startSection('title', 'Your tennis pathway'); ?>
<?php $__env->startSection('content'); ?>
<div data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-player-pathway-page>
  <?php
    $pathwayHeaderSnapshot = $workspace['snapshot'];
    if (isset($pathway['move']) && $pathway['move']['score'] === null) {
      $badge = $pathway['badge'];
      $pathwayHeaderSnapshot = [
        'stage' => $badge['stage'],
        'points' => isset($badge['score'], $pathway['metrics']['date']) ? [[
          'score' => $badge['score'], 'date' => $pathway['metrics']['date'],
          'connection_status' => $badge['local'] ? 'disconnected' : null,
        ]] : [],
      ];
    }
  ?>
  <?php echo $__env->make('frontend.my-tennis.profile-header', ['workspaceTab' => 'pathway', 'ratingSnapshot' => $pathwayHeaderSnapshot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('frontend.my-tennis.workspace-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->make(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-page.blade.php ENDPATH**/ ?>