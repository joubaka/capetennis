<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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
        ]);

        $wallet = $user->wallet ?? $user->wallet()->create();
        $amount = (float) $request->amount;

        if ($request->type === 'debit' && $wallet->balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient wallet balance for debit.']);
        }

        $wallet->transactions()->create([
            'type'        => $request->type,
            'amount'      => $amount,
            'source_type' => 'manual',
            'source_id'   => auth()->id(),
            'meta'        => [
                'admin'     => auth()->user()->name,
                'reference' => $request->reference,
            ],
        ]);

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

        return back()->with('wallet_success', "Transaction recorded for {$user->name}.");
    }

    /**
     * Update an existing wallet transaction (type, amount, reference).
     */
    public function updateTransaction(Request $request, WalletTransaction $transaction)
    {
        $request->validate([
            'type'      => 'required|in:credit,debit',
            'amount'    => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
        ]);

        $wallet    = $transaction->wallet;
        $oldType   = $transaction->type;
        $oldAmount = $transaction->amount;

        // Check new balance would not go negative after edit
        if ($request->type === 'debit') {
            // Reverse the current transaction's effect on the balance, then apply the new values.
            // oldEffect: +amount for credit (increased balance), -amount for debit (decreased balance).
            // newEffect: always negative since we're validating a debit change.
            $oldEffect = $oldType === 'credit' ? $oldAmount : -$oldAmount;
            $newEffect = -(float) $request->amount;
            $newBalance = $wallet->balance - $oldEffect + $newEffect;
            if ($newBalance < 0) {
                return back()->withErrors(['amount' => 'Editing this transaction would result in a negative wallet balance.']);
            }
        }

        $meta = $transaction->meta ?? [];
        $meta['reference'] = $request->reference;
        $meta['admin']     = auth()->user()->name;

        $transaction->update([
            'type'   => $request->type,
            'amount' => (float) $request->amount,
            'meta'   => $meta,
        ]);

        activity('wallet')
            ->performedOn($wallet)
            ->causedBy(auth()->user())
            ->withProperties([
                'transaction_id' => $transaction->id,
                'old_type'       => $oldType,
                'old_amount'     => $oldAmount,
                'new_type'       => $request->type,
                'new_amount'     => $request->amount,
                'reference'      => $request->reference,
            ])
            ->log("Edited wallet transaction #{$transaction->id} for wallet #{$wallet->id}");

        return back()->with('wallet_success', 'Transaction updated successfully.');
    }

    /**
     * Delete a single wallet transaction.
     */
    public function destroyTransaction(WalletTransaction $transaction)
    {
        $wallet = $transaction->wallet;

        // Prevent deletion if it would push balance negative (debit transaction removal increases balance; credit removal decreases)
        if ($transaction->type === 'credit') {
            $newBalance = $wallet->balance - $transaction->amount;
            if ($newBalance < 0) {
                return back()->withErrors(['wallet' => 'Deleting this credit transaction would result in a negative wallet balance.']);
            }
        }

        activity('wallet')
            ->performedOn($wallet)
            ->causedBy(auth()->user())
            ->withProperties([
                'transaction_id' => $transaction->id,
                'type'           => $transaction->type,
                'amount'         => $transaction->amount,
            ])
            ->log("Deleted wallet transaction #{$transaction->id} from wallet #{$wallet->id}");

        $transaction->delete();

        return back()->with('wallet_success', 'Transaction deleted.');
    }

    /**
     * Delete an entire wallet and all its transactions.
     */
    public function destroyWallet(Wallet $wallet)
    {
        $payable = $wallet->payable;
        $name    = $payable?->name ?? "Wallet #{$wallet->id}";

        activity('wallet')
            ->performedOn($wallet)
            ->causedBy(auth()->user())
            ->withProperties(['wallet_id' => $wallet->id, 'user' => $name])
            ->log("Deleted entire wallet #{$wallet->id} for {$name}");

        $wallet->transactions()->delete();
        $wallet->delete();

        return back()->with('wallet_success', "Wallet for {$name} deleted.");
    }
}
