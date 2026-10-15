@php
  $used = $item->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true));
  $usedCents = $used->sum(fn ($entry) => \App\Services\Clothing\ClothingRefundService::cents($entry->amount));
  $paidCents = \App\Services\Clothing\ClothingRefundService::cents($item->line_total);
  $availableCents = max(0, $paidCents - $usedCents);
  $selected = (int) old('items.'.$item->id, 0);
@endphp
<tr data-refund-row>
  <td data-label="Select"><input type="checkbox" class="form-check-input" aria-label="Select {{ $item->item_name }} {{ $item->size_name }}" data-select-refund @checked($selected > 0) @disabled($availableCents <= 0 || $item->remaining_quantity < 1)></td>
  <td data-label="Item / size">{{ $item->item_name ?: 'Clothing item' }}<small class="d-block">{{ $item->size_name ?: 'Size not recorded' }}</small></td>
  <td data-label="Purchased / paid">{{ $item->qty }} · R{{ number_format($paidCents / 100, 2) }}</td>
  <td data-label="Prior refunds / pending">{{ $item->refunded_quantity }} units · R{{ number_format($usedCents / 100, 2) }}</td>
  <td data-label="Available">{{ $item->remaining_quantity }} units · R{{ number_format($availableCents / 100, 2) }}</td>
  <td data-label="Quantity to refund"><label class="visually-hidden" for="refund-qty-{{ $item->id }}">Quantity to refund for {{ $item->item_name }} {{ $item->size_name }}</label>
    <input id="refund-qty-{{ $item->id }}" class="form-control" style="min-width: 5rem" name="items[{{ $item->id }}]" type="number" min="1" max="{{ $item->remaining_quantity }}" value="{{ $selected > 0 ? $selected : $item->remaining_quantity }}" data-refund-quantity @disabled($selected < 1)>
  </td>
</tr>
