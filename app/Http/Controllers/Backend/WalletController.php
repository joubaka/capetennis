<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * List all user wallets.
     */
    public function index()
    {
        $wallets = Wallet::with('payable')->get();

        return view('backend.wallet.wallet-index', compact('wallets'));
    }

    /**
     * Show a specific user's wallet and its transactions.
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        // Get wallet or create if missing
        $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);

        // Get transactions
        $transactions = $wallet->transactions()->latest()->get();

        return view('backend.wallet.wallet-show', compact('user', 'wallet', 'transactions'));
    }

    public static function addToWallet($amount, $userId, $reference = null)
{
    $user = \App\Models\User::findOrFail($userId);

    $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);

    // Update balance
    $wallet->increment('balance', $amount);

    // Log transaction
    $wallet->transactions()->create([
        'type' => 'credit',
        'amount' => $amount,
        'reference' => $reference ?? 'Auto top-up',
        'meta' => ['source' => 'auto'],
    ]);

    return true;
}
public static function deductFromWallet($amount, $userId, $reference = null)
{
    $user = \App\Models\User::findOrFail($userId);

    $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0]);

    if ($wallet->balance < $amount) {
        throw new \Exception('Insufficient wallet balance.');
    }

    // Deduct balance
    $wallet->decrement('balance', $amount);

    // Log transaction
    $wallet->transactions()->create([
        'type' => 'debit',
        'amount' => $amount,
        'reference' => $reference ?? 'Wallet deduction',
        'meta' => ['source' => 'manual'],
    ]);

    return true;
}

}
