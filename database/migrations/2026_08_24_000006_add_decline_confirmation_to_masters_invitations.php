<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('masters_invitations', function (Blueprint $table) {
            $table->timestamp('decline_confirmation_sent_at')->nullable()->after('declined_at');
            $table->timestamp('decline_confirmed_at')->nullable()->after('decline_confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('masters_invitations', function (Blueprint $table) {
            $table->dropColumn(['decline_confirmation_sent_at', 'decline_confirmed_at']);
        });
    }
};
