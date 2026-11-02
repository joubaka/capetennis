<?php

namespace App\Console\Commands;

use App\Models\{Event, File};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** Restore absent local copies; never edit event records or fetch private documents. */
class RestorePublicEventDocuments extends Command
{
    protected $signature = 'events:restore-public-documents {--event= : Limit to one event ID} {--apply : Save verified missing files locally}';

    protected $description = 'Review or restore missing local public event attachments from the canonical public site';

    private const ORIGIN = 'https://www.capetennis.co.za';
    private const MAX_DOCUMENTS = 152;
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function handle(): int
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (!app()->environment(['local', 'testing']) || !in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            $this->error('Document restoration is restricted to a local application and local APP_URL.');
            return self::FAILURE;
        }
        $event = $this->option('event');
        if ($event !== null && (!ctype_digit((string) $event) || (int) $event < 1)) {
            $this->error('Choose a positive event ID.');
            return self::FAILURE;
        }
        $documents = File::query()->whereIn('event_id', Event::query()->visibleTo(null)->select('id'))
            ->when($event !== null, fn ($query) => $query->where('event_id', (int) $event))
            ->orderBy('id')->limit(self::MAX_DOCUMENTS + 1)->get(['id', 'event_id', 'name', 'path']);
        if ($documents->count() > self::MAX_DOCUMENTS) {
            $this->error('More than 152 public documents were found. Limit the review with --event.');
            return self::FAILURE;
        }
        $counts = ['existing' => 0, 'ready' => 0, 'restored' => 0, 'failed' => 0];
        $rows = [];
        foreach ($documents as $document) {
            $label = 'Event '.$document->event_id.' / document '.$document->id;
            $path = (string) $document->path;
            // Only known modern and legacy Cape Tennis attachment layouts.
            // The model never rebases an arbitrary absolute basename.
            $destination = $document->portableAttachmentPath();
            $legacy = $document->legacyAttachmentFilename() !== null;
            if ($destination === null) {
                $rows[] = [$label, 'Failed: unsupported attachment path'];
                $counts['failed']++;
                continue;
            }
            if ($document->resolvedLocalPath() !== null || file_exists($destination) || is_link($destination)) {
                $rows[] = [$label, 'Existing; left unchanged'];
                $counts['existing']++;
                continue;
            }
            $url = self::ORIGIN.'/events/'.(int) $document->event_id.'/documents/'.(int) $document->id;
            try {
                // No user credentials, cookies, arbitrary origin, or redirects.
                // The source route independently enforces remote publication.
                $response = Http::connectTimeout(10)->timeout(20)->withOptions([
                    'allow_redirects' => false, 'stream' => true, 'cookies' => false,
                ])->get($url);
                if ($response->status() !== 200) throw new \RuntimeException('Source returned HTTP '.$response->status());
                $disposition = (string) $response->header('Content-Disposition');
                if ($disposition !== '') {
                    $parameters = HeaderUtils::combine(HeaderUtils::split($disposition, ';='));
                    $filename = $parameters['filename'] ?? null;
                    if (isset($parameters['filename*'])) {
                        $extended = explode("'", (string) $parameters['filename*'], 3);
                        if (count($extended) !== 3 || strtolower($extended[0]) !== 'utf-8') throw new \RuntimeException('Source filename encoding is unsupported');
                        $filename = rawurldecode($extended[2]);
                    }
                    if (!is_string($filename) || !$document->matchesSourceFilename($filename)) {
                        throw new \RuntimeException('Source filename does not match the local attachment');
                    }
                } else {
                    throw new \RuntimeException('Source filename is missing');
                }
                if ((int) $response->header('Content-Length') > self::MAX_BYTES) throw new \RuntimeException('Source exceeds the 5 MiB limit');
                $stream = $response->toPsrResponse()->getBody();
                $contents = '';
                try {
                    while (!$stream->eof()) {
                        $contents .= $stream->read(min(65536, self::MAX_BYTES + 1 - strlen($contents)));
                        if (strlen($contents) > self::MAX_BYTES) throw new \RuntimeException('Source exceeds the 5 MiB limit');
                    }
                } finally {
                    $stream->close();
                }
                if (!$this->validContents($destination, (string) $response->header('Content-Type'), $contents)) {
                    throw new \RuntimeException('Source is empty, HTML, or does not match the attachment format');
                }
                if ($this->option('apply')) {
                    if (!Event::query()->visibleTo(null)->whereKey($document->event_id)->exists()
                        || !File::query()->whereKey($document->id)->where('event_id', $document->event_id)->where('path', $path)->where('name', $document->name)->exists()) {
                        throw new \RuntimeException('Source publication or attachment record changed during download');
                    }
                    $this->saveExclusive($destination, $contents, $legacy);
                    $counts['restored']++;
                    $rows[] = [$label, 'Restored ('.strlen($contents).' bytes)'];
                } else {
                    $counts['ready']++;
                    $rows[] = [$label, 'Ready; dry run ('.strlen($contents).' bytes)'];
                }
            } catch (\Throwable $error) {
                // Do not print transport errors containing environment paths,
                // proxy details or other connection configuration.
                $reason = $error instanceof \RuntimeException && str_starts_with($error->getMessage(), 'Source ')
                    ? $error->getMessage() : 'Source download or local save failed';
                $rows[] = [$label, 'Failed: '.$reason];
                $counts['failed']++;
            }
        }
        $this->table(['Attachment', 'Result'], $rows);
        $this->line('Existing: '.$counts['existing'].'; ready: '.$counts['ready'].'; restored: '.$counts['restored'].'; failed: '.$counts['failed'].'.');
        if (!$this->option('apply')) $this->info('Dry run only. Use --apply to save verified missing local copies.');
        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }

    private function validContents(string $path, string $type, string $contents): bool
    {
        if ($contents === '' || str_contains(strtolower($type), 'text/html')
            || preg_match('/\A\s*(?:<!doctype\s+html|<html\b)/i', $contents)) return false;
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => str_starts_with($contents, '%PDF-'),
            'doc', 'xls' => str_starts_with($contents, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'docx', 'xlsx' => str_starts_with($contents, "PK\x03\x04"),
            'csv' => !str_contains($contents, "\0") && preg_match('//u', $contents) === 1,
            default => false,
        };
    }

    private function saveExclusive(string $destination, string $contents, bool $legacy = false): void
    {
        $directory = dirname($destination);
        $root = realpath(storage_path('app'));
        $ancestor = $directory;
        while (!file_exists($ancestor) && dirname($ancestor) !== $ancestor) $ancestor = dirname($ancestor);
        $existing = realpath($ancestor);
        if ($root === false || $existing === false || !$this->isWithin($existing, $root)) {
            throw new \RuntimeException('Local destination is outside attachment storage');
        }
        $this->checkAttachmentDirectory($root, $legacy);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Local directory creation failed');
        }
        $resolved = realpath($directory);
        if ($resolved === false || !$this->isWithin($resolved, $root)) {
            throw new \RuntimeException('Local destination is outside attachment storage');
        }
        $this->checkAttachmentDirectory($root, $legacy);
        $handle = @fopen($destination, 'xb');
        if ($handle === false) throw new \RuntimeException('Local destination already exists or cannot be created');
        try {
            $written = fwrite($handle, $contents);
            if ($written !== strlen($contents)) throw new \RuntimeException('Local document write was incomplete');
        } catch (\Throwable $error) {
            fclose($handle);
            unlink($destination); // Only the new file created exclusively above.
            throw $error;
        }
        fclose($handle);
    }

    private function isWithin(string $path, string $root): bool
    {
        return str_starts_with(str_replace('\\', '/', $path).'/', rtrim(str_replace('\\', '/', $root), '/').'/');
    }

    private function checkAttachmentDirectory(string $root, bool $legacy = false): void
    {
        foreach ($legacy ? ['public', 'public/files', 'public/files/legacy'] : ['public', 'public/files'] as $component) {
            $directory = storage_path('app/'.$component);
            if (!file_exists($directory) && !is_link($directory)) continue;
            $resolved = realpath($directory);
            $expected = str_replace('\\', '/', $root).'/'.$component;
            $actual = $resolved === false ? '' : str_replace('\\', '/', $resolved);
            if (PHP_OS_FAMILY === 'Windows') { $actual = strtolower($actual); $expected = strtolower($expected); }
            if (is_link($directory) || $actual !== $expected) throw new \RuntimeException('Local attachment directory is redirected');
        }
    }
}
