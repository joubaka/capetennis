<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teamsheet_profile_link_requests', function (Blueprint $table) {
            $table->id();
            $table->string('state_hash', 64)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('player_id');
            $table->string('session_hash', 64);
            $table->string('origin', 500);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('teamsheet_tennis_player_links', function (Blueprint $table) {
            $table->string('link_method', 30)->default('admin_review');
            $table->unsignedBigInteger('linked_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teamsheet_tennis_player_links', fn (Blueprint $table) => $table->dropColumn(['link_method', 'linked_by']));
        Schema::dropIfExists('teamsheet_profile_link_requests');
    }
};
