<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, Team};
use App\Support\Audit\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventPointSharingController extends Controller
{
    public function update(Request $request, Event $event)
    {
        $this->authorize('event.settings.manage', $event);
        abort_unless($event->isTeam(), 422, 'Point sharing requires a team event.');

        DB::transaction(function () use ($request, $event): void {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $regionIds = $locked->regions()->pluck('team_regions.id')->all();
            $teams = Team::query()->without(['team_players', 'team_players_no_profile'])
                ->whereHas('category', fn ($query) => $query->where('event_id', $locked->id))
                ->whereIn('region_id', $regionIds)->get()->keyBy('id');
            $data = $request->validate([
                'enabled' => ['required', 'boolean'],
                'rules' => ['present', 'array', 'max:200'],
                'rules.*' => ['array:team_id,enabled,mode,region_ids'],
                'rules.*.team_id' => ['required', 'integer', 'distinct', Rule::in($teams->keys()->all())],
                'rules.*.enabled' => ['required', 'boolean'],
                'rules.*.mode' => ['required', Rule::in(['full', 'split'])],
                'rules.*.region_ids' => ['present', 'array', 'max:100'],
                'rules.*.region_ids.*' => ['integer', Rule::in($regionIds)],
            ]);
            $rules = [];
            foreach ($data['rules'] as $index => $rule) {
                $recipients = array_map('intval', $rule['region_ids']);
                if (count(array_unique($recipients)) !== count($recipients)) {
                    throw ValidationException::withMessages(["rules.$index.region_ids" => 'Choose each recipient region only once per team.']);
                }
                sort($recipients);
                if (in_array((int) $teams[$rule['team_id']]->region_id, $recipients, true)) {
                    throw ValidationException::withMessages(["rules.$index.region_ids" => 'Choose recipient regions other than the source team’s region.']);
                }
                if ($rule['enabled'] && !$recipients) {
                    throw ValidationException::withMessages(["rules.$index.region_ids" => 'Select at least one recipient region for an enabled team.']);
                }
                $rules[] = ['team_id' => (int) $rule['team_id'], 'enabled' => (bool) $rule['enabled'], 'mode' => $rule['mode'], 'region_ids' => $recipients];
            }
            usort($rules, fn ($a, $b) => $a['team_id'] <=> $b['team_id']);
            $sharing = ['enabled' => (bool) $data['enabled'], 'rules' => $rules];
            $before = $locked->team_result_settings['point_sharing'] ?? ['enabled' => false, 'rules' => []];
            if ($sharing === $before) return;
            $locked->team_result_settings = array_replace($locked->team_result_settings ?? [], ['point_sharing' => $sharing]);
            $locked->save();
            app(AuditWriter::class)->record([
                'category' => 'business', 'action' => 'event.standings.point-sharing.changed',
                'subject' => $locked, 'event_id' => $locked->id,
                'before' => ['point_sharing' => $before], 'after' => ['point_sharing' => $sharing],
            ], true);
        });

        return response()->json(['success' => true, 'message' => 'Point sharing saved. Overall regional standings now use these settings.'])
            ->header('Cache-Control', 'no-store, private');
    }
}
