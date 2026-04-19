<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Only super-users can manually credit/debit wallets.
     */
    public function store(Request $request, $id)
    {
        // Only super-user can manually credit/debit wallets
        if (!auth()->user()->can('super-user')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($id);
        $wallet = $user->wallet ?? $user->wallet()->create();
        $amount = $request->amount;

        // Quick pre-check (not under lock – the authoritative check is inside the transaction)
        if ($request->type === 'debit' && $wallet->balance < $amount) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Insufficient balance for debit.'], 422);
            }
            return back()->withErrors(['amount' => 'Insufficient balance for debit.']);
        }

        $insufficientFunds = false;

        DB::transaction(function () use ($wallet, $amount, $request, $user, &$insufficientFunds) {

          if ($request->type === 'debit') {
            // Re-check balance under a row lock to prevent concurrent over-debits
            $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($locked->balance < $amount) {
              $insufficientFunds = true;
              return;
            }
          }

          // Create transaction (balance is computed from transactions)
          $wallet->transactions()->create([
              'type' => $request->type,
              'amount' => $amount,
              'source_type' => 'manual',
              'source_id' => auth()->id(),
              'meta' => [
                  'admin' => auth()->user()->name,
                  'reference' => $request->reference,
              ],
          ]);

          activity('wallet')
            ->performedOn($wallet)
            ->causedBy(auth()->user())
            ->withProperties([
              'type' => $request->type,
              'amount' => $amount,
              'reference' => $request->reference,
              'user_id' => $user->id,
            ])
            ->log("Manual wallet {$request->type} R{$amount} for {$user->name}");
        });

        if ($insufficientFunds) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Insufficient balance for debit.'], 422);
            }
            return back()->withErrors(['amount' => 'Insufficient balance for debit.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Transaction recorded.',
                'balance' => $wallet->balance,
            ]);
        }

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
