<h1>Cape Tennis website error</h1>
<p>An unexpected server error was reported. The exception log below includes the message, complete stack trace and underlying exceptions, with sensitive details redacted.</p>
<dl>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['reference', 'occurred_at', 'environment', 'exception', 'file', 'line', 'method', 'route', 'user_id', 'fingerprint']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <dt><?php echo e(ucfirst(str_replace('_', ' ', $field))); ?></dt>
    <dd><?php echo e($diagnostic[$field] ?? 'Guest / unavailable'); ?></dd>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</dl>
<h2>Source trace</h2>
<pre><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $diagnostic['trace']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $frame): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($frame['file']); ?>:<?php echo e($frame['line']); ?>

<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></pre>
<h2>Exception log</h2>
<pre style="white-space: pre-wrap; overflow-wrap: anywhere"><?php echo e($diagnostic['exception_log'] ?? 'Unavailable for an alert queued before this update.'); ?></pre>
<p>SQL details, request values, credentials, URLs and trace arguments are redacted or omitted. This log covers the reported exception, not unrelated application log entries. Repeated errors from the same source are grouped for five minutes; alerts are limited to ten distinct errors per minute.</p>
<?php /**PATH D:\wamp64\www\ct\resources\views\emails\website-error-alert.blade.php ENDPATH**/ ?>