<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the app-specific wallets and wallet_transactions tables.
 * Runs after bavix/laravel-wallet migrations so we can replace the
 * bavix schema with the app's own payable-morph schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        // If bavix already created wallets with holder_type, replace it with app schema.
        // If it already has payable_type, nothing to do.
        if (Schema::hasTable('wallets')) {
            if (Schema::hasColumn('wallets', 'payable_type')) {
                // Already correct app schema
            } else {
                // Bavix schema – drop and recreate
                Schema::dropIfExists('transfers');
                Schema::dropIfExists('transactions');
                Schema::drop('wallets');
                $this->createWallets();
            }
        } else {
            $this->createWallets();
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
                $table->string('type'); // credit | debit
                $table->decimal('amount', 10, 2);
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->json('meta')->nullable();
                $table->string('idempotency_key')->nullable()->unique();
                $table->timestamps();
            });
        }
    }

    private function createWallets(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->index(['payable_type', 'payable_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
