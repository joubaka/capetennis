<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\CapesterConnection;
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
            $connection = new CapesterConnection(function (array $arguments, string $cwd, array $environment): Process {
                $this->assertFalse($environment['CT_HANDSHAKE_TEST_SECRET']);
                $this->assertFalse($environment['CT_HANDSHAKE_SERVER_SECRET']);
                $this->assertArrayNotHasKey('PATH', $environment);
                throw new \RuntimeException('Stop after inspecting environment.');
            });
            $this->assertFalse($connection->ping());
        } finally {
            unset($_ENV['CT_HANDSHAKE_TEST_SECRET'], $_SERVER['CT_HANDSHAKE_SERVER_SECRET']);
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
