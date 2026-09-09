<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table): void {
            $table->unsignedTinyInteger('minimum_events_for_team_selection')
                ->default(1)
                ->after('best_num_of_scores');
        });

        $this->updateSystemPreset('overberg-rankings', [
            'minimum_events_for_team_selection' => 1,
        ]);
        $this->updateSystemPreset('witzenberg-winelands-rankings', [
            'auto_award_rule' => false,
            'minimum_events_for_team_selection' => 2,
        ]);

        DB::table('series')
            ->where(function ($query): void {
                $query->where('name', 'like', '%Witzenberg%')
                    ->orWhere('name', 'like', '%Winelands%');
            })
            ->update([
                'auto_award_rule' => false,
                'minimum_events_for_team_selection' => 2,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('series', function (Blueprint $table): void {
            $table->dropColumn('minimum_events_for_team_selection');
        });
    }

    /** @param array<string, int|bool> $changes */
    private function updateSystemPreset(string $slug, array $changes): void
    {
        $preset = DB::table('ranking_rule_presets')->where('slug', $slug)->first();
        if (! $preset) {
            return;
        }

        $rules = json_decode((string) $preset->rules, true, 512, JSON_THROW_ON_ERROR);
        DB::table('ranking_rule_presets')->where('id', $preset->id)->update([
            'rules' => json_encode(array_merge($rules, $changes), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }
};
