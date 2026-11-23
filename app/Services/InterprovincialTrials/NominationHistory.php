<?php

namespace App\Services\InterprovincialTrials;

use App\Models\Event;
use App\Models\EventNomination;
use Illuminate\Support\Facades\DB;

/** Read-only, event-scoped current and removed nomination register. */
class NominationHistory
{
    public function build(Event $event, string $filter = 'all'): array
    {
        $latest = DB::table('interprovincial_trial_invitations as i')
            ->join('event_nominations as n', function ($join) {
                $join->on('n.id', '=', 'i.nomination_id')->on('n.event_id', '=', 'i.event_id')
                    ->on('n.category_event_id', '=', 'i.category_event_id')
                    ->where(function ($q) {
                        $q->whereColumn('n.player_id', 'i.player_id')->orWhere(fn ($q) => $q->whereNull('n.player_id')->whereNull('i.player_id'));
                    });
            })->where('n.event_id', $event->id)
            ->selectRaw('i.nomination_id, COALESCE(MAX(CASE WHEN i.status != ? THEN i.id END), MAX(i.id)) as invitation_id', ['prepared'])
            ->groupBy('i.nomination_id');
        $current = DB::table('event_nominations as n')->join('category_events as c', 'c.id', '=', 'n.category_event_id')
            ->where('n.event_id', $event->id)->where('c.event_id', $event->id)
            ->leftJoinSub($latest, 'latest', 'latest.nomination_id', '=', 'n.id')
            ->leftJoin('interprovincial_trial_invitations as i', 'i.id', '=', 'latest.invitation_id')
            ->selectRaw("n.id, n.category_event_id, NULL as audit_id, CASE
                WHEN i.status = 'withdrawn' THEN 'withdrawn'
                WHEN i.status IN ('declined','admin_declined') THEN 'declined'
                WHEN i.status = 'cancelled' THEN 'cancelled'
                WHEN i.status = 'paid_confirmed' THEN 'registered'
                WHEN i.status = 'accepted_pending_payment' THEN 'payment-pending'
                WHEN n.archived_at IS NOT NULL THEN 'archived'
                WHEN i.status IN ('queued','sending','sent') THEN 'invited'
                WHEN i.status = 'failed' THEN 'delivery-failed'
                ELSE 'nominated' END as state");

        $beforeEvent = $this->json('a.before', 'event_id');
        $beforeCategory = $this->json('a.before', 'category_event_id');
        $removed = DB::table('audit_events as a')->join('category_events as c', function ($join) use ($beforeCategory) {
            $join->whereRaw("c.id = $beforeCategory");
        })->where('a.event_id', $event->id)->where('c.event_id', $event->id)
            ->whereRaw("$beforeEvent = ?", [$event->id])
            ->where('a.subject_type', EventNomination::class)->where('a.action', 'event-nomination.deleted')->where('a.outcome', 'succeeded')
            ->whereRaw($this->json('a.before', 'id').' = a.subject_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('event_nominations as retained')->whereColumn('retained.id', 'a.subject_id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('audit_events as newer')
                ->whereColumn('newer.event_id', 'a.event_id')->whereColumn('newer.subject_type', 'a.subject_type')
                ->whereColumn('newer.subject_id', 'a.subject_id')->whereColumn('newer.action', 'a.action')
                ->where('newer.outcome', 'succeeded')->whereColumn('newer.id', '>', 'a.id'))
            ->selectRaw("CAST(a.subject_id AS UNSIGNED) as id, c.id as category_event_id, a.id as audit_id, 'removed' as state");
        // Reject malformed identifiers rather than allowing SQL coercion to invent a match.
        if (DB::getDriverName() === 'sqlite') {
            $removed->whereRaw("a.subject_id != '' AND a.subject_id NOT GLOB '*[^0-9]*'");
        } else {
            $removed->whereRaw("a.subject_id REGEXP '^[0-9]+$'");
        }
        foreach (['id', 'event_id', 'category_event_id'] as $key) {
            $value = $this->json('a.before', $key);
            $removed->whereRaw(DB::getDriverName() === 'sqlite'
                ? "$value IS NOT NULL AND CAST($value AS TEXT) != '' AND CAST($value AS TEXT) NOT GLOB '*[^0-9]*'"
                : "$value REGEXP '^[0-9]+$'");
        }
        $register = DB::query()->fromSub($current->unionAll($removed), 'register');
        $counts = (clone $register)->selectRaw('state, COUNT(*) as total')->groupBy('state')->pluck('total', 'state');
        $categoryCounts = (clone $register)->selectRaw('category_event_id, COUNT(*) as total')->groupBy('category_event_id')->pluck('total', 'category_event_id');
        $rows = (clone $register)->when($filter !== 'all', fn ($q) => $q->where('state', $filter))
            ->orderBy('category_event_id')->orderBy('id')->paginate(100, ['*'], 'nominations_page')->withQueryString();
        $retained = EventNomination::with(['player:id,name,surname', 'categoryEvent.category:id,name'])
            ->where('event_id', $event->id)->whereIn('id', $rows->getCollection()->whereNull('audit_id')->pluck('id'))->get()->keyBy('id');
        $audits = DB::table('audit_events')->where('event_id', $event->id)
            ->whereIn('id', $rows->getCollection()->pluck('audit_id')->filter())->get(['id', 'before', 'occurred_at', 'actor_name', 'reason'])->keyBy('id');
        $players = \App\Models\Player::select(['id', 'name', 'surname'])->whereIn('id', $audits->map(function ($audit) {
            $id = json_decode($audit->before, true)['player_id'] ?? null;
            return is_int($id) || (is_string($id) && ctype_digit($id)) ? $id : null;
        })->filter())->get()->keyBy('id');
        $rows->setCollection($rows->getCollection()->map(function ($row) use ($retained, $audits, $players, $event) {
            if (!$row->audit_id) {
                $nomination = $retained->get($row->id);
            } else {
                $audit = $audits->get($row->audit_id);
                $snapshot = json_decode($audit->before, true);
                foreach (['nominee_name', 'nominee_surname'] as $key) {
                    $snapshot[$key] = is_string($snapshot[$key] ?? null) ? $snapshot[$key] : null;
                }
                $snapshot['player_id'] = is_int($snapshot['player_id'] ?? null) || (is_string($snapshot['player_id'] ?? null) && ctype_digit($snapshot['player_id']))
                    ? $snapshot['player_id'] : null;
                $nomination = new EventNomination();
                $nomination->forceFill(['id' => $row->id, 'event_id' => $event->id, 'category_event_id' => $row->category_event_id,
                    'player_id' => $snapshot['player_id'] ?? null, 'nominee_name' => $snapshot['nominee_name'] ?? null,
                    'nominee_surname' => $snapshot['nominee_surname'] ?? null]);
                $nomination->setAttribute('removed_at', $audit->occurred_at);
                $nomination->setAttribute('removed_by_name', $audit->actor_name);
                $nomination->setAttribute('removal_reason', $audit->reason);
                $playerId = $snapshot['player_id'] ?? null;
                $player = is_int($playerId) || (is_string($playerId) && ctype_digit($playerId)) ? $players->get($playerId) : null;
                $nomination->setRelation('player', $player);
                if (!$player && !trim($nomination->nominee_name.' '.$nomination->nominee_surname)) {
                    $nomination->nominee_name = 'Removed nominee #'.$row->id;
                }
            }
            $nomination->setAttribute('history_state', $row->state);
            return $nomination;
        }));
        return compact('rows', 'counts', 'categoryCounts');
    }

    private function json(string $column, string $key): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "json_extract($column, '$.$key')"
            : "JSON_UNQUOTE(JSON_EXTRACT($column, '$.$key'))";
    }
}
