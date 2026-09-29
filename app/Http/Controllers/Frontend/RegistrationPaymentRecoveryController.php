<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Http\Controllers\Controller;
use App\Models\RegistrationPaymentRecovery;
use App\Services\Payfast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class RegistrationPaymentRecoveryController extends Controller
{
    public function show(Request $request, RegistrationPaymentRecovery $recovery)
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless((int) $recovery->user_id === (int) $request->user()?->id, 403);
        $recoveryService = app(RegistrationPaymentRecoveryService::class);
        $recoveryService->assertRecoveryLinkActive($recovery);

        $existingOrder = $recovery->order;
        abort_unless($existingOrder, 404);
        if ((int) $existingOrder->pay_status === 1 || $existingOrder->payfast_paid) {
            $recovery->newQuery()->whereKey($recovery->id)->whereNull('paid_at')->update(['paid_at' => now(), 'status' => 'paid']);
            return redirect()->route('frontend.registration.success', $existingOrder);
        }
        abort_unless(in_array($recovery->status, ['prepared', 'notified'], true), 409);

        $order = DB::transaction(fn () => $recoveryService->validatePrepared($recovery));
        abort_unless($order && (int) $order->user_id === (int) $recovery->user_id, 404);

        abort_unless(($order->status ?? null) === 'pending', 409);
        abort_unless(round((float) $recovery->amount_due, 2) === round((float) $order->items->sum('item_price'), 2), 409);

        app(PaymentOrchestrator::class)->initiatePayment($order, 0, (float) $recovery->amount_due);
        $payfast = new Payfast();
        $payfast->setMode(config('services.payfast.sandbox') ? 0 : 1);

        return view('frontend.registration-payment-recovery.checkout', [
            'recovery' => $recovery,
            'order' => $order->fresh('items.category_event.event'),
            'payfast' => $payfast,
            'paymentUrl' => URL::temporarySignedRoute('registration.recovery.payfast', $recoveryService->nestedSignedLinkExpiry($recovery), ['recovery' => $recovery->id]),
        ]);
    }

    public function payfast(Request $request, RegistrationPaymentRecovery $recovery)
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless((int) $recovery->user_id === (int) $request->user()?->id, 403);
        $recoveryService = app(RegistrationPaymentRecoveryService::class);
        $recoveryService->assertRecoveryLinkActive($recovery);
        abort_unless(in_array($recovery->status, ['prepared', 'notified'], true), 409);
        $order = DB::transaction(fn () => $recoveryService->validatePrepared($recovery));
        abort_if((int) $order->pay_status === 1 || $order->payfast_paid, 409);
        $payfast = new Payfast();
        $payfast->setMode(config('services.payfast.sandbox') ? 0 : 1);
        return view('frontend.payfast.pay_now', [
            'payfast' => $payfast, 'amount' => (float) $recovery->amount_due, 'orderId' => $order->id,
            'return_url' => route('frontend.registration.success', $order),
            'cancel_url' => URL::temporarySignedRoute('registration.recovery.show', $recoveryService->nestedSignedLinkExpiry($recovery), ['recovery' => $recovery->id]),
            'notify_url' => route('notify'),
        ]);
    }
}
