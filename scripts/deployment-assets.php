<?php

declare(strict_types=1);

// Standalone: asset publication must not bootstrap Laravel or depend on rsync.
set_error_handler(static function (int $severity, string $message): never {
    throw new RuntimeException($message);
});

try {
    if ($argc !== 5) {
        throw new RuntimeException('Usage: deployment-assets.php SOURCE DESTINATION FOLDERS FILES');
    }

    $source = realpath($argv[1]);
    if ($source === false || ! is_dir($source)) {
        throw new RuntimeException('Public asset source directory is missing');
    }
    $destination = $argv[2];
    if (! is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    $destination = realpath($destination);
    if ($destination === false || $source === $destination || str_starts_with($destination, $source.DIRECTORY_SEPARATOR) || str_starts_with($source, $destination.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Public asset destination must be a separate directory');
    }

    $checked = $copied = $unchanged = $missing = 0;
    $started = $lastProgress = microtime(true);
    $progress = static function () use (&$checked, &$copied, &$unchanged, &$missing, &$lastProgress, $started): void {
        printf("Assets: %d checked, %d copied, %d unchanged, %d optional paths missing (%.1fs)\n", $checked, $copied, $unchanged, $missing, microtime(true) - $started);
        $lastProgress = microtime(true);
        flush();
    };
    $sync = function (string $relative) use (&$sync, $source, $destination, &$checked, &$copied, &$unchanged, &$missing, &$lastProgress, $progress): void {
        if ($relative === '' || preg_match('~(^/|^[A-Za-z]:|\\\\|(^|/)\.\.?(/|$))~', $relative)) {
            throw new RuntimeException('Invalid asset path: '.$relative);
        }
        $from = $source.'/'.$relative;
        $to = $destination.'/'.$relative;
        if (is_link($from) || is_link($to)) {
            throw new RuntimeException('Symlink asset paths are unsupported: '.$relative);
        }
        if (! file_exists($from)) {
            $missing++;
            echo 'Skipping missing optional asset: '.$relative."\n";

            return;
        }
        if (is_dir($from)) {
            echo 'Scanning assets: '.$relative."\n";
            flush();
            if (! is_dir($to)) {
                mkdir($to, 0755);
            }
            foreach (new DirectoryIterator($from) as $entry) {
                if (! $entry->isDot()) {
                    $sync($relative.'/'.$entry->getFilename());
                }
            }

            return;
        }
        if (! is_file($from)) {
            throw new RuntimeException('Unsupported asset type: '.$relative);
        }
        $checked++;
        if (is_file($to) && filesize($from) === filesize($to) && hash_file('sha256', $from) === hash_file('sha256', $to)) {
            $unchanged++;
        } else {
            echo 'Copying asset: '.$relative."\n";
            flush();
            // Stage beside the destination so requests never read partial assets.
            $temporary = tempnam(dirname($to), '.asset-deploy-');
            try {
                copy($from, $temporary);
                chmod($temporary, fileperms($from) & 0777);
                touch($temporary, filemtime($from));
                rename($temporary, $to);
                $copied++;
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        if ($checked % 100 === 0 || microtime(true) - $lastProgress >= 2) {
            $progress();
        }
    };

    echo "Starting incremental public asset sync (content comparison)\n";
    flush();
    foreach (preg_split('/\s+/', trim($argv[3].' '.$argv[4]), -1, PREG_SPLIT_NO_EMPTY) as $path) {
        // Configuration selects top-level public paths; recursion checks each child.
        if (str_contains($path, '/') || str_contains($path, '\\')) {
            throw new RuntimeException('Invalid asset path: '.$path);
        }
        $sync($path);
    }
    $progress();
    echo "Public asset sync complete\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Asset sync failed: '.$exception->getMessage()."\n");
    exit(1);
}
