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
        $wallet = $user->wallet ?? $user->wallet()->create();

        // Get transactions
        $transactions = $wallet->transactions()->latest()->get();

        return view('backend.wallet.wallet-show', compact('user', 'wallet', 'transactions'));
    }

}
