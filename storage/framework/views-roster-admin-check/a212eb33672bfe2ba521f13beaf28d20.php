<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($campaign->subject); ?></title>
</head>
<body style="margin:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#263238;">
  <div style="max-width:680px;margin:0 auto;padding:24px 12px;">
    <div style="background:#fff;border-radius:10px;padding:28px;border:1px solid #e2e6ea;">
      <h2 style="margin:0 0 8px;color:#17365d;"><?php echo e($campaign->series->name); ?></h2>
      <p style="margin:0 0 24px;color:#667085;">Provisional ranking review</p>

      <p>Dear <?php echo e(count($playerNames) === 1 ? $playerNames[0] : 'Player / Parent'); ?>,</p>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = preg_split('/\R{2,}/u', trim((string) $campaign->message)) ?: []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paragraph): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <p class="ranking-review-message-paragraph" style="margin:0 0 18px;line-height:1.6;"><?php echo nl2br(e($paragraph), false); ?></p>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($playerNames) > 1): ?>
        <p style="margin-top:20px;"><strong>This email covers:</strong> <?php echo e(implode(', ', $playerNames)); ?></p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <p style="margin:28px 0;text-align:center;">
        <a href="<?php echo e($reviewUrl); ?>" style="display:inline-block;background:#198754;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold;">
          View provisional rankings
        </a>
      </p>

      <p style="font-size:14px;color:#667085;">
        Reply cutoff: <strong><?php echo e($campaign->cutoff_at->timezone(config('app.timezone'))->format('d M Y H:i T')); ?></strong>.
        Reply directly to this email if anything needs attention. Replies will go to
        <strong><?php echo e($campaign->reply_to); ?></strong>.
      </p>
    </div>
  </div>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\ranking-review.blade.php ENDPATH**/ ?>