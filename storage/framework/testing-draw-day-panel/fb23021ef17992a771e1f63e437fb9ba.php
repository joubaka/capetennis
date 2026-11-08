<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\Performance\PlayerRatingBadgeService::visible()): ?>
<style>@media print { .player-rating-badge { display:none !important; } }</style>
<?php
    // Use the same component for server-rendered badges and badges for dynamically loaded rows; only escaped data is substituted in JS.
    $stampTemplates = [];
    foreach (['unknown' => null, 'red' => 'red', 'orange' => 'orange', 'green' => 'green', 'yellow' => 'yellow', 'standard' => 'standard'] as $stageKey => $ballStage) {
        foreach ([false, true] as $localStamp) {
            $stampTemplates[$stageKey][$localStamp ? 'local' : 'shared'] = str_replace('98.7', '__CTR_SCORE__',
                \Illuminate\Support\Facades\Blade::render('<x-ctr-badge size="inline" class="badge player-rating-badge ms-1" :score="98.7" :local="$local" :stage="$stage" title="__CTR_TITLE__" />', ['local' => $localStamp, 'stage' => $ballStage]));
        }
    }
?>
<script>
window.CTPlayerRatingConfig = {
  endpoint: <?php echo json_encode(route('backend.player-performance.badges'), 15, 512) ?>,
  drawId: <?php echo json_encode(isset($draw) && $draw instanceof \App\Models\Draw ? $draw->id : null, 15, 512) ?>,
  stampTemplates: <?php echo json_encode($stampTemplates, 15, 512) ?>
};
</script>
<script src="<?php echo e(asset('assets/js/player-rating-badges.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/player-rating-badges.js'))); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/draw/partials/player-rating-assets.blade.php ENDPATH**/ ?>