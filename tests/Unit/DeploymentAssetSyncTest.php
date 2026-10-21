<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentAssetSyncTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ct-assets-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/source/assets/nested', 0777, true);
        mkdir($this->root.'/destination/assets/nested', 0777, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function test_copies_only_changed_contents_even_with_identical_size_and_older_timestamp(): void
    {
        file_put_contents($this->root.'/source/assets/nested/changed.js', 'new');
        file_put_contents($this->root.'/destination/assets/nested/changed.js', 'old');
        touch($this->root.'/source/assets/nested/changed.js', 1000000000);
        file_put_contents($this->root.'/source/assets/same.js', 'same');
        file_put_contents($this->root.'/destination/assets/same.js', 'same');
        touch($this->root.'/destination/assets/same.js', 1100000000);
        file_put_contents($this->root.'/source/root.js', 'root');
        file_put_contents($this->root.'/destination/assets/retained.js', 'retained');

        [$status, $output, $error] = $this->runSync('assets', 'root.js missing.js');

        $this->assertSame(0, $status, $error);
        $this->assertSame('new', file_get_contents($this->root.'/destination/assets/nested/changed.js'));
        $this->assertSame('root', file_get_contents($this->root.'/destination/root.js'));
        clearstatcache();
        $this->assertSame(1100000000, filemtime($this->root.'/destination/assets/same.js'));
        $this->assertFileExists($this->root.'/destination/assets/retained.js');
        $this->assertStringContainsString('3 checked, 2 copied, 1 unchanged, 1 optional paths missing', $output);
        $this->assertStringContainsString('Scanning assets: assets', $output);
        $this->assertStringContainsString('Copying asset: root.js', $output);
        $this->assertStringContainsString('Public asset sync complete', $output);
        $this->assertSame([], glob($this->root.'/destination/.asset-deploy-*'));

        [$status, $output, $error] = $this->runSync('assets', 'root.js');
        $this->assertSame(0, $status, $error);
        $this->assertStringContainsString('3 checked, 0 copied, 3 unchanged', $output);
        $this->assertStringNotContainsString('Copying asset:', $output);
    }

    public function test_missing_optional_paths_and_empty_lists_succeed(): void
    {
        foreach ([['absent', 'missing.js'], ['', '']] as [$folders, $files]) {
            [$status, $output, $error] = $this->runSync($folders, $files);
            $this->assertSame(0, $status, $error);
            $this->assertStringContainsString('0 checked, 0 copied', $output);
            $this->assertStringContainsString('Public asset sync complete', $output);
        }
    }

    public function test_copy_failure_is_reported_and_temporary_file_is_removed(): void
    {
        file_put_contents($this->root.'/source/root.js', 'new');
        mkdir($this->root.'/destination/root.js');

        [$status, $output, $error] = $this->runSync('', 'root.js');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Asset sync failed:', $error);
        $this->assertStringNotContainsString('Public asset sync complete', $output);
        $this->assertDirectoryExists($this->root.'/destination/root.js');
        $this->assertSame([], glob($this->root.'/destination/.asset-deploy-*'));
    }

    public function test_invalid_path_cannot_escape_asset_roots(): void
    {
        [$status, $output, $error] = $this->runSync('../outside', '');
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Invalid asset path', $error);
        $this->assertStringNotContainsString('Public asset sync complete', $output);
    }

    private function runSync(string $folders, string $files): array
    {
        $process = proc_open([
            PHP_BINARY, '-d', 'display_errors=Off', dirname(__DIR__, 2).'/scripts/deployment-assets.php',
            $this->root.'/source', $this->root.'/destination', $folders, $files,
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output, $error];
    }
}
