<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\Draw;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TeamEventRulesService
{
    public const STANDINGS_ORDER = ['points', 'tie_wins', 'rubber_difference', 'set_difference', 'game_difference'];

    public function defaults(): array
    {
        $rubbers = [];
        foreach (RubberType::ALL as $type) {
            $singles = in_array($type, [RubberType::SINGLES, RubberType::REVERSE_SINGLES], true);
            $rubbers[$type] = ['sets_to_win' => $singles ? 2 : 1, 'straight_win' => $singles ? 3 : 2,
                'deciding_win' => 2, 'loss' => 0, 'deciding_loss' => $singles ? 1 : 0,
                'close_loss' => $singles ? 0 : 1, 'close_game_margin' => 1];
        }
        return ['rubbers' => $rubbers, 'tie_win' => 0, 'tie_draw' => 0, 'tie_loss' => 0, 'standings_order' => ['points']];
    }

    public function forEvent(Event $event): array
    {
        $stored = DB::table('team_event_rules')->where('event_id', $event->id)->value('rules');
        return $stored ? json_decode($stored, true, flags: JSON_THROW_ON_ERROR) : $this->defaults();
    }

    public function forDraw(Draw $draw): array
    {
        // Historical draws never inherit a later event configuration.
        return $draw->team_scoring_rules ?? $this->defaults();
    }

    public function validate(Request $request): array
    {
        $rules = ['rules' => 'required|array:rubbers,tie_win,tie_draw,tie_loss,standings_order',
            'rules.rubbers' => 'required|array:'.implode(',', RubberType::ALL),
            'rules.standings_order' => 'required|array|min:1|max:5',
            'rules.standings_order.*' => 'required|distinct|in:'.implode(',', self::STANDINGS_ORDER)];
        foreach (['tie_win', 'tie_draw', 'tie_loss'] as $field) {
            $rules["rules.{$field}"] = 'required|numeric|min:0|max:100';
        }
        foreach (RubberType::ALL as $type) {
            $rules["rules.rubbers.{$type}"] = 'required|array:sets_to_win,straight_win,deciding_win,loss,deciding_loss,close_loss,close_game_margin';
            $rules["rules.rubbers.{$type}.sets_to_win"] = 'required|integer|min:1|max:2';
            $rules["rules.rubbers.{$type}.close_game_margin"] = 'required|integer|min:0|max:100';
            foreach (['straight_win', 'deciding_win', 'loss', 'deciding_loss', 'close_loss'] as $field) {
                $rules["rules.rubbers.{$type}.{$field}"] = 'required|numeric|min:0|max:100';
            }
        }
        $validated = $request->validate($rules)['rules'];
        foreach ($validated['rubbers'] as &$rubber) {
            foreach ($rubber as $key => &$value) {
                $value = in_array($key, ['sets_to_win', 'close_game_margin'], true) ? (int) $value : (float) $value;
            }
            unset($value);
        }
        unset($rubber);
        foreach (['tie_win', 'tie_draw', 'tie_loss'] as $field) {
            $validated[$field] = (float) $validated[$field];
        }
        return $validated;
    }

    public function save(Event $event, array $rules): void
    {
        DB::transaction(function () use ($event, $rules) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            DB::table('team_event_rules')->updateOrInsert(['event_id' => $event->id],
                ['rules' => json_encode($rules, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
