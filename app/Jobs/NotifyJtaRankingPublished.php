<?php

namespace App\Jobs;

use App\Models\SeriesRanking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NotifyJtaRankingPublished implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public function __construct(public int $seriesId, public int $runId) {}

    public function handle(): void
    {
        $url = (string) config('integrations.jta.publication_webhook_url');
        $secret = (string) config('integrations.jta.publication_webhook_secret');
        if ($url === '' || $secret === '') throw new RuntimeException('JTA publication webhook is not configured.');

        $rows = SeriesRanking::query()->with(['series', 'category', 'player', 'rankingList'])
            ->where('series_id', $this->seriesId)->where('run_id', $this->runId)->where('status', 'published')->get();
        $items = $rows->map(function (SeriesRanking $ranking): array {
            $payload = [
                'source' => 'cape_tennis', 'result_type' => 'series_ranking',
                'source_result_id' => "ct-series-ranking-{$ranking->series_id}-{$ranking->category_id}-{$ranking->player_id}",
                'series' => ['id' => (int) $ranking->series_id, 'name' => (string) $ranking->series?->name, 'year' => $ranking->series?->year],
                'ranking_list_id' => (int) $ranking->ranking_list_id,
                'category' => ['id' => (int) $ranking->category_id, 'name' => (string) $ranking->category?->name],
                'player' => ['cape_tennis_player_id' => (int) $ranking->player_id, 'display_name' => trim((string) $ranking->player?->name.' '.(string) $ranking->player?->surname)],
                'rank_position' => (int) $ranking->rank_position, 'total_points' => (float) $ranking->total_points,
                'published_at' => optional($ranking->published_at)->toIso8601String(), 'event_legs' => [],
            ];
            return $payload + ['source_version' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 'source_updated_at' => ($ranking->published_at ?? $ranking->updated_at)->toIso8601String()];
        })->values()->all();
        $body = ['event' => 'ranking.published', 'data' => ['source_id' => "ct-ranking-run-{$this->seriesId}-{$this->runId}", 'source_version' => hash('sha256', json_encode($items)), 'source_updated_at' => now()->toIso8601String(), 'items' => $items]];
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        Http::acceptJson()->withHeaders(['X-Cape-Tennis-Signature' => hash_hmac('sha256', $json, $secret)])->timeout(20)->post($url, $body)->throw();
    }
}
