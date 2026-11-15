<?php

namespace Tests\Feature;

use App\Services\Performance\PlayerPlusDerivedCommandRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlayerPlusDerivedCommandRunnerTest extends TestCase
{
    public function test_canonical_child_has_its_own_output_buffer_and_outer_json_remains_available(): void
    {
        $kernel = app(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->registerCommand(new class extends Command {
            protected $signature = 'player-ability:refresh {--reference=}';
            public function handle(): int { $this->line('Private child stage output'); return 0; }
        });
        $kernel->registerCommand(new class extends Command {
            protected $signature = 'test:derived-outer';
            public function handle(PlayerPlusDerivedCommandRunner $runner): int {
                $exit = $runner->run('player-ability:refresh', ['--reference' => 'retained-reference']);
                $this->line(json_encode(['status' => 'complete', 'child_exit' => $exit])); return $exit;
            }
        });
        $this->assertSame(0, Artisan::call('test:derived-outer'));
        $this->assertSame(['status' => 'complete', 'child_exit' => 0], json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Private child', Artisan::output());
    }

    public function test_runner_cannot_execute_unrelated_operational_commands(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PlayerPlusDerivedCommandRunner)->run('queue:work');
    }
}
