<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletTransactionController extends Controller
{
    /**
     * Show form to create a wallet transaction (credit/debit).
     */
    public function create($id)
    {
        $user = User::findOrFail($id);
        $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);

        return view('backend.wallet.transaction-create', compact('user', 'wallet'));
    }

    /**
     * Store a new wallet transaction.
     */
    public function store(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);
        $amount = $request->amount;

        if ($request->type === 'debit' && $wallet->balance < $amount) {
            return back()->withErrors(['amount' => 'Insufficient balance for debit.']);
        }

        // Apply the transaction
        $wallet->{$request->type === 'credit' ? 'increment' : 'decrement'}('balance', $amount);

        $wallet->transactions()->create([
            'type' => $request->type,
            'amount' => $amount,
            'reference' => $request->reference,
            'meta' => ['admin' => auth()->user()->name],
        ]);

        return redirect()->route('wallet.show', $user->id)->with('success', 'Transaction recorded.');
    }

    /**
     * (Optional) Approve a withdrawal request.
     */
    public function approve($id)
    {
        $tx = WalletTransaction::findOrFail($id);
        // Mark as approved or update status if needed
        // Optionally notify user
    }

    /**
     * (Optional) Reject a withdrawal request.
     */
    public function reject($id)
    {
        $tx = WalletTransaction::findOrFail($id);
        // Update status to rejected, refund balance if needed
        // Optionally notify user
    }
}
