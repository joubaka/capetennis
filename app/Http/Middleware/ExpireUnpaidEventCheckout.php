<?php

namespace App\Http\Middleware;

use App\Domain\Payments\Services\UnpaidCheckoutExpiryService;
use App\Models\{RegistrationOrder, TeamPaymentOrder, TrialParticipation};
use Closure;
use Illuminate\Http\Request;

class ExpireUnpaidEventCheckout
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->routeIs(
            'registration.checkout', 'registration.hybrid.pay', 'registration.payfast-only',
            'registration.hybrid.apply-wallet', 'registration.hybrid.complete',
            'team.checkout', 'team.hybrid.pay', 'team.hybrid.complete',
            'interprovincial-trials.participation.show', 'interprovincial-trials.participation.payfast',
            'interprovincial-trials.participation.proof'
        )) return $next($request);

        $participation = $request->route('participation');
        if ($participation instanceof TrialParticipation) {
            $event = $request->route('event');
            if ((int) $participation->event_id !== (int) ($event->id ?? $event)
                || (int) $participation->payer_id !== (int) $request->user()->id) return $next($request);
            $order = $participation->order;
        } else {
            $parameter = $request->route('order') ?? $request->route('orderId') ?? $request->input('custom_int5') ?? $request->input('order_id');
            $team = $request->routeIs('team.*') || $request->input('type') === 'team';
            $model = $team ? TeamPaymentOrder::class : RegistrationOrder::class;
            $order = $parameter instanceof $model ? $parameter : $model::find(is_scalar($parameter) ? $parameter : null);
        }
        if (!$order || (int) $order->user_id !== (int) $request->user()->id) return $next($request);
        if (app(UnpaidCheckoutExpiryService::class)->expire($order)) {
            if ($participation instanceof TrialParticipation) {
                return redirect()->route('events.show', $participation->event_id)
                    ->with('info', 'Your unpaid checkout expired after five minutes. Start again from the event; no wallet funds were deducted.');
            }
            foreach ($request->route()->parameters() as $parameter) {
                if ($parameter instanceof \Illuminate\Database\Eloquent\Model) $parameter->refresh();
            }
            if ($order instanceof TeamPaymentOrder) {
                return redirect()->route('events.show', $order->event_id)
                    ->with('info', 'Your unpaid checkout expired after five minutes. Your roster place is retained; start checkout again.');
            }
        }
        return $next($request);
    }
}
