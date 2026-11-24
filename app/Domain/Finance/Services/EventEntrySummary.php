<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\CategoryEventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Read-only participation counts; payment-ledger entry counts remain separate. */
class EventEntrySummary
{
    public const COLUMNS = [
        'recorded' => 'Recorded entries',
        'confirmed' => 'Active confirmed',
        'unpaid' => 'Active unpaid',
        'withdrawn' => 'Withdrawals',
        'completed' => 'Refund completed',
        'pending' => 'Refund pending',
        'waived' => 'Refund waived',
        'no_refund' => 'No refund recorded',
    ];

    public function build(Event $event): array
    {
        $divisions = DB::table('category_events as ce')->leftJoin('categories as c', 'c.id', '=', 'ce.category_id')
            ->where('ce.event_id', $event->id)->orderBy('ce.ordering')->orderBy('ce.id')
            ->get(['ce.id', 'c.name'])->mapWithKeys(fn ($division) => [$division->id => [
                'name' => $division->name ?? 'Unnamed division', ...array_fill_keys(array_keys(self::COLUMNS), 0),
            ]])->all();

        if ($event->isTeam()) {
            // Imported teams have regular mirror rows: only their authoritative roster is counted.
            foreach ([['team_players', 'player_id', false], ['no_profile_team_players', 'id', true]] as [$table, $identity, $imported]) {
                $rows = DB::table($table.' as slot')->join('teams as t', 't.id', '=', 'slot.team_id')
                    ->join('category_events as ce', 'ce.id', '=', 't.category_event_id')
                    ->where('ce.event_id', $event->id)->where('t.noProfile', $imported)->where('slot.'.$identity, '>', 0)
                    ->where(function ($query) use ($event, $imported) {
                        $playerColumn = $imported ? 'slot.player_profile' : 'slot.player_id';
                        $withdrawn = fn ($orders) => $orders->selectRaw('1')->from('team_payment_orders as coverage')
                            ->where('coverage.event_id', $event->id)->whereColumn('coverage.team_id', 't.id')
                            ->whereRaw('COALESCE(coverage.beneficiary_player_id, coverage.player_id) = '.$playerColumn)
                            ->where('coverage.pay_status', 1);
                        $query->whereNotExists(fn ($orders) => $withdrawn($orders)->whereNotNull('coverage.withdrawn_at'))
                            ->orWhereExists(fn ($orders) => $withdrawn($orders)->whereNull('coverage.withdrawn_at'));
                    })
                    ->selectRaw('ce.id as division_id, slot.pay_status as paid, COUNT(*) as total')
                    ->groupBy('ce.id', 'slot.pay_status')->get();
                foreach ($rows as $row) {
                    $divisions[$row->division_id][$row->paid ? 'confirmed' : 'unpaid'] += (int) $row->total;
                }
            }
            // Unpaid cancellations/expiry also set withdrawn_at; they are not participant withdrawals.
            $withdrawals = DB::table('team_payment_orders as entry')->join('teams as t', 't.id', '=', 'entry.team_id')
                ->join('category_events as ce', 'ce.id', '=', 't.category_event_id')
                ->where('entry.event_id', $event->id)->where('ce.event_id', $event->id)
                ->where('entry.pay_status', 1)->whereNotNull('entry.withdrawn_at')
                ->selectRaw('ce.id as division_id, entry.refund_status, COUNT(*) as total')
                ->groupBy('ce.id', 'entry.refund_status')->get();
        } else {
            $rows = DB::table('category_event_registrations as entry')->join('category_events as ce', 'ce.id', '=', 'entry.category_event_id')
                ->where('ce.event_id', $event->id)
                // Canonical player-merge retirement reuses withdrawn state for an unpaid duplicate.
                // Match its exact subject and event/division/registration audit tuple, never reason text.
                ->where(fn ($query) => $this->excludeMergeRetirement($query))
                ->where(function ($query) {
                    $query->whereIn('entry.status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded'])
                        ->orWhereNotNull('entry.withdrawn_at')
                        ->orWhere(fn ($active) => $active->whereNull('entry.deleted_at')->where('entry.status', 'active'));
                })
                ->selectRaw("ce.id as division_id, CASE WHEN entry.withdrawn_at IS NOT NULL OR entry.status IN ('withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded') THEN 1 ELSE 0 END as withdrawn, entry.payment_status_id as paid, entry.refund_status, COUNT(*) as total")
                ->groupBy('ce.id', 'withdrawn', 'entry.payment_status_id', 'entry.refund_status')->get();
            foreach ($rows->where('withdrawn', 0) as $row) {
                $divisions[$row->division_id][(int) $row->paid === 1 ? 'confirmed' : 'unpaid'] += (int) $row->total;
            }
            $withdrawals = $rows->where('withdrawn', 1);
        }

        foreach ($withdrawals as $row) {
            $division = &$divisions[$row->division_id];
            $division['withdrawn'] += (int) $row->total;
            $status = in_array($row->refund_status, ['completed', 'pending', 'waived'], true) ? $row->refund_status : 'no_refund';
            $division[$status] += (int) $row->total;
            unset($division);
        }
        $totals = array_fill_keys(array_keys(self::COLUMNS), 0);
        foreach ($divisions as &$division) {
            $division['recorded'] = $division['confirmed'] + $division['unpaid'] + $division['withdrawn'];
            foreach ($totals as $key => $value) $totals[$key] += $division[$key];
        }
        unset($division);

        return ['columns' => self::COLUMNS, 'divisions' => array_values($divisions), 'totals' => $totals, 'team' => $event->isTeam()];
    }

    /** Private finance drilldown: page entry identities first, then resolve only that page's names. */
    public function withdrawals(Event $event): LengthAwarePaginator
    {
        if ($event->isTeam()) {
            $query = DB::table('team_payment_orders as entry')->join('teams as t', 't.id', '=', 'entry.team_id')
                ->join('category_events as ce', 'ce.id', '=', 't.category_event_id')
                ->leftJoin('categories as c', 'c.id', '=', 'ce.category_id')
                ->leftJoin('players as p', function ($join) {
                    $join->on('p.id', '=', DB::raw('COALESCE(entry.beneficiary_player_id, entry.player_id)'));
                })
                ->where('entry.event_id', $event->id)->where('ce.event_id', $event->id)
                ->where('entry.pay_status', 1)->whereNotNull('entry.withdrawn_at')
                ->select(['entry.id', 'entry.withdrawn_at', 'entry.refund_status', 'c.name as division', 't.name as team', 'p.name', 'p.surname']);
        } else {
            $query = DB::table('category_event_registrations as entry')->join('category_events as ce', 'ce.id', '=', 'entry.category_event_id')
                ->leftJoin('categories as c', 'c.id', '=', 'ce.category_id')->where('ce.event_id', $event->id)
                ->where(fn ($query) => $this->excludeMergeRetirement($query))
                ->where(fn ($query) => $query->whereIn('entry.status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded'])->orWhereNotNull('entry.withdrawn_at'))
                ->select(['entry.id', 'entry.registration_id', 'entry.withdrawn_at', 'entry.refund_status', 'entry.deleted_at', 'c.name as division']);
        }
        $page = $query->orderByDesc('entry.withdrawn_at')->orderByDesc('entry.id')->paginate(25)->withQueryString();
        $names = $event->isTeam() ? collect() : DB::table('player_registrations as pr')->join('players as p', 'p.id', '=', 'pr.player_id')
            ->whereIn('pr.registration_id', $page->getCollection()->pluck('registration_id'))->orderBy('p.surname')->orderBy('p.name')
            ->get(['pr.registration_id', 'p.name', 'p.surname'])->groupBy('registration_id');
        foreach ($page as $row) {
            $row->players = $event->isTeam() ? trim(($row->name ?? '').' '.($row->surname ?? ''))
                : $names->get($row->registration_id, collect())->map(fn ($p) => trim($p->name.' '.$p->surname))->implode(' / ');
            $row->source = $event->isTeam() ? 'Team registration' : 'Individual entry';
            $row->archived = !empty($row->deleted_at);
            $row->refund_label = self::COLUMNS[in_array($row->refund_status, ['completed', 'pending', 'waived'], true) ? $row->refund_status : 'no_refund'];
        }
        return $page;
    }

    private function excludeMergeRetirement(Builder $query): void
    {
        $query->where('entry.payment_status_id', 1)->orWhereNotExists(function ($audit) {
            $audit->selectRaw('1')->from('activity_log as retirement')
                ->where('retirement.log_name', 'player-profile-merge')
                ->where('retirement.description', 'Retired unpaid duplicate registration during player merge')
                ->where('retirement.subject_type', (new CategoryEventRegistration)->getMorphClass())
                ->whereColumn('retirement.subject_id', 'entry.id')
                ->whereColumn('retirement.properties->category_event_id', 'entry.category_event_id')
                ->whereColumn('retirement.properties->duplicate_registration_id', 'entry.registration_id');
        });
    }
}
