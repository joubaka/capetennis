<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_rule_presets', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->nullable()->unique();
            $table->json('rules');
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('series', function (Blueprint $table): void {
            $table->boolean('use_last_leg_position_tiebreak')->default(false)->after('use_third_score_tiebreak');
            $table->unsignedBigInteger('ranking_rule_preset_id')->nullable()->after('rank_type')->index();
        });

        $now = now();
        $overbergId = DB::table('ranking_rule_presets')->insertGetId([
            'name' => 'Overberg Rankings',
            'slug' => 'overberg-rankings',
            'rules' => json_encode([
                'best_num_of_scores' => 2,
                'auto_award_rule' => true,
                'use_third_score_tiebreak' => true,
                'use_last_leg_position_tiebreak' => false,
                'use_head_to_head_tiebreak' => true,
            ], JSON_THROW_ON_ERROR),
            'is_system' => true,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $witzenbergId = DB::table('ranking_rule_presets')->insertGetId([
            'name' => 'Witzenberg Winelands Rankings',
            'slug' => 'witzenberg-winelands-rankings',
            'rules' => json_encode([
                'best_num_of_scores' => 2,
                'auto_award_rule' => true,
                'use_third_score_tiebreak' => true,
                'use_last_leg_position_tiebreak' => true,
                'use_head_to_head_tiebreak' => false,
            ], JSON_THROW_ON_ERROR),
            'is_system' => true,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('series')
            ->where('year', 2026)
            ->where('name', 'like', '%Overberg%')
            ->update([
                'ranking_rule_preset_id' => $overbergId,
                'best_num_of_scores' => 2,
                'auto_award_rule' => true,
                'use_third_score_tiebreak' => true,
                'use_last_leg_position_tiebreak' => false,
                'use_head_to_head_tiebreak' => true,
                'updated_at' => $now,
            ]);

        DB::table('series')
            ->where('year', 2026)
            ->where(function ($query): void {
                $query->where('name', 'like', '%Witzenberg%')
                    ->orWhere('name', 'like', '%Winelands%');
            })
            ->update([
                'ranking_rule_preset_id' => $witzenbergId,
                'best_num_of_scores' => 2,
                'auto_award_rule' => true,
                'use_third_score_tiebreak' => true,
                'use_last_leg_position_tiebreak' => true,
                'use_head_to_head_tiebreak' => false,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table): void {
            $table->dropColumn(['use_last_leg_position_tiebreak', 'ranking_rule_preset_id']);
        });

        Schema::dropIfExists('ranking_rule_presets');
    }
};
