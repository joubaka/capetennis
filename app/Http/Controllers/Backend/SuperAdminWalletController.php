<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Wallet\ManualWalletAdjustmentService;
use Illuminate\Http\Request;

class SuperAdminWalletController extends Controller
{
    /**
     * Create or ensure a wallet exists for a user, then add a transaction.
     */
    public function storeTransaction(Request $request, User $user)
    {
        $request->validate([
            'type'      => 'required|in:credit,debit',
            'amount'    => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
            'idempotency_key' => 'required|uuid',
        ]);

        $wallet = $user->wallet ?? $user->wallet()->create();
        $amount = (float) $request->amount;

        if ($request->type === 'debit' && $wallet->balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance for debit.']);
        }

        $meta = [
            'admin' => auth()->user()->name,
            'reference' => $request->reference,
            'initiated_by' => 'super_admin_wallet_controller',
        ];

        $transaction = app(ManualWalletAdjustmentService::class)->apply(
            $wallet,
            $request->type,
            $amount,
            $request->string('idempotency_key')->toString(),
            $meta
        );

        if ($transaction->wasRecentlyCreated) {
            activity('wallet')
            ->performedOn($wallet)
            ->causedBy(auth()->user())
            ->withProperties([
                'type'      => $request->type,
                'amount'    => $amount,
                'reference' => $request->reference,
                'user_id'   => $user->id,
            ])
                ->log("Manual wallet {$request->type} R{$amount} for {$user->name}");
        }

        return back()->with('wallet_success', "Transaction recorded for {$user->name}.");
    }

    /**
     * Update an existing wallet transaction (type, amount, reference).
     */
    public function updateTransaction(Request $request, WalletTransaction $transaction)
    {
        return back()->withErrors('Wallet transactions cannot be edited. To correct this entry, add a new transaction with the opposite amount.');
    }

    /**
     * Delete a single wallet transaction.
     */
    public function destroyTransaction(WalletTransaction $transaction)
    {
        return back()->withErrors('Wallet transactions cannot be deleted. To reverse this entry, add a new transaction with the opposite amount.');
    }

    /**
     * Delete an entire wallet and all its transactions.
     */
    public function destroyWallet(Wallet $wallet)
    {
        return back()->withErrors('Wallet deletion is locked in production-safe mode.');
    }
}
