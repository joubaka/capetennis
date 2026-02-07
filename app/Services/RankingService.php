namespace App\Services;

use App\Models\RankingList;
use App\Models\Series;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class RankingService
{
    public function build(RankingList $list): array
    {
        $seriesId = $list->series_id;
        $bestN = (int) (optional($list->series)->best_num_of_scores ?? 9999);

        // position -> points
        $pos2pts = DB::table('points')
            ->where('series_id', $seriesId)
            ->pluck('score', 'position'); // collection: [position => score]

        // CategoryEvents in this ranking list
        $ceIds = DB::table('ranking_list_category_events')
            ->where('ranking_list_id', $list->id)
            ->pluck('category_event_id');

        if ($ceIds->isEmpty()) return [];

        // Legs per registration (from the VIEW)
        $legs = DB::table('category_event_registration_placements as rp')
            ->whereIn('rp.category_event_id', $ceIds)
            ->join('category_events as ce', 'ce.id', '=', 'rp.category_event_id')
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->join('categories as c', 'c.id', '=', 'ce.category_id')
            ->selectRaw('rp.registration_id, rp.category_event_id,
                         rp.position,
                         e.name as event_name,
                         c.name as category_name')
            ->get()
            ->groupBy('registration_id');

        $rows = [];
        foreach ($legs as $registrationId => $items) {
            // map legs to points
            $mapped = $items->map(function ($row) use ($pos2pts) {
                $pos = (int) $row->position;
                $pts = (int) ($pos2pts[$pos] ?? 0);

                return [
                    'event'    => $row->event_name,
                    'category' => $row->category_name,
                    'pos'      => $pos,
                    'pts'      => $pts,
                    'synthetic'=> false,
                ];
            })->sortByDesc('pts')->values();

            // Best N
            $top = $mapped->slice(0, $bestN);
            $total = $top->sum('pts');

            $name = registrationDisplayName(
                Registration::with('players')->find($registrationId)
            );

            $rows[] = [
                'registration_id' => (int)$registrationId,
                'player' => $name,
                'points' => (int)$total,
                'legs'   => $top->all(),
            ];
        }

        // Sort + rank
        usort($rows, fn($a,$b) => $b['points'] <=> $a['points'] ?: strcmp($a['player'], $b['player']));
        $rank=1; $lastPts=null; $lastRank=1;
        foreach ($rows as &$r) {
            if ($lastPts === null || $r['points'] < $lastPts) $lastRank = $rank;
            $r['rank'] = $lastRank;
            $lastPts = $r['points'];
            $rank++;
        }

        return $rows;
    }
}
