<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_nominations', function (Blueprint $table): void {
            $table->integer('player_id')->nullable()->change();
            $table->string('nominee_name')->nullable();
            $table->string('nominee_surname')->nullable();
            $table->string('nominee_email')->nullable();
            $table->string('profileless_key', 64)->nullable()->unique();
        });
        Schema::table('interprovincial_trial_invitations', function (Blueprint $table): void {
            $table->unsignedBigInteger('player_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('event_nominations')->whereNull('player_id')->exists()
            || \Illuminate\Support\Facades\DB::table('interprovincial_trial_invitations')->whereNull('player_id')->exists()) {
            throw new RuntimeException('Resolve or remove pending profileless nominations before rollback.');
        }
        Schema::table('event_nominations', function (Blueprint $table): void {
            $table->dropUnique(['profileless_key']);
            $table->dropColumn(['nominee_name', 'nominee_surname', 'nominee_email', 'profileless_key']);
            $table->integer('player_id')->nullable(false)->change();
        });
        Schema::table('interprovincial_trial_invitations', function (Blueprint $table): void {
            $table->unsignedBigInteger('player_id')->nullable(false)->change();
        });
    }
};
