<?php

namespace Tests\Unit;

use App\Console\Commands\CacheDeploymentViews;
use Illuminate\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\ViewServiceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class IncrementalViewCacheTest extends TestCase
{
    public function test_warmup_retains_unchanged_files_replaces_changed_and_cannot_render_deleted_source(): void
    {
        $root = sys_get_temp_dir().'/ct-incremental-views-'.bin2hex(random_bytes(6));
        mkdir($root.'/views', 0777, true);
        mkdir($root.'/compiled', 0777, true);
        $previous = Application::getInstance();
        $app = new Application($root);
        $app->instance('config', new Repository(['view' => ['paths' => [$root.'/views'], 'compiled' => $root.'/compiled']]));
        $app->instance('files', new Filesystem);
        $app->instance('events', new Dispatcher($app));
        Facade::setFacadeApplication($app);
        $app->register(ViewServiceProvider::class);
        try {
            $source = $root.'/views/example.blade.php';
            file_put_contents($source, 'Old {{ $name }}');
            $source = realpath($source);
            $command = new CacheDeploymentViews;
            $command->setLaravel($app);
            $tester = new CommandTester($command);
            $this->assertSame(0, $tester->execute([]));
            $compiled = $app['blade.compiler']->getCompiledPath($source);
            $this->assertFileExists($compiled);
            // An unchanged warmup must preserve the compiled file and timestamp.
            touch($compiled, time() + 60);
            clearstatcache(true, $compiled);
            $before = stat($compiled);
            $this->assertSame(0, $tester->execute([]));
            clearstatcache(true, $compiled);
            $after = stat($compiled);
            $this->assertSame($before['mtime'], $after['mtime']);
            $this->assertSame($before['ino'], $after['ino']);
            file_put_contents($source, 'New {{ $name }}');
            $this->assertSame(0, $tester->execute([]));
            $this->assertStringContainsString('New ', file_get_contents($compiled));
            $this->assertSame('New Player', $app['view']->make('example', ['name' => 'Player'])->render());
            $lastGood = file_get_contents($compiled);
            $app['blade.compiler']->directive('broken', fn () => throw new \RuntimeException('Compilation failed'));
            file_put_contents($source, '@broken');
            try {
                $tester->execute([]);
                $this->fail('Compilation failures must stop the warmup.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Compilation failed', $exception->getMessage());
            }
            $this->assertSame($lastGood, file_get_contents($compiled));
            unlink($source);
            $this->assertSame(0, $tester->execute([]));
            $app['view']->getFinder()->flush();
            $this->expectException(\InvalidArgumentException::class);
            $app['view']->make('example', ['name' => 'Player'])->render();
        } finally {
            (new Filesystem)->deleteDirectory($root);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($previous);
            Application::setInstance($previous);
        }
    }
}
