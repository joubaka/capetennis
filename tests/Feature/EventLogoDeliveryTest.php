<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class EventLogoDeliveryTest extends TestCase
{
    public function test_uploaded_png_is_served_without_copying_to_the_web_root(): void
    {
        $filename = 'test-logo-'.Str::uuid().'.png';
        $path = public_path('assets/img/logos/'.$filename);
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));

        try {
            $response = $this->get('/assets/img/logos/'.$filename)
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame(realpath($path), $response->baseResponse->getFile()->getRealPath());
        } finally {
            unlink($path);
        }
    }

    public function test_missing_logo_returns_not_found(): void
    {
        $this->get('/assets/img/logos/missing-'.Str::uuid().'.png')->assertNotFound();
    }

    public function test_non_image_file_cannot_be_served_as_a_logo(): void
    {
        $filename = 'test-logo-'.Str::uuid().'.png';
        $path = public_path('assets/img/logos/'.$filename);
        file_put_contents($path, '<?php echo "not an image";');

        try {
            $this->get('/assets/img/logos/'.$filename)->assertNotFound();
        } finally {
            unlink($path);
        }
    }
}
