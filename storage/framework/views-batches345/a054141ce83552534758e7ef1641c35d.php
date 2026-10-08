<?php
    $mailHistoryUrl = request()->routeIs('backend.superadmin.workspace') ? route('backend.superadmin.workspace') : route('backend.superadmin.mail-history');
    $reportContext = request()->routeIs('backend.superadmin.workspace') ? ['tab'=>'mails'] : [];
?>
<?php echo $__env->make('backend.partials.mail-report',['report'=>$mailReport,'reportUrl'=>$mailHistoryUrl,'reportTitle'=>'All tracked email history','reportEvent'=>null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\partials\mail-history.blade.php ENDPATH**/ ?>