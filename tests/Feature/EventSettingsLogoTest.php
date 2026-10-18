<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSettingsLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_png_uploads_return_displayable_unique_logos_and_can_be_reselected(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $paths = [];

        try {
            foreach ([1, 2] as $attempt) {
                $response = $this->actingAs($user)->post(
                    route('admin.events.settings.update', $event),
                    ['_method' => 'PATCH', 'logo_upload' => UploadedFile::fake()->image('sponsor.png')],
                    ['Accept' => 'application/json']
                )->assertOk();

                $filename = $response->json('logo');
                $paths[] = public_path('assets/img/logos/'.$filename);
                $this->assertFileExists(end($paths));
                $this->assertSame('image/png', mime_content_type(end($paths)));
                $this->assertSame($filename, $event->fresh()->logo);
                $response->assertJsonPath('logo_url', asset('assets/img/logos/'.$filename));
            }

            $this->assertNotSame($paths[0], $paths[1]);
            $filename = basename($paths[0]);
            $this->patchJson(route('admin.events.settings.update', $event), ['logo_existing' => $filename])
                ->assertOk()->assertJsonPath('logo', $filename);
            $this->assertSame($filename, $event->fresh()->logo);
        } finally {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }
}
