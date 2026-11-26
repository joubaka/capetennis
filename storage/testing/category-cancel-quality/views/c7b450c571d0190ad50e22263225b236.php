<?php
  $used = $item->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true));
  $usedCents = $used->sum(fn ($entry) => \App\Services\Clothing\ClothingRefundService::cents($entry->amount));
  $paidCents = \App\Services\Clothing\ClothingRefundService::cents($item->line_total);
  $availableCents = max(0, $paidCents - $usedCents);
  $selected = (int) old('items.'.$item->id, 0);
?>
<tr data-refund-row>
  <td data-label="Select"><input type="checkbox" class="form-check-input" aria-label="Select <?php echo e($item->item_name); ?> <?php echo e($item->size_name); ?>" data-select-refund <?php if($selected > 0): echo 'checked'; endif; ?> <?php if($availableCents <= 0 || $item->remaining_quantity < 1): echo 'disabled'; endif; ?>></td>
  <td data-label="Item / size"><?php echo e($item->item_name ?: 'Clothing item'); ?><small class="d-block"><?php echo e($item->size_name ?: 'Size not recorded'); ?></small></td>
  <td data-label="Purchased / paid"><?php echo e($item->qty); ?> · R<?php echo e(number_format($paidCents / 100, 2)); ?></td>
  <td data-label="Prior refunds / pending"><?php echo e($item->refunded_quantity); ?> units · R<?php echo e(number_format($usedCents / 100, 2)); ?></td>
  <td data-label="Available"><?php echo e($item->remaining_quantity); ?> units · R<?php echo e(number_format($availableCents / 100, 2)); ?></td>
  <td data-label="Quantity to refund"><label class="visually-hidden" for="refund-qty-<?php echo e($item->id); ?>">Quantity to refund for <?php echo e($item->item_name); ?> <?php echo e($item->size_name); ?></label>
    <input id="refund-qty-<?php echo e($item->id); ?>" class="form-control" style="min-width: 5rem" name="items[<?php echo e($item->id); ?>]" type="number" min="1" max="<?php echo e($item->remaining_quantity); ?>" value="<?php echo e($selected > 0 ? $selected : $item->remaining_quantity); ?>" data-refund-quantity <?php if($selected < 1): echo 'disabled'; endif; ?>>
  </td>
</tr>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\partials\refund-item.blade.php ENDPATH**/ ?>