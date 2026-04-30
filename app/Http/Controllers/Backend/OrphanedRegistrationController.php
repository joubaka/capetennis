<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class OrphanedRegistrationController extends Controller
{
    /**
     * Find all paid orders where one or more items have no matching
     * category_event_registrations row.
     */
    public function index()
    {
        [$orphans, $sandboxOrphans] = $this->findOrphans();
        return view('backend.superadmin.orphaned-registrations', compact('orphans', 'sandboxOrphans'));
    }

    /**
     * Replay a single order's ITN logic to create the missing CER rows.
     */
    public function repair(Request $request, RegistrationOrder $order)
    {
        $order->load(['items.category_event.event', 'user']);

        $pfPaymentId = $order->payfast_pf_payment_id;
        $repaired    = 0;
        $errors      = [];

        DB::transaction(function () use ($order, $pfPaymentId, &$repaired, &$errors) {
            foreach ($order->items as $item) {
                $registration = Registration::find($item->registration_id);

                if (!$registration) {
                    $errors[] = "Registration #{$item->registration_id} not found.";
                    continue;
                }

                // Sync player
                try {
                    $registration->players()->syncWithoutDetaching([$item->player_id]);
                } catch (\Throwable $e) {
                    $errors[] = "Player sync failed for reg #{$item->registration_id}: {$e->getMessage()}";
                }

                // Create or update CER
                try {
                    $existing = CategoryEventRegistration::where('registration_id', $item->registration_id)
                        ->where('category_event_id', $item->category_event_id)
                        ->first();

                    if ($existing) {
                        if (empty($existing->pf_transaction_id)) {
                            $existing->update([
                                'pf_transaction_id' => $pfPaymentId,
                                'payment_status_id' => 1,
                                'user_id'           => $order->user_id,
                            ]);
                            $repaired++;
                        }
                        // Already complete — skip
                    } else {
                        $registration->categoryEvents()->syncWithoutDetaching([
                            $item->category_event_id => [
                                'payment_status_id' => 1,
                                'user_id'           => $order->user_id,
                                'pf_transaction_id' => $pfPaymentId,
                            ],
                        ]);
                        $repaired++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "CER sync failed for reg #{$item->registration_id}, ce #{$item->category_event_id}: {$e->getMessage()}";
                    Log::error('[ORPHAN REPAIR] CER sync failed', [
                        'order_id'         => $order->id,
                        'registration_id'  => $item->registration_id,
                        'category_event_id'=> $item->category_event_id,
                        'error'            => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('[ORPHAN REPAIR] Repaired order', [
            'order_id' => $order->id,
            'repaired' => $repaired,
            'errors'   => $errors,
        ]);

        if (!empty($errors)) {
            return back()->withErrors($errors)->with('warning', "Partially repaired {$repaired} registration(s). See errors above.");
        }

        return back()->with('success', "Successfully repaired {$repaired} registration(s) for order #{$order->id}.");
    }

    /**
     * Purge all traces of a sandbox/test order — safe to run only when
     * the order is identified as a test/sandbox order.
     */
    public function purge(Request $request, RegistrationOrder $order)
    {
        $order->load('items');

        if (!$this->isSandboxOrder($order)) {
            return back()->with('error', "Order #{$order->id} does not appear to be a sandbox/test order. Purge refused.");
        }

        $registrationIds = $order->items->pluck('registration_id')->unique()->filter()->values();

        DB::transaction(function () use ($order, $registrationIds) {
            // 1. Remove CER rows
            CategoryEventRegistration::whereIn('registration_id', $registrationIds)->delete();

            // 2. Remove player_registrations pivot
            DB::table('player_registrations')->whereIn('registration_id', $registrationIds)->delete();

            // 3. Remove registrations
            DB::table('registrations')->whereIn('id', $registrationIds)->delete();

            // 4. Remove order items
            DB::table('registration_order_items')->where('order_id', $order->id)->delete();

            // 5. Remove the sandbox transaction record (if any)
            if ($order->payfast_pf_payment_id) {
                DB::table('transactions_pf')->where('pf_payment_id', $order->payfast_pf_payment_id)->delete();
            }

            // 6. Remove the order itself
            DB::table('registration_orders')->where('id', $order->id)->delete();

            Log::info('[SANDBOX PURGE] Purged sandbox order', [
                'order_id'         => $order->id,
                'pf_payment_id'    => $order->payfast_pf_payment_id,
                'registration_ids' => $registrationIds,
            ]);
        });

        return back()->with('success', "Sandbox order #{$order->id} and all related test data purged successfully.");
    }

    /**
     * Permanently delete all traces of a real orphaned order.
     * The transactions_pf record is preserved as a financial audit trail.
     * Only allowed when NO category_event_registrations exist for the order items
     * (i.e. it is a confirmed orphan — nothing was ever registered).
     */
    public function deleteReal(Request $request, RegistrationOrder $order)
    {
        $order->load('items');

        // Safety: refuse sandbox orders — use purge instead
        if ($this->isSandboxOrder($order)) {
            return back()->with('error', "Order #{$order->id} is a sandbox order. Use the sandbox purge action instead.");
        }

        // Safety: refuse if any CER rows exist — this order is not a true orphan
        $registrationIds = $order->items->pluck('registration_id')->unique()->filter()->values();
        $cerCount = CategoryEventRegistration::whereIn('registration_id', $registrationIds)->count();
        if ($cerCount > 0) {
            return back()->with('error', "Order #{$order->id} has {$cerCount} existing registration record(s). It is not a true orphan and cannot be deleted this way.");
        }

        DB::transaction(function () use ($order, $registrationIds) {
            // Remove player_registrations pivot
            DB::table('player_registrations')->whereIn('registration_id', $registrationIds)->delete();

            // Remove registrations
            DB::table('registrations')->whereIn('id', $registrationIds)->delete();

            // Remove order items
            DB::table('registration_order_items')->where('order_id', $order->id)->delete();

            // Remove the order itself
            DB::table('registration_orders')->where('id', $order->id)->delete();

            // transactions_pf is intentionally kept as financial audit trail

            Log::info('[ORPHAN DELETE] Deleted real orphaned order', [
                'order_id'         => $order->id,
                'pf_payment_id'    => $order->payfast_pf_payment_id,
                'registration_ids' => $registrationIds,
            ]);
        });

        return back()->with('success', "Order #{$order->id} and all orphaned registration data deleted. The PayFast transaction record has been kept for financial audit purposes.");
    }

    /**
     * Determine if an order is a sandbox/test order.
     * Conditions (any one sufficient):
     *  - linked transactions_pf row has is_test = 1
     *  - payfast_pf_payment_id is NULL (no real PayFast reference)
     *  - payfast_pf_payment_id is shorter than 9 digits (sandbox IDs are 7 digits)
     */
    private function isSandboxOrder(RegistrationOrder $order): bool
    {
        $pfId = $order->payfast_pf_payment_id;

        // No PF reference at all
        if (is_null($pfId) || $pfId === '') {
            return true;
        }

        // Short PF ID — real PayFast IDs are always 9 digits
        if (strlen((string) $pfId) < 9) {
            return true;
        }

        // Explicitly flagged as test in transactions_pf
        return Transaction::where('pf_payment_id', $pfId)
            ->where('is_test', true)
            ->exists();
    }

    /**
     * Returns two collections: [realOrphans, sandboxOrphans]
     * Sandbox = linked transactions_pf row has is_test = 1.
     */
    private function findOrphans(): array
    {
        $paidOrders = RegistrationOrder::with(['items', 'user'])
            ->where(function ($q) {
                $q->where('payfast_paid', true)->orWhere('pay_status', true);
            })
            ->get();

        $allOrphans = $paidOrders->filter(function ($order) {
            foreach ($order->items as $item) {
                $exists = CategoryEventRegistration::where('registration_id', $item->registration_id)
                    ->where('category_event_id', $item->category_event_id)
                    ->exists();
                if (!$exists) {
                    return true;
                }
            }
            return false;
        })->map(function ($order) {
            $missingItems = $order->items->filter(function ($item) {
                return !CategoryEventRegistration::where('registration_id', $item->registration_id)
                    ->where('category_event_id', $item->category_event_id)
                    ->exists();
            })->load(['player', 'category_event.event', 'category_event.category']);

            $isSandbox = $this->isSandboxOrder($order);

            return (object) [
                'order'         => $order,
                'missing_items' => $missingItems,
                'pf_payment_id' => $order->payfast_pf_payment_id,
                'total'         => $order->items->sum('item_price'),
                'is_sandbox'    => $isSandbox,
            ];
        })->values();

        return [
            $allOrphans->where('is_sandbox', false)->values(),
            $allOrphans->where('is_sandbox', true)->values(),
        ];
    }
}
