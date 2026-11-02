<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CapesterReceiver
{
    public function __construct(private readonly CapesterConnection $connection) {}

    public function receive(): string
    {
        if (! app()->environment(['local', 'testing'])) {
            return 'failed';
        }
        try {
            $base = $this->baseUrl((string) config('capester.receiver.base_url'));
            $token = (string) config('capester.receiver.token');
            if ($token === '' || strlen($token) > 4096 || preg_match('/[\r\n]/', $token)) {
                return 'failed';
            }
            $http = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects' => false, 'verify' => true, 'stream' => true, 'read_timeout' => 5]);
            $response = $http->post($base.'/api/v1/capester/claim');
            if (! $response->successful()) {
                return 'failed';
            }
            $payload = $this->boundedJson($response);
            if (! is_array($payload) || array_keys($payload) !== ['request']) {
                return 'failed';
            }
            if ($payload['request'] === null) {
                return 'idle';
            }
            $item = $this->validateItem($payload['request']);
            if ($item['kind'] === 'hello') {
                $result = $this->connection->ping() ? 'hello_passed' : 'hello_failed';
            } else {
                $this->store($base, $item);
                $result = 'received';
            }
            $ack = $http->post($base.'/api/v1/capester/'.$item['uuid'].'/ack', ['token' => $item['token'], 'result' => $result]);
            return $ack->successful() && $this->boundedJson($ack) === ['acknowledged' => true] ? $result : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function boundedJson(\Illuminate\Http\Client\Response $response): mixed
    {
        $stream = $response->toPsrResponse()->getBody();
        $body = '';
        $deadline = hrtime(true) + 15_000_000_000;
        try {
            while (! $stream->eof() && strlen($body) <= 16384) {
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException('Response read timed out.');
                }
                $chunk = $stream->read(16385 - strlen($body));
                if ($chunk === '' || hrtime(true) >= $deadline) {
                    throw new RuntimeException('Incomplete response.');
                }
                $body .= $chunk;
            }
            if (strlen($body) > 16384) {
                throw new RuntimeException('Response too large.');
            }
            return json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } finally {
            $stream->close();
        }
    }

    private function baseUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\\\\]/', $url)) {
            throw new RuntimeException('Invalid receiver origin.');
        }
        $loopback = in_array(strtolower($parts['host']), ['127.0.0.1', 'localhost', '[::1]', '::1'], true);
        if ($parts['scheme'] !== 'https' && ! ($parts['scheme'] === 'http' && $loopback)) {
            throw new RuntimeException('HTTPS required.');
        }
        return rtrim($url, '/');
    }

    private function validateItem(mixed $item): array
    {
        if (! is_array($item)) {
            throw new RuntimeException('Invalid claim.');
        }
        $keys = array_keys($item);
        sort($keys);
        if ($keys !== ['capester_description', 'event_id', 'kind', 'token', 'uuid']
            || ! is_string($item['uuid']) || ! Str::isUuid($item['uuid']) || strtolower($item['uuid']) !== $item['uuid']
            || ! in_array($item['kind'], ['support', 'hello'], true)
            || ! is_int($item['event_id']) || $item['event_id'] < 1
            || ! is_string($item['capester_description']) || trim($item['capester_description']) === '' || mb_strlen($item['capester_description']) > 2000
            || ! is_string($item['token']) || ! preg_match('/^[a-f0-9]{64}$/D', $item['token'])) {
            throw new RuntimeException('Invalid claim.');
        }
        return $item;
    }

    private function store(string $origin, array $item): void
    {
        $root = storage_path('app/private');
        foreach ([$root, $root.'/capester', $root.'/capester/inbox', $root.'/capester/inbox/'.hash('sha256', $origin)] as $directory) {
            if (is_link($directory) || (! is_dir($directory) && ! @mkdir($directory, 0700, true))) {
                throw new RuntimeException('Inbox unavailable.');
            }
            @chmod($directory, 0700);
        }
        $path = $directory.'/'.$item['uuid'].'.json';
        $content = json_encode(['uuid' => $item['uuid'], 'kind' => $item['kind'], 'event_id' => $item['event_id'],
            'capester_description' => $item['capester_description']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        $lockPath = $path.'.lock';
        if (is_link($lockPath) || is_link($path)) {
            throw new RuntimeException('Unsafe inbox file.');
        }
        $lock = @fopen($lockPath, 'c');
        if (! $lock) {
            throw new RuntimeException('Inbox unavailable.');
        }
        @chmod($lockPath, 0600);
        $temporary = null;
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Inbox locked.');
            }
            if (is_link($path)) {
                throw new RuntimeException('Unsafe inbox file.');
            }
            if (file_exists($path)) {
                if (! is_file($path) || file_get_contents($path, false, null, 0, 16385) !== $content) {
                    throw new RuntimeException('Inbox record differs.');
                }
                return;
            }
            $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
            $handle = @fopen($temporary, 'x');
            if (! $handle) {
                throw new RuntimeException('Inbox unavailable.');
            }
            try {
                @chmod($temporary, 0600);
                if (fwrite($handle, $content) !== strlen($content) || ! fflush($handle) || (function_exists('fsync') && ! fsync($handle))) {
                    throw new RuntimeException('Inbox write failed.');
                }
            } finally {
                fclose($handle);
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Inbox write failed.');
            }
            $temporary = null;
        } finally {
            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
