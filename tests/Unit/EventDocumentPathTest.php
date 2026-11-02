<?php

namespace Tests\Unit;

use App\Console\Commands\RestorePublicEventDocuments;
use App\Models\File;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

class EventDocumentPathTest extends TestCase
{
    private Container $previous;
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = Container::getInstance();
        $this->directory = sys_get_temp_dir().'/ct-document-path-'.uniqid();
        $app = new Application(dirname(__DIR__, 2));
        $app->useStoragePath($this->directory);
        mkdir($this->directory.'/app', 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        Container::setInstance($this->previous);
        parent::tearDown();
    }

    private function document(string $path, string $name = 'Overberg Rules.pdf'): File
    {
        return (new File)->forceFill(['name' => $name, 'path' => $path]);
    }

    private function legacy(string $filename = 'Overberg Rules.pdf.pdf'): string
    {
        return '/home/batawrry/domains/capetennis.co.za/public_html/storage/'.$filename;
    }

    public function test_legacy_host_paths_resolve_existing_current_storage_files_without_rewriting_records(): void
    {
        $path = storage_path('Overberg Rules.pdf.pdf');
        file_put_contents($path, '%PDF-1.4 synthetic legacy document');
        $file = $this->document($this->legacy());
        $this->assertSame($path, $file->resolvedLocalPath());
        $this->assertTrue($file->isAvailable());
        $this->assertSame($this->legacy(), $file->path);
        $this->assertTrue($file->matchesSourceFilename('Overberg Rules.pdf.pdf'));
        $this->assertFalse($file->matchesSourceFilename('Overberg Rules.pdf'));
        $this->assertFalse($file->matchesSourceFilename('Different.pdf.pdf'));
        $this->assertTrue($this->document($this->legacy('Overberg Rules.pdf'))->matchesSourceFilename('Overberg Rules.pdf'));
        $this->assertFalse($this->document($this->legacy('Overberg Rules.pdf.pdf.pdf'))->matchesSourceFilename('Overberg Rules.pdf.pdf.pdf'));
    }

    public function test_missing_legacy_documents_use_only_the_explicit_ignored_restoration_cache(): void
    {
        $file = $this->document($this->legacy());
        $expected = storage_path('app/public/files/legacy/Overberg Rules.pdf.pdf');
        $this->assertSame($expected, $file->portableAttachmentPath());
        $this->assertNull($file->resolvedLocalPath());
        mkdir(dirname($expected), 0755, true);
        file_put_contents($expected, '%PDF-1.4 restored document');
        $this->assertSame($expected, $file->resolvedLocalPath());
        $this->assertTrue($file->isAvailable());
    }

    public function test_unrelated_domains_accounts_traversal_and_stream_paths_never_get_a_basename_fallback(): void
    {
        file_put_contents(storage_path('Overberg Rules.pdf.pdf'), 'Existing same-named file');
        foreach ([
            '/home/account/domains/other.example/public_html/storage/Overberg Rules.pdf.pdf',
            '/home/other_account/domains/capetennis.co.za/public_html/storage/Overberg Rules.pdf.pdf',
            '/tmp/Overberg Rules.pdf.pdf',
            '/home/../domains/capetennis.co.za/public_html/storage/Overberg Rules.pdf.pdf',
            $this->legacy('../Overberg Rules.pdf.pdf'),
            $this->legacy('nested/Overberg Rules.pdf.pdf'),
            $this->legacy('back\\slash.pdf'),
            $this->legacy('file.pdf:stream'),
            'public/files/../Overberg Rules.pdf.pdf',
            '../../Overberg Rules.pdf.pdf',
        ] as $path) {
            $file = $this->document($path);
            $this->assertNull($file->portableAttachmentPath(), $path);
            $this->assertNull($file->resolvedLocalPath(), $path);
        }
    }

    public function test_existing_absolute_paths_and_modern_document_paths_remain_supported(): void
    {
        $absolute = storage_path('absolute-fixture.pdf');
        file_put_contents($absolute, '%PDF-1.4 fixture');
        $this->assertSame($absolute, $this->document($absolute)->resolvedLocalPath());
        $this->assertNull($this->document($absolute)->portableAttachmentPath());
        $modern = $this->document('public/files/Programme.pdf', 'Programme.pdf');
        $expected = storage_path('app/public/files/Programme.pdf');
        mkdir(dirname($expected), 0755, true);
        file_put_contents($expected, '%PDF-1.4 modern fixture');
        $this->assertSame($expected, $modern->resolvedLocalPath());
        $this->assertTrue($modern->matchesSourceFilename('Programme.pdf'));
        $this->assertFalse($modern->matchesSourceFilename('Programme.pdf.pdf'));
    }

    public function test_legacy_cache_writer_remains_exclusive_and_does_not_fill_the_tracked_storage_root(): void
    {
        $file = $this->document($this->legacy());
        $destination = $file->portableAttachmentPath();
        $writer = new \ReflectionMethod(RestorePublicEventDocuments::class, 'saveExclusive');
        $command = new RestorePublicEventDocuments;
        $writer->invoke($command, $destination, '%PDF-1.4 verified legacy copy', true);
        $this->assertSame($destination, $file->resolvedLocalPath());
        $this->assertFileDoesNotExist(storage_path('Overberg Rules.pdf.pdf'));
        try {
            $writer->invoke($command, $destination, 'replacement', true);
            $this->fail('Existing attachment must not be overwritten.');
        } catch (\RuntimeException) {
            $this->assertSame('%PDF-1.4 verified legacy copy', file_get_contents($destination));
        }
    }
}
