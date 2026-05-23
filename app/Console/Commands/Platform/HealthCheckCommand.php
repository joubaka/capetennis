<?php

namespace App\Console\Commands\Platform;

use App\Services\PlatformHealthService;
use Illuminate\Console\Command;

/**
 * platform:health-check
 *
 * CLI health check. Mirrors the dashboard in terminal output.
 * Exits 1 if any critical issue found. Useful for uptime monitors and CI.
 */
class HealthCheckCommand extends Command
{
    protected $signature   = 'platform:health-check {--section= : Run only a specific section (engine|financial|draw|registration|queue|system)} {--json : Output raw JSON}';
    protected $description = 'Platform operational health check — mirrors the admin dashboard';

    private array $icons = [
        'ok'       => '✅',
        'warn'     => '⚠️ ',
        'critical' => '🔴',
    ];

    public function __construct(private PlatformHealthService $health) {
        parent::__construct();
    }

    public function handle(): int
    {
        $section = $this->option('section');
        $all     = $this->health->all();

        if ($this->option('json')) {
            $this->line(json_encode($all, JSON_PRETTY_PRINT));
            return $all['summary']['critical'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $sections = [
            'engine'       => '⚙️  Engine Health',
            'financial'    => '💰  Financial Health',
            'draw'         => '🎾  Draw Health',
            'registration' => '📋  Registration Health',
            'queue'        => '📬  Queue Health',
            'system'       => '🖥️  System Health',
        ];

        foreach ($sections as $key => $title) {
            if ($section && $section !== $key) {
                continue;
            }

            $this->info('');
            $this->info("  {$title}");
            $this->line('  ' . str_repeat('─', 50));

            foreach ($all[$key] as $item) {
                $icon   = $this->icons[$item['status']] ?? '?';
                $detail = $item['detail'] ? "  [{$item['detail']}]" : '';
                $this->line(sprintf(
                    '  %s  %-36s %s%s',
                    $icon, $item['label'], $item['value'], $detail
                ));
            }
        }

        $s = $all['summary'];
        $this->info('');
        $this->info('  ─────────────────────────────────────────────────');
        $this->info("  Summary: 🔴 {$s['critical']} critical  ⚠️  {$s['warn']} warnings  ✅ {$s['ok']} passing");
        $this->info('');

        return $s['critical'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
