<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#f3f6fa;font-family:Arial,sans-serif;color:#102b4e">
<div style="max-width:580px;margin:24px auto;padding:24px;background:#fff;border-top:5px solid #f28b22">
    <p style="color:#2167af;font-weight:bold">Cape Tennis Player Plus</p>
    <h1 style="font-size:24px"><?php echo e($eventName); ?></h1>
    <p>Hello <?php echo e($playerName); ?> and linked contacts,</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($drawPublished): ?><p>The draw for <?php echo e($playerName); ?> has been published: <?php echo e($drawName); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scheduleChanged): ?><p>The schedule for <?php echo e($drawName); ?> <?php echo e($scheduleInitial ? 'has been published' : 'has been updated'); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p style="margin:28px 0"><a href="<?php echo e($profileUrl); ?>" style="display:inline-block;padding:14px 18px;background:#2167af;color:#fff;text-decoration:none;border-radius:5px">Open <?php echo e($playerName); ?> in My Tennis</a></p>
    <p>Sign in with an account already linked to this player to open their My Tennis page.</p>
    <p><a href="<?php echo e($publicUrl); ?>" style="color:#2167af">View the published draw and schedule</a></p>
    <p style="font-size:13px;color:#52627a">Please check the latest published page for changes.</p>
</div>
</body></html>
<?php /**PATH D:\wamp64\www\ct\resources\views\emails\plus-publication.blade.php ENDPATH**/ ?>