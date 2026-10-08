
<?php
  $hasCrit = collect($items)->where('status', 'critical')->count() > 0;
  $hasWarn = collect($items)->where('status', 'warn')->count() > 0;
  $badgeClass = $hasCrit ? 'badge-critical' : ($hasWarn ? 'badge-warn' : 'badge-ok');
  $badgeText  = $hasCrit ? 'CRITICAL' : ($hasWarn ? 'WARN' : 'OK');
?>
<span class="badge <?php echo e($badgeClass); ?> ms-auto"><?php echo e($badgeText); ?></span>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\platform\_health_badge.blade.php ENDPATH**/ ?>