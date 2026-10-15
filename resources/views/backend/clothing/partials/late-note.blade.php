@php $currentDeadline = $refundDeadline ?? app(\App\Services\Clothing\ClothingRefundService::class)->deadline($event, $order); @endphp
@if($currentDeadline && now()->gte($currentDeadline) && auth()->user()->hasRole('super-user'))
<div class="alert alert-warning">Late refund — clothing payout requires reconciliation. If the region or supplier has already been paid, its payout may need an adjustment. This does not establish a debt owed by the payer.</div>
<label class="form-label" for="{{ $noteKey }}">Late refund / payout reconciliation note</label><textarea required maxlength="1000" class="form-control mb-2" id="{{ $noteKey }}" name="late_refund_note">{{ old('late_refund_note') }}</textarea>
@endif
