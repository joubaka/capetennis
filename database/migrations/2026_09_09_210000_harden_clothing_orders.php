<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $orderColumns = [
            'player_id' => fn (Blueprint $table) => $table->unsignedBigInteger('player_id')->nullable(),
            'team_id' => fn (Blueprint $table) => $table->unsignedBigInteger('team_id')->nullable(),
            'event_id' => fn (Blueprint $table) => $table->unsignedBigInteger('event_id')->nullable(),
            'total' => fn (Blueprint $table) => $table->decimal('total', 10, 2)->default(0),
            'status' => fn (Blueprint $table) => $table->string('status', 30)->default('pending'),
            'request_token' => fn (Blueprint $table) => $table->uuid('request_token')->nullable(),
            'payfast_paid' => fn (Blueprint $table) => $table->boolean('payfast_paid')->default(false),
            'payfast_pf_payment_id' => fn (Blueprint $table) => $table->string('payfast_pf_payment_id')->nullable(),
            'payfast_amount_due' => fn (Blueprint $table) => $table->decimal('payfast_amount_due', 10, 2)->default(0),
            'wallet_reserved' => fn (Blueprint $table) => $table->decimal('wallet_reserved', 10, 2)->default(0),
            'wallet_debited' => fn (Blueprint $table) => $table->boolean('wallet_debited')->default(false),
            'payment_method' => fn (Blueprint $table) => $table->string('payment_method', 30)->nullable(),
            'paid_at' => fn (Blueprint $table) => $table->timestamp('paid_at')->nullable(),
            'amount_paid' => fn (Blueprint $table) => $table->decimal('amount_paid', 10, 2)->nullable(),
        ];
        foreach ($orderColumns as $column => $definition) {
            if (! Schema::hasColumn('clothing_orders', $column)) {
                Schema::table('clothing_orders', fn (Blueprint $table) => $definition($table));
            }
        }
        Schema::table('clothing_orders', function (Blueprint $table) {
            $table->index('status', 'clothing_orders_status_index');
            $table->unique('request_token', 'clothing_orders_request_token_unique');
            $table->unique('payfast_pf_payment_id', 'clothing_orders_payfast_payment_unique');
        });

        if (! Schema::hasColumn('clothing_order_items', 'item_name')) {
            Schema::table('clothing_order_items', fn (Blueprint $table) => $table->string('item_name', 191)->nullable());
        }
        if (! Schema::hasColumn('clothing_order_items', 'size_name')) {
            Schema::table('clothing_order_items', fn (Blueprint $table) => $table->string('size_name', 191)->nullable());
        }

        DB::table('clothing_orders')->where('pay_status', 1)->update([
            'status' => 'completed',
            'payfast_paid' => true,
            'payment_method' => 'payfast',
        ]);
        DB::table('clothing_orders')->where('pay_status', '!=', 1)->update(['status' => 'pending']);

        DB::table('clothing_order_items')->orderBy('id')->chunkById(250, function ($rows) {
            $itemNames = DB::table('clothing_item_types')
                ->whereIn('id', $rows->pluck('clothing_order_item_id')->filter()->unique())
                ->pluck('item_type_name', 'id');
            $sizeNames = DB::table('clothing_sizes')
                ->whereIn('id', $rows->pluck('clothing_item_size')->filter()->unique())
                ->pluck('size', 'id');

            foreach ($rows as $row) {
                DB::table('clothing_order_items')->where('id', $row->id)->update([
                    'item_name' => $itemNames[$row->clothing_order_item_id] ?? null,
                    'size_name' => $sizeNames[$row->clothing_item_size] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clothing_order_items', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'size_name']);
        });

        Schema::table('clothing_orders', function (Blueprint $table) {
            $table->dropUnique('clothing_orders_request_token_unique');
            $table->dropUnique('clothing_orders_payfast_payment_unique');
            $table->dropIndex('clothing_orders_status_index');
            $table->dropColumn([
                'status', 'request_token', 'payfast_paid', 'payfast_pf_payment_id',
                'payfast_amount_due', 'wallet_reserved', 'wallet_debited',
                'payment_method', 'paid_at', 'amount_paid',
            ]);
        });
    }
};
