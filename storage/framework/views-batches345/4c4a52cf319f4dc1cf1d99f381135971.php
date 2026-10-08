<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\Performance\PlayerRatingBadgeService::visible()): ?>
<style>@media print { .player-rating-badge { display:none !important; } }</style>
<script>
window.CTPlayerRatingConfig = {
  endpoint: <?php echo json_encode(route('backend.player-performance.badges'), 15, 512) ?>,
  drawId: <?php echo json_encode(isset($draw) && $draw instanceof \App\Models\Draw ? $draw->id : null, 15, 512) ?>
};
</script>
<script src="<?php echo e(asset('assets/js/player-rating-badges.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/player-rating-badges.js'))); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\draw\partials\player-rating-assets.blade.php ENDPATH**/ ?>