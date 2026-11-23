<?php

namespace Tests\Support;

use App\Models\Draw;
use App\Services\Draw\{DrawFinalPlacementService, InterproRoundRobinFormat};
use App\Services\DrawService;
use Illuminate\Support\Facades\DB;

/** Optional static review artifacts from fictional, rolled-back test records only. */
final class InterproPreviewExporter
{
    private string $directory;
    private string $assetBase;

    public function __construct()
    {
        if (!app()->environment('testing') || DB::connection()->getDatabaseName() !== 'ct_testing') {
            throw new \LogicException('Fictional preview export requires the isolated ct_testing database.');
        }
        $this->directory = storage_path('app/testing/interpro-preview');
        $this->assetBase = rtrim((string) (getenv('INTERPRO_PREVIEW_ASSET_BASE') ?: config('app.url')), '/');
        if (!in_array(parse_url($this->assetBase, PHP_URL_HOST), ['localhost', '127.0.0.1', 'ct.test'], true)) {
            throw new \LogicException('Review snapshots must use a configured local asset host.');
        }
        if (!is_dir($this->directory)) mkdir($this->directory, 0777, true);
    }

    public function setup(string $html): void
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $page = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " operational-page ")]')->item(0);
        if (!$page) throw new \LogicException('The actual setup page did not contain its application workspace.');
        foreach (iterator_to_array($xpath->query('.//script', $page)) as $script) $script->parentNode->removeChild($script);
        foreach ($xpath->query('.//a', $page) as $link) $link->setAttribute('href', '#');
        foreach ($xpath->query('.//form', $page) as $form) { $form->removeAttribute('action'); $form->setAttribute('onsubmit', 'return false'); }
        foreach ($xpath->query('.//input|.//button|.//select', $page) as $control) $control->setAttribute('disabled', 'disabled');
        $this->write('setup.html', $this->wrap('1 · Choose the Interpro format', $document->saveHTML($page)));
    }

    public function roundRobin(Draw $draw): void
    {
        $hub = app(DrawService::class)->loadRoundRobinHub($draw);
        $groups = $draw->groups()->orderBy('name')->get(['id', 'name'])->toArray();
        $data = json_encode(['groups' => $groups, 'standings' => $hub['standings']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $body = '<h1>2 · Round-robin finishing order</h1><p>Fictional completed groups. Player 1 wins each group; Player 4 finishes here. Review and confirm all four groups before progressing.</p><div id="rr-standings-wrapper"></div>'
            .'<script src="'.$this->assetBase.'/assets/js/roundrobin-standings.js"></script><script>const previewStandings='.$data.'; document.getElementById("rr-standings-wrapper").innerHTML=previewStandings.groups.map(group=>RRStandingsTable.renderGroup(group,Object.values(previewStandings.standings[group.id]||{}))).join("");</script>';
        $this->write('round-robin.html', $this->wrap('2 · Round-robin finishing order', $body));
    }

    public function stage(string $filename, string $title, string $actualBracketHtml): void
    {
        $this->write($filename, $this->wrap($title, '<h1>'.e($title).'</h1>'.$actualBracketHtml));
    }

    public function printPage(string $actualBracketHtml, Draw $draw): void
    {
        // Extract the exact CSS used by the real Print Bracket popup.
        $script = view('backend.draw.roundrobin.print-scripts', ['draw' => $draw])->render();
        $this->write('print-script.js', preg_replace('/^\s*<script>|<\/script>\s*$/u', '', $script));
        preg_match('/const printStyles = `([\s\S]*?)`;/u', $script, $print);
        preg_match('/const landscapeStyles = `([\s\S]*?)`;/u', $script, $landscape);
        if (!$print || !$landscape) throw new \LogicException('The current application print styles could not be reviewed.');
        $styles = $print[1].$landscape[1].'<style>'.file_get_contents(public_path('css/tennis-bracket.css')).'</style>';
        $body = '<div class="bracket-header"><h1>'.e($draw->drawName).'</h1><h2>Playoff Brackets</h2></div><div class="bracket-print-wrap">'.$actualBracketHtml.'</div>';
        $this->write('print.html', '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fictional Interpro print preview</title>'.$styles.'</head><body>'.$body.'</body></html>');
    }

    public function finish(Draw $draw): void
    {
        $fixtures = $draw->drawFixtures()->with(['fixtureResults', 'draw.settings'])->get();
        $carries = $fixtures->filter(fn ($fixture) => InterproRoundRobinFormat::hasReference($fixture))->map(function ($fixture) {
            $source = InterproRoundRobinFormat::source($fixture);
            return ['match' => $fixture->match_nr, 'source_match' => $source?->match_nr, 'score' => InterproRoundRobinFormat::score($fixture),
                'players' => [$fixture->registration1?->display_name, $fixture->registration2?->display_name]];
        })->values()->all();
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->map(fn ($row) => ['position' => $row['position'], 'name' => $row['name'], 'status' => $row['status']])->all();
        $manifest = ['fictional' => true, 'database' => 'ct_testing', 'asset_base' => $this->assetBase,
            'group_sizes' => [4, 4, 4, 4], 'players' => 16, 'round_robin_matches' => 24, 'playoff_positions' => 18,
            'carried_matches' => count($carries), 'played_match_records' => $fixtures->filter(fn ($fixture) => $fixture->fixtureResults->isNotEmpty())->count(),
            'set_records' => $fixtures->sum(fn ($fixture) => $fixture->fixtureResults->count()), 'carries' => $carries, 'final_positions' => $positions,
            'snapshots' => ['setup.html', 'round-robin.html', 'carry.html', 'final.html', 'print.html', 'blank.html'], 'print_script' => 'print-script.js',
            'scope' => 'Static snapshots from actual application rendering using fictional test records; all database changes roll back. Preview controls are disabled. No live records, publication, payments or communications.'];
        $this->write('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $body = '<h1>Interpro trials · fictional walkthrough</h1><p>Six static snapshots from the updated application. All names and results are fictional. Test records roll back; this preview has no working save, publish or payment controls.</p>'
            .'<p><strong>16 players · 4 groups · 24 RR matches · 18 playoff positions · 2 carried results · 40 genuine played matches.</strong></p><ol>'
            .'<li><a href="setup.html">Choose format</a> — the actual setup choice, with four groups and first result stands.</li>'
            .'<li><a href="round-robin.html">Round-robin standings</a> — Player 1 wins each group; fourth place finishes.</li>'
            .'<li><a href="carry.html">Feed-in and carried results</a> — B1 meets B2 and D1 meets D2; their first results advance them automatically.</li>'
            .'<li><a href="final.html">Completed draw and final positions</a> — twelve unique places; no extra matches for repeat pairings.</li>'
            .'<li><a href="print.html">Actual print layout</a> — the app’s Print Bracket popup styles, ready for browser print preview.</li>'
            .'<li><a href="blank.html">Blank planning draw</a> — source labels only, with no player names, scores or final-position list.</li></ol>'
            .'<p><a href="manifest.json">Evidence manifest</a> · Full first-stage and completed playoff corrections remain protected pending a reviewed correction workflow.</p>';
        $this->write('index.html', $this->wrap('Interpro trials · fictional walkthrough', $body));
    }

    private function wrap(string $title, string $body): string
    {
        $styles = '';
        foreach (['assets/vendor/css/core.css', 'assets/vendor/css/theme-default.css', 'assets/css/demo.css', 'assets/css/draw-roundrobin.css'] as $path) {
            $styles .= '<link rel="stylesheet" href="'.$this->assetBase.'/'.$path.'">';
        }
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).'</title>'.$styles
            .'<style>body{background:#f4f7fb;color:#172b4d}main.preview-content{max-width:1280px;margin:24px auto;padding:24px;background:#fff;border-radius:12px}header.preview-banner{padding:12px 20px;background:#102c50;color:#fff;font:14px Arial,sans-serif}header.preview-banner a{color:#fff;text-decoration:underline}.preview-content h1{font-size:26px}.preview-content .operational-page{padding:0}.preview-content a{overflow-wrap:anywhere}@media(max-width:576px){main.preview-content{margin:12px;padding:14px}.preview-content h1{font-size:22px}}@media print{header.preview-banner{display:none}main.preview-content{margin:0;padding:0;max-width:none}}</style></head><body><header class="preview-banner"><a href="index.html">Fictional Interpro preview</a> · Static review snapshot · Nothing here changes tournament records</header><main class="preview-content">'.$body.'</main></body></html>';
    }

    private function write(string $filename, string $contents): void
    {
        file_put_contents($this->directory.'/'.$filename, $contents);
    }
}
