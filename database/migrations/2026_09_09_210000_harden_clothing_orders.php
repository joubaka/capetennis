<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clothing_orders', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->after('pay_status')->index();
            $table->uuid('request_token')->nullable()->after('status')->unique();
            $table->boolean('payfast_paid')->default(false)->after('pf_id');
            $table->string('payfast_pf_payment_id')->nullable()->after('payfast_paid')->unique();
            $table->decimal('payfast_amount_due', 10, 2)->default(0)->after('total');
            $table->decimal('wallet_reserved', 10, 2)->default(0)->after('payfast_amount_due');
            $table->boolean('wallet_debited')->default(false)->after('wallet_reserved');
            $table->string('payment_method', 30)->nullable()->after('wallet_debited');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->decimal('amount_paid', 10, 2)->nullable()->after('paid_at');
        });

        Schema::table('clothing_order_items', function (Blueprint $table) {
            $table->string('item_name', 191)->nullable()->after('clothing_order_item_id');
            $table->string('size_name', 191)->nullable()->after('clothing_item_size');
        });

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
            $table->dropUnique(['request_token']);
            $table->dropUnique(['payfast_pf_payment_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status', 'request_token', 'payfast_paid', 'payfast_pf_payment_id',
                'payfast_amount_due', 'wallet_reserved', 'wallet_debited',
                'payment_method', 'paid_at', 'amount_paid',
            ]);
        });
    }
};
