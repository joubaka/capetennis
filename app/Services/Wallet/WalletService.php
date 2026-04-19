<?php

namespace App\Services\Wallet;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Services\Wallet\Exceptions\DuplicateTransactionException;
use App\Services\Wallet\Exceptions\InsufficientFundsException;

class WalletService
{
  /**
   * Credit a wallet (idempotent)
   */
  public function credit(
    Wallet $wallet,
    float $amount,
    string $sourceType,
    int $sourceId,
    array $meta = []
  ): WalletTransaction {
    if ($amount <= 0) {
      throw new \InvalidArgumentException('Credit amount must be positive');
    }

    return DB::transaction(function () use ($wallet, $amount, $sourceType, $sourceId, $meta) {

      // 🔒 Idempotency check
      if (
        WalletTransaction::where('wallet_id', $wallet->id)
          ->where('source_type', $sourceType)
          ->where('source_id', $sourceId)
          ->exists()
      ) {
        throw new DuplicateTransactionException(
          "Duplicate wallet credit: {$sourceType} #{$sourceId}"
        );
      }

      return WalletTransaction::create([
        'wallet_id' => $wallet->id,
        'type' => 'credit',
        'amount' => $amount,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'meta' => $meta,
      ]);
    });
  }

  /**
   * Debit a wallet (balance-safe)
   */
  public function debit(
    Wallet $wallet,
    float $amount,
    string $sourceType,
    int $sourceId,
    array $meta = []
  ): WalletTransaction {
    if ($amount <= 0) {
      throw new \InvalidArgumentException('Debit amount must be positive');
    }

    return DB::transaction(function () use ($wallet, $amount, $sourceType, $sourceId, $meta) {

      // 🔒 Lock the wallet row to prevent concurrent double-debits
      $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

      if ($locked->balance < $amount) {
        throw new InsufficientFundsException(
          "Wallet {$locked->id} has insufficient funds"
        );
      }

      if (
        WalletTransaction::where('wallet_id', $locked->id)
          ->where('source_type', $sourceType)
          ->where('source_id', $sourceId)
          ->exists()
      ) {
        throw new DuplicateTransactionException(
          "Duplicate wallet debit: {$sourceType} #{$sourceId}"
        );
      }

      return WalletTransaction::create([
        'wallet_id' => $locked->id,
        'type' => 'debit',
        'amount' => $amount,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'meta' => $meta,
      ]);
    });
  }
}
