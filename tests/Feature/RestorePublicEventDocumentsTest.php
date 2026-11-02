<?php

namespace Tests\Feature;

use App\Models\{Event, File};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File as Filesystem, Http};
use Tests\TestCase;

class RestorePublicEventDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStorage;
    private string $directory;
    private const PDF = "%PDF-1.4\nSynthetic public event attachment\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost/ct/public']);
        Http::preventStrayRequests();
        $this->originalStorage = storage_path();
        $this->directory = storage_path('framework/testing/document-restoration-'.uniqid());
        Filesystem::ensureDirectoryExists($this->directory.'/app');
        $this->app->useStoragePath($this->directory);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        Filesystem::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function document(Event $event, string $name = 'Programme.pdf', ?string $path = null): File
    {
        return File::forceCreate(['event_id' => $event->id, 'name' => $name, 'path' => $path ?? 'public/files/'.$name]);
    }

    private function url(File $file): string
    {
        return 'https://www.capetennis.co.za/events/'.$file->event_id.'/documents/'.$file->id;
    }

    public function test_dry_run_apply_and_repeat_preserve_records_existing_files_and_public_document_route(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $document = $this->document($event, 'Trial programme 2026.pdf');
        $original = $document->fresh()->getAttributes();
        Http::fake(function ($request) use ($document) {
            $this->assertSame($this->url($document), $request->url());
            // Each real download owns a fresh stream, including a subsequent
            // apply after a dry run has closed its verified response body.
            return Http::response(self::PDF, 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Trial programme 2026.pdf"',
            ]);
        });
        $this->artisan('events:restore-public-documents', ['--event' => $event->id])
            ->expectsOutputToContain('ready: 1; restored: 0; failed: 0')->assertSuccessful();
        $this->assertFileDoesNotExist(storage_path('app/'.$document->path));
        $this->artisan('events:restore-public-documents', ['--event' => $event->id, '--apply' => true])
            ->expectsOutputToContain('restored: 1; failed: 0')->assertSuccessful();
        $this->assertSame(self::PDF, file_get_contents(storage_path('app/'.$document->path)));
        $this->assertSame($original, $document->fresh()->getAttributes());
        $this->assertDatabaseCount('files', 1);
        $this->get(route('events.documents.show', [$event, $document]))->assertOk()
            ->assertHeader('content-disposition', 'inline; filename="Trial programme 2026.pdf"');
        $html = view('frontend.event.partials.event-documents', ['event' => $event->load('files')])->render();
        $this->assertStringContainsString(e(route('events.documents.show', [$event, $document])), $html);
        $this->assertStringNotContainsString('Document unavailable', $html);
        Http::fake();
        $this->artisan('events:restore-public-documents', ['--apply' => true])
            ->expectsOutputToContain('Existing: 1; ready: 0; restored: 0; failed: 0')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame(self::PDF, file_get_contents(storage_path('app/'.$document->path)));
    }

    public function test_missing_public_document_returns_generic_error_without_storage_paths(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $document = $this->document($event);
        $this->get(route('events.documents.show', [$event, $document]))->assertNotFound()
            ->assertDontSee(storage_path(), false)->assertDontSee('File not found:', false);
        $html = view('frontend.event.partials.event-documents', ['event' => $event->load('files')])->render();
        $this->assertStringContainsString('Programme.pdf', $html);
        $this->assertStringContainsString('Document unavailable', $html);
        $this->assertStringNotContainsString(e(route('events.documents.show', [$event, $document])), $html);
    }

    public function test_private_foreign_and_unsafe_path_documents_are_never_fetched(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $private = Event::factory()->unpublished()->create();
        $foreign = Event::factory()->create(['published' => true]);
        $this->document($private, 'Private.pdf');
        $this->document($foreign, 'Foreign.pdf');
        foreach (['public/files/../outside.pdf', 'public/files/sub/file.pdf', 'public/files/file.pdf:stream', 'C:/outside.pdf', 'https://other.example/file.pdf', 'public/files/back\\slash.pdf'] as $path) {
            $this->document($event, 'Unsafe.pdf', $path);
        }
        Http::fake();
        $this->artisan('events:restore-public-documents', ['--event' => $event->id, '--apply' => true])
            ->expectsOutputToContain('restored: 0; failed: 6')->assertFailed();
        $this->artisan('events:restore-public-documents', ['--event' => $private->id, '--apply' => true])->assertSuccessful();
        Http::assertNothingSent();
        $this->assertDatabaseCount('files', 8);
    }

    public function test_remote_failures_redirects_html_wrong_filenames_and_oversized_bodies_are_reported_without_writes(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $cases = [
            ['404.pdf', '', 404, []],
            ['Redirect.pdf', '', 302, ['Location' => 'https://other.example/private.pdf']],
            ['Html.pdf', '<html>Login</html>', 200, ['Content-Type' => 'text/html']],
            ['Wrong.pdf', self::PDF, 200, ['Content-Disposition' => 'inline; filename="Different.pdf"']],
            ['Huge.pdf', self::PDF.str_repeat('x', 5 * 1024 * 1024), 200, ['Content-Type' => 'application/pdf']],
            ['HeaderHuge.pdf', self::PDF, 200, ['Content-Length' => 6 * 1024 * 1024]],
            ['MissingFilename.pdf', self::PDF, 200, ['Content-Disposition' => '']],
        ];
        $responses = [];
        foreach ($cases as [$name, $body, $status, $headers]) {
            $document = $this->document($event, $name);
            $responses[$this->url($document)] = Http::response($body, $status, $headers + ['Content-Disposition' => 'inline; filename="'.$name.'"']);
        }
        Http::fake(function ($request, $options) use ($responses) {
            $this->assertFalse($options['allow_redirects']);
            $this->assertFalse($options['cookies']);
            return $responses[$request->url()];
        });
        $this->artisan('events:restore-public-documents', ['--apply' => true])
            ->expectsOutputToContain('Source returned HTTP 404')->expectsOutputToContain('Source returned HTTP 302')
            ->expectsOutputToContain('restored: 0; failed: 7')->assertFailed();
        $this->assertDirectoryDoesNotExist(storage_path('app/public/files'));
        Http::assertSentCount(7);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://www.capetennis.co.za/events/')
            && !$request->hasHeader('Authorization') && !$request->hasHeader('Cookie'));
        $this->assertDatabaseCount('files', 7);
    }

    public function test_publication_changed_during_download_prevents_restoration(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $document = $this->document($event);
        Http::fake(function () use ($event) {
            $event->forceFill(['published' => false])->save();
            return Http::response(self::PDF, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Programme.pdf"']);
        });
        $this->artisan('events:restore-public-documents', ['--apply' => true])
            ->expectsOutputToContain('Source publication or attachment record changed')->assertFailed();
        $this->assertFileDoesNotExist(storage_path('app/'.$document->path));
    }

    public function test_production_nonlocal_url_invalid_scope_and_unbounded_runs_are_refused_before_network_access(): void
    {
        Http::fake();
        $this->app->instance('env', 'production');
        $this->artisan('events:restore-public-documents', ['--apply' => true])->assertFailed();
        $this->app->instance('env', 'testing');
        config(['app.url' => 'https://www.capetennis.co.za']);
        $this->artisan('events:restore-public-documents', ['--apply' => true])->assertFailed();
        config(['app.url' => 'http://localhost']);
        $this->artisan('events:restore-public-documents', ['--event' => '../1'])->assertFailed();
        $event = Event::factory()->create(['published' => true]);
        for ($i = 0; $i < 153; $i++) $this->document($event, 'Document'.$i.'.pdf');
        $this->artisan('events:restore-public-documents')->expectsOutputToContain('More than 152')->assertFailed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('files', 153);
    }
}
