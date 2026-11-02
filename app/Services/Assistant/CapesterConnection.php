<?php

namespace App\Services\Assistant;

use Closure;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** Local fixed-message connectivity check. No user instruction or application data is sent. */
final class CapesterConnection
{
    public const PROMPT = 'Reply exactly CAPESTER_PONG. Do not use tools, read files, or execute commands.';

    public function __construct(private readonly ?Closure $processFactory = null) {}

    public function ping(): bool
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ct-capester-'.bin2hex(random_bytes(16));
        if (! @mkdir($directory, 0700)) {
            return false;
        }
        $process = null;
        try {
            $arguments = ['codex', 'exec', '--ignore-user-config', '--sandbox', 'read-only', '--ephemeral',
                '-c', 'features.shell_tool=false', '--skip-git-repo-check', '--json', '-C', $directory, self::PROMPT];
            $environment = $this->childEnvironment();
            $process = $this->processFactory !== null
                ? ($this->processFactory)($arguments, $directory, $environment)
                : new Process($arguments, $directory, $environment, null, 60);
            $process->setTimeout(60);
            $output = '';
            $process->run(function (string $type, string $chunk) use (&$output, $process): void {
                if ($type === Process::OUT) {
                    $output .= $chunk;
                    if (strlen($output) > 65536) {
                        throw new RuntimeException('Handshake output exceeded its limit.');
                    }
                }
                // Provider diagnostics may contain sensitive context; never retain or display them.
                $process->clearOutput();
                $process->clearErrorOutput();
            });
            return $process->isSuccessful() && $this->validResponse($output);
        } catch (Throwable) {
            if ($process instanceof Process) {
                $process->stop(0);
            }
            return false;
        } finally {
            // Never recursively remove unexpected files. A tool-using run fails the handshake.
            @rmdir($directory);
        }
    }

    private function childEnvironment(): array
    {
        $allowed = ['PATH', 'PATHEXT', 'SYSTEMROOT', 'WINDIR', 'COMSPEC', 'TEMP', 'TMP', 'TMPDIR',
            'HOME', 'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH', 'APPDATA', 'LOCALAPPDATA', 'CODEX_HOME',
            'PROGRAMFILES', 'PROGRAMFILES(X86)', 'PROGRAMW6432', 'LANG', 'LC_ALL'];
        $environment = [];
        // Symfony otherwise inherits getenv and Dotenv's $_ENV values. False explicitly removes a key.
        foreach (array_unique(array_merge(array_keys(getenv()), array_keys($_ENV), array_keys($_SERVER))) as $key) {
            if (! in_array(strtoupper((string) $key), $allowed, true)) {
                $environment[$key] = false;
            }
        }
        return $environment;
    }

    private function validResponse(string $output): bool
    {
        $completed = false;
        $messages = 0;
        try {
            foreach (preg_split('/\r?\n/', trim($output)) as $line) {
                $event = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
                if (! is_array($event) || $completed || ! is_string($event['type'] ?? null)) {
                    return false;
                }
                switch ($event['type']) {
                    case 'thread.started':
                    case 'turn.started':
                        break;
                    case 'item.started':
                    case 'item.updated':
                    case 'item.completed':
                        $item = $event['item'] ?? null;
                        if (! is_array($item) || ! in_array($item['type'] ?? null, ['reasoning', 'agent_message'], true)) {
                            return false;
                        }
                        if ($item['type'] === 'agent_message' && $event['type'] === 'item.completed') {
                            if (($item['text'] ?? null) !== 'CAPESTER_PONG' || ++$messages !== 1) {
                                return false;
                            }
                        }
                        break;
                    case 'turn.completed':
                        $completed = $messages === 1;
                        if (! $completed) {
                            return false;
                        }
                        break;
                    default:
                        return false;
                }
            }
        } catch (JsonException) {
            return false;
        }
        return $completed && $messages === 1;
    }
}
