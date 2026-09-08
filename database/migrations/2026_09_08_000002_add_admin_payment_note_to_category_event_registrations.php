<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('category_event_registrations', 'admin_payment_status')) {
            Schema::table('category_event_registrations', function (Blueprint $table) {
                $table->string('admin_payment_status', 20)
                    ->nullable()
                    ->after('payment_method')
                    ->comment('Private collection note for admin-created entries: unpaid or paid');
            });
        }

        // Existing admin entries were recorded as zero-value "Admin Entry"
        // transactions. Start their private-collection note as unpaid; an
        // event admin can explicitly confirm any money already received.
        DB::table('category_event_registrations')
            ->whereNull('admin_payment_status')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('player_registrations as pr')
                    ->join('transactions_pf as tx', function ($join) {
                        $join->on('tx.player_id', '=', 'pr.player_id')
                            ->whereColumn(
                                'tx.category_event_id',
                                'category_event_registrations.category_event_id'
                            );
                    })
                    ->whereColumn(
                        'pr.registration_id',
                        'category_event_registrations.registration_id'
                    )
                    ->where('tx.item_name', 'Admin Entry');
            })
            ->update(['admin_payment_status' => 'unpaid']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('category_event_registrations', 'admin_payment_status')) {
            Schema::table('category_event_registrations', function (Blueprint $table) {
                $table->dropColumn('admin_payment_status');
            });
        }
    }
};
