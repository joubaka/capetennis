<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interprovincial_trial_invitations', function (Blueprint $table): void {
            $table->unsignedBigInteger('registration_id')->nullable()->after('player_id');
            $table->unsignedBigInteger('order_id')->nullable()->after('registration_id');
            $table->timestamp('accepted_at')->nullable()->after('sent_at');
            $table->timestamp('paid_at')->nullable()->after('accepted_at');
            $table->timestamp('withdrawn_at')->nullable()->after('paid_at');
            $table->unique('registration_id', 'interpro_trial_invitations_registration_unique');
            $table->unique('order_id', 'interpro_trial_invitations_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('interprovincial_trial_invitations', function (Blueprint $table): void {
            $table->dropUnique('interpro_trial_invitations_registration_unique');
            $table->dropUnique('interpro_trial_invitations_order_unique');
            $table->dropColumn(['registration_id', 'order_id', 'accepted_at', 'paid_at', 'withdrawn_at']);
        });
    }
};
