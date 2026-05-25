<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::table('category_event_registrations', function (Blueprint $table) {
    if (!Schema::hasColumn('category_event_registrations', 'payment_method')) {
        $table->string('payment_method')->nullable()->after('pf_transaction_id');
        echo "  + payment_method\n";
    } else {
        echo "  = payment_method already exists\n";
    }
    if (!Schema::hasColumn('category_event_registrations', 'wallet_transaction_id')) {
        $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('payment_method');
        echo "  + wallet_transaction_id\n";
    } else {
        echo "  = wallet_transaction_id already exists\n";
    }
    if (!Schema::hasColumn('category_event_registrations', 'withdrawn_by')) {
        $table->unsignedBigInteger('withdrawn_by')->nullable()->after('withdrawn_at');
        echo "  + withdrawn_by\n";
    } else {
        echo "  = withdrawn_by already exists\n";
    }
    if (!Schema::hasColumn('category_event_registrations', 'withdrawal_reason')) {
        $table->string('withdrawal_reason')->nullable()->after('withdrawn_by');
        echo "  + withdrawal_reason\n";
    } else {
        echo "  = withdrawal_reason already exists\n";
    }
});
echo "Done.\n";
