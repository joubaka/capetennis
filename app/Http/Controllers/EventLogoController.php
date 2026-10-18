<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventLogoController extends Controller
{
    /**
     * Serve runtime uploads when the web root is a separate deployment copy.
     * Existing static logos continue to be served directly by the web server.
     */
    public function __invoke(string $filename): BinaryFileResponse
    {
        abort_unless($filename === basename($filename) && !str_contains($filename, '\\'), 404);

        $directory = realpath(public_path('assets/img/logos'));
        $path = $directory === false ? false : realpath($directory.DIRECTORY_SEPARATOR.$filename);
        abort_unless($path !== false && is_file($path) && dirname($path) === $directory, 404);

        $mime = @getimagesize($path)['mime'] ?? null;
        abort_unless(in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true), 404);

        return response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
