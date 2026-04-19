<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

class WalletPolicy
{
    /**
     * Determine if the user can view any wallet (admin dashboard).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Determine if the user can view a specific wallet.
     */
    public function view(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id
            || $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Determine if the user can credit a wallet.
     */
    public function credit(User $user, Wallet $wallet): bool
    {
        return $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Determine if the user can debit a wallet.
     */
    public function debit(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id
            || $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Determine if the user can view wallet transactions.
     */
    public function viewTransactions(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id
            || $user->hasAnyRole(['super-user', 'admin']);
    }
}
