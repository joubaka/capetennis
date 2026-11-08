
<?php ($capezLogo = \App\Models\SiteSetting::brandLogoPath()); ?>
<?php ($capezLogoIsRemote = filter_var($capezLogo, FILTER_VALIDATE_URL)); ?>
<?php ($capezLogoPath = ltrim($capezLogo, '/')); ?>
<?php ($capezLogoFile = $capezLogoIsRemote ? null : public_path($capezLogoPath)); ?>
<?php ($capezLogoVersion = $capezLogoFile && is_file($capezLogoFile) ? filemtime($capezLogoFile) : null); ?>
<?php ($capezLogoUrl = $capezLogoIsRemote ? $capezLogo : asset($capezLogoPath)); ?>
<img src="<?php echo e($capezLogoUrl); ?><?php echo e($capezLogoVersion ? '?v='.$capezLogoVersion : ''); ?>" height="40" width="40" alt="Cape Tennis logo">
<?php /**PATH C:\wamp64\www\ct\resources\views/_partials/macros.blade.php ENDPATH**/ ?>