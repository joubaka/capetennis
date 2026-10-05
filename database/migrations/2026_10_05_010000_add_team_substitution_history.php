<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('no_profile_team_players', fn (Blueprint $t) => $t->integer('team_id')->nullable()->change());
        Schema::table('team_fixture_players', fn (Blueprint $t) => $t->json('participant_snapshot')->nullable());
        Schema::create('team_substitutions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id');
            $t->unsignedBigInteger('team_id');
            $t->unsignedBigInteger('actor_id');
            $t->string('request_key', 80);
            $t->string('fingerprint', 64);
            $t->json('details');
            $t->timestamp('created_at');
            $t->unique(['event_id', 'request_key']);
            $t->index('team_id');
        });
    }
    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('team_substitutions')->exists()) {
            throw new RuntimeException('Cannot remove recorded competition substitution history.');
        }
        if (\Illuminate\Support\Facades\DB::table('no_profile_team_players')->whereNull('team_id')->exists()) {
            throw new RuntimeException('Cannot remove standalone competition identities while their history exists.');
        }
        Schema::dropIfExists('team_substitutions');
        Schema::table('team_fixture_players', fn (Blueprint $t) => $t->dropColumn('participant_snapshot'));
        Schema::table('no_profile_team_players', fn (Blueprint $t) => $t->integer('team_id')->nullable(false)->change());
    }
};
