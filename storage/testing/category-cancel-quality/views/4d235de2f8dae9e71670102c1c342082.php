<?php
    $statementLogoPath = public_path('assets/img/pwa/cape-tennis-app-192.png');
    $statementLogo = is_file($statementLogoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($statementLogoPath)) : null;
?>
<table class="statement-brand"><tr>
    <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statementLogo): ?><img src="<?php echo e($statementLogo); ?>" class="statement-brand-logo" alt="Cape Tennis"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><span class="statement-brand-name">Cape Tennis</span></td>
    <td class="statement-title"><span class="statement-kicker">Account records</span><br>Account statement</td>
</tr></table>
<table class="statement-meta"><tr>
    <td><span class="statement-kicker">Account holder</span><strong><?php echo e($account->name); ?></strong><?php echo e($account->email); ?><br>Account #<?php echo e($account->id); ?></td>
    <td><dl>
        <dt>Statement date:</dt> <dd><?php echo e($generated_at->format('d M Y')); ?></dd><br>
        <dt>Recorded period:</dt> <dd><?php echo e(($allTime ?? false) ? 'All time' : $from.' to '.$to); ?></dd><br>
        <dt>Payments included:</dt> <dd><?php echo e(($category ?? 'all') === 'all' ? 'Entry fees and clothing' : ucfirst($category)); ?></dd>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?><br><dt>Player search:</dt> <dd><?php echo e($player); ?></dd><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </dl></td>
</tr></table>
<table class="statement-summary"><tr>
    <td><span class="statement-summary-label">Known payments</span><span class="statement-value">R <?php echo e(number_format($paid / 100, 2)); ?></span></td>
    <td><span class="statement-summary-label">Completed refunds</span><span class="statement-value">R <?php echo e(number_format($refunded / 100, 2)); ?></span></td>
    <td class="statement-net"><span class="statement-summary-label">Net known payments</span><span class="statement-value">R <?php echo e(number_format($net / 100, 2)); ?></span></td>
</tr></table>
<p class="statement-caption">ZAR · Totals include all matching receipt pages. Historical fee information appears separately below.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?><div class="statement-notice">These are full matching checkout amounts, including other players in a shared checkout. They are not player-only expenses. Historical fee subtotals use the matching players only.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\account\statement-heading.blade.php ENDPATH**/ ?>