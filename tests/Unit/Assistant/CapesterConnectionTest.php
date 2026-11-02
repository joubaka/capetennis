<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\CapesterConnection;
use App\Console\Commands\CapesterPing;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class CapesterConnectionTest extends TestCase
{
    public function test_fixed_handshake_is_isolated_and_accepts_only_a_completed_exact_reply(): void
    {
        $directory = null;
        $connection = $this->connection($this->reply(), true, function (array $arguments, string $cwd) use (&$directory): void {
            $directory = $cwd;
            $this->assertDirectoryExists($cwd);
            $this->assertSame([], array_values(array_diff(scandir($cwd), ['.', '..'])));
            $this->assertSame(['codex', 'exec', '--ignore-user-config', '--sandbox', 'read-only', '--ephemeral',
                '-c', 'features.shell_tool=false', '--skip-git-repo-check', '--json', '-C', $cwd, CapesterConnection::PROMPT], $arguments);
        });
        $this->assertTrue($connection->ping());
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_application_environment_secrets_are_removed_from_child_process(): void
    {
        $_ENV['CT_HANDSHAKE_TEST_SECRET'] = 'fictional-secret';
        $_SERVER['CT_HANDSHAKE_SERVER_SECRET'] = 'fictional-secret';
        try {
            $capturedEnvironment = null;
            $connection = new CapesterConnection(function (array $arguments, string $cwd, array $environment) use (&$capturedEnvironment): Process {
                $capturedEnvironment = $environment;
                throw new \RuntimeException('Stop after inspecting environment.');
            });
            $this->assertFalse($connection->ping());
            $this->assertIsArray($capturedEnvironment);
            $this->assertFalse($capturedEnvironment['CT_HANDSHAKE_TEST_SECRET']);
            $this->assertFalse($capturedEnvironment['CT_HANDSHAKE_SERVER_SECRET']);
            $this->assertArrayNotHasKey('PATH', $capturedEnvironment);
        } finally {
            unset($_ENV['CT_HANDSHAKE_TEST_SECRET'], $_SERVER['CT_HANDSHAKE_SERVER_SECRET']);
        }
    }

    public function test_command_rejects_production_before_starting_a_process(): void
    {
        $originalContainer = Container::getInstance();
        $application = $this->createMock(Application::class);
        $application->expects($this->once())->method('environment')->with(['local', 'testing'])->willReturn(false);
        $started = false;
        $connection = new CapesterConnection(function () use (&$started): Process {
            $started = true;
            throw new \RuntimeException('Must not run.');
        });
        $command = $this->getMockBuilder(CapesterPing::class)->onlyMethods(['error'])->getMock();
        $command->expects($this->once())->method('error')->with('This connection check is available only in local or testing environments.');
        Container::setInstance($application);
        try {
            $this->assertSame(1, $command->handle($connection));
            $this->assertFalse($started);
        } finally {
            Container::setInstance($originalContainer);
        }
    }

    public function test_tool_use_errors_incomplete_and_malformed_output_are_rejected(): void
    {
        foreach (['command_execution', 'file_change', 'mcp_tool_call', 'web_search'] as $type) {
            $tool = json_encode(['type' => 'item.started', 'item' => ['type' => $type]])."\n";
            $this->assertFalse($this->connection($tool.$this->reply())->ping());
        }
        foreach (['', 'CAPESTER_PONG', '{invalid',
            json_encode(['type' => 'error', 'message' => 'private diagnostic']),
            json_encode(['type' => 'turn.completed']),
            str_replace('CAPESTER_PONG', 'CAPESTER_PONG extra', $this->reply()),
            str_replace('turn.completed', 'turn.failed', $this->reply()),
            $this->reply()."\n".json_encode(['type' => 'turn.started']),
            str_repeat('a', 65537),
        ] as $output) {
            $this->assertFalse($this->connection($output)->ping());
        }
        $this->assertFalse($this->connection($this->reply(), false)->ping());
    }

    public function test_process_failures_are_generic_and_temporary_directory_is_removed(): void
    {
        $directory = null;
        $connection = new CapesterConnection(function (array $arguments, string $cwd) use (&$directory): Process {
            $directory = $cwd;
            throw new \RuntimeException('private diagnostic');
        });
        $this->assertFalse($connection->ping());
        $this->assertDirectoryDoesNotExist($directory);
    }

    private function connection(string $output, bool $success = true, ?\Closure $inspect = null): CapesterConnection
    {
        return new CapesterConnection(function (array $arguments, string $cwd) use ($output, $success, $inspect): Process {
            if ($inspect) {
                $inspect($arguments, $cwd);
            }
            $process = $this->createMock(Process::class);
            $process->expects($this->once())->method('setTimeout')->with(60)->willReturnSelf();
            $process->method('run')->willReturnCallback(function (callable $callback) use ($output): int {
                $callback(Process::ERR, 'private provider diagnostic');
                $callback(Process::OUT, $output);
                return 0;
            });
            $process->method('isSuccessful')->willReturn($success);
            return $process;
        });
    }

    private function reply(): string
    {
        return implode("\n", array_map(fn ($event) => json_encode($event), [
            ['type' => 'thread.started', 'thread_id' => 'fictional'],
            ['type' => 'turn.started'],
            ['type' => 'item.completed', 'item' => ['type' => 'agent_message', 'text' => 'CAPESTER_PONG']],
            ['type' => 'turn.completed', 'usage' => []],
        ]));
    }
}
