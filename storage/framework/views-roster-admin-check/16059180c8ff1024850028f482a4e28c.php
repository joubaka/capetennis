<?php
    use App\Models\RegistrationOrder;
    
    // Load order with relationships if orderId is provided
    $order = null;
    $firstItem = null;
    $categoryEvent = null;
    $event = null;
    $category = null;
    $player = null;
    
    if (isset($orderId) && $orderId) {
        $order = RegistrationOrder::with('items.category_event.event', 'items.category_event.category', 'items.player')->find($orderId);
        if ($order) {
            $firstItem = $order->items->first();
            $categoryEvent = $firstItem?->category_event;
            $event = $categoryEvent?->event;
            $category = $categoryEvent?->category;
            $player = $firstItem?->player;
        }
    }
?>

<form id="payfastForm" action="<?php echo e($payfast->url); ?>" method="post" data-audit-order-id="<?php echo e($orderId ?? ''); ?>">
    <input type="hidden" name="merchant_id" value="<?php echo e($payfast->id); ?>">
    <input type="hidden" name="merchant_key" value="<?php echo e($payfast->key); ?>">
    <input type="hidden" name="return_url" value="<?php echo e($return_url); ?>">
    <input type="hidden" name="cancel_url" value="<?php echo e($cancel_url); ?>">
    <input type="hidden" name="notify_url" value="<?php echo e($notify_url); ?>">
    <input type="hidden" name="amount" value="<?php echo e(number_format((float)$amount, 2, '.', '')); ?>">
    <input type="hidden" name="item_name" value="<?php echo e($event ? $event->name : 'Event Registration'); ?>">

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryEvent): ?>
        <input type="hidden" name="custom_int1" value="<?php echo e($categoryEvent->id); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
        <input type="hidden" name="custom_int2" value="<?php echo e($player->id); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?>
        <input type="hidden" name="custom_int3" value="<?php echo e($event->id); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check()): ?>
        <input type="hidden" name="custom_int4" value="<?php echo e(auth()->id()); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($orderId): ?>
        <input type="hidden" name="custom_int5" value="<?php echo e($orderId); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($category): ?>
        <input type="hidden" name="custom_str1" value="<?php echo e($category->name); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
        <input type="hidden" name="custom_str2" value="<?php echo e(trim($player->name . ' ' . $player->surname)); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event): ?>
        <input type="hidden" name="custom_str3" value="<?php echo e($event->name); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check()): ?>
        <input type="hidden" name="custom_str4" value="<?php echo e(trim(auth()->user()->name)); ?>">
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php
        $formFields = array_filter([
            'merchant_id'  => $payfast->id,
            'merchant_key' => $payfast->key,
            'return_url'   => $return_url,
            'cancel_url'   => $cancel_url,
            'notify_url'   => $notify_url,
            'amount'       => number_format((float)$amount, 2, '.', ''),
            'item_name'    => $event ? $event->name : 'Event Registration',
            'custom_int1'  => $categoryEvent ? (string)$categoryEvent->id : null,
            'custom_int2'  => $player ? (string)$player->id : null,
            'custom_int3'  => $event ? (string)$event->id : null,
            'custom_int4'  => auth()->check() ? (string)auth()->id() : null,
            'custom_int5'  => $orderId ? (string)$orderId : null,
            'custom_str1'  => $category ? $category->name : null,
            'custom_str2'  => $player ? trim($player->name . ' ' . $player->surname) : null,
            'custom_str3'  => $event ? $event->name : null,
            'custom_str4'  => auth()->check() ? trim(auth()->user()->name) : null,
        ], fn($v) => $v !== null && $v !== '');
    ?>
    <input type="hidden" name="signature" value="<?php echo e($payfast->generateFormSignature($formFields)); ?>">
</form>

<script>
    (function() {
        var form = document.getElementById('payfastForm');
        var fields = {};
        form.querySelectorAll('input[type=hidden]').forEach(function(el) {
            fields[el.name] = el.value;
        });
        // DEBUG MODE: hold for 10 seconds so you can read console, then submit
        var debugHold = <?php echo e(app()->isLocal() ? 'true' : 'false'); ?>;
        var hasCreds  = fields.merchant_id && fields.merchant_key;

        if (!hasCreds) {
            console.error('[PayFast] MISSING CREDENTIALS — form will NOT submit. Check config/services.payfast');
            document.body.insertAdjacentHTML('afterbegin',
                '<div style="background:red;color:#fff;padding:16px;font-size:16px;z-index:9999;position:fixed;top:0;left:0;right:0">'
                + '⚠️ PayFast merchant_id or merchant_key is EMPTY. Check server .env / config cache.'
                + '</div>'
            );
            return; // stop — do not submit broken form
        }

        if (navigator.sendBeacon && <?php echo e(\Illuminate\Support\Facades\Route::has('audit.interactions.store') ? 'true' : 'false'); ?>) {
            var auditPayload = new FormData();
            auditPayload.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            auditPayload.append('action', 'payment.payfast-submit');
            auditPayload.append('label', 'Pay with PayFast');
            auditPayload.append('element', 'form');
            auditPayload.append('page_path', window.location.pathname);
            auditPayload.append('order_id', fields.custom_int5 || '');
            navigator.sendBeacon(<?php echo json_encode(\Illuminate\Support\Facades\Route::has('audit.interactions.store') ? route('audit.interactions.store') : null, 15, 512) ?>, auditPayload);
        }
        form.submit();
    })();
</script>

<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\payfast\pay_now.blade.php ENDPATH**/ ?>