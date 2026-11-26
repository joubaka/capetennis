<?php $__env->startSection('title', 'Preview Plus'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace">
  <?php echo $__env->make('frontend.my-tennis.profile-header', [
    'canViewRating' => false,
    'ratingPreviewPlayer' => $ratingPlayer,
    'workspaceTab' => $previewFeature === 'events' ? 'rating' : $previewFeature,
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <script defer src="<?php echo e(asset('assets/js/my-tennis-player-switch.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/my-tennis-player-switch.js'))); ?>"></script>
  <div class="card p-3 p-sm-4" data-player-plus-preview-page>
    <style>
      .plus-sample{background:#fff;border:1px solid #cbd9e9;border-radius:.75rem;margin:1rem 0;padding:1rem}
      .plus-sample dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(8rem,1fr));gap:.75rem;margin:.75rem 0 0}
      .plus-sample dl>div{background:#f1f5fa;border-radius:.5rem;padding:.75rem;min-width:0}
      .plus-sample dd{color:#004177;font-size:1.15rem;font-weight:700;margin:0;overflow-wrap:anywhere}
    </style>
    <?php echo $__env->make('frontend.my-tennis.feature-preview', ['showSample' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\feature-preview-page.blade.php ENDPATH**/ ?>