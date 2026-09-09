<?php

namespace App\Domain\Ranking\Services;

use App\Models\RankingRulePreset;
use App\Models\User;
use Illuminate\Support\Collection;

final class RankingRulePresetService
{
    public const RULE_FIELDS = [
        'best_num_of_scores',
        'minimum_events_for_team_selection',
        'auto_award_rule',
        'use_third_score_tiebreak',
        'use_last_leg_position_tiebreak',
        'use_head_to_head_tiebreak',
    ];

    /** @return Collection<int, RankingRulePreset> */
    public function availableTo(User $user): Collection
    {
        return RankingRulePreset::query()
            ->where(function ($query) use ($user): void {
                $query->where('is_system', true)
                    ->orWhere('created_by', $user->id);
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function findAvailable(int $presetId, User $user): RankingRulePreset
    {
        return RankingRulePreset::query()
            ->whereKey($presetId)
            ->where(function ($query) use ($user): void {
                $query->where('is_system', true)
                    ->orWhere('created_by', $user->id);
            })
            ->firstOrFail();
    }

    /** @param array<string, mixed> $settings */
    public function create(string $name, array $settings, User $user): RankingRulePreset
    {
        $name = trim($name);
        $duplicate = RankingRulePreset::query()
            ->where(function ($query) use ($user): void {
                $query->where('is_system', true)
                    ->orWhere('created_by', $user->id);
            })
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            throw new \InvalidArgumentException('A ranking-rule preset with that name already exists.');
        }

        return RankingRulePreset::create([
            'name' => $name,
            'rules' => $this->rulesFrom($settings),
            'is_system' => false,
            'created_by' => $user->id,
        ]);
    }

    /** @param array<string, mixed> $settings
     *  @return array<string, int|bool>
     */
    public function rulesFrom(array $settings): array
    {
        return [
            'best_num_of_scores' => max(1, (int) ($settings['best_num_of_scores'] ?? 2)),
            'minimum_events_for_team_selection' => max(1, (int) ($settings['minimum_events_for_team_selection'] ?? 1)),
            'auto_award_rule' => (bool) ($settings['auto_award_rule'] ?? false),
            'use_third_score_tiebreak' => (bool) ($settings['use_third_score_tiebreak'] ?? false),
            'use_last_leg_position_tiebreak' => (bool) ($settings['use_last_leg_position_tiebreak'] ?? false),
            'use_head_to_head_tiebreak' => (bool) ($settings['use_head_to_head_tiebreak'] ?? false),
        ];
    }
}
