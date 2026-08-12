<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstallTest extends TestCase
{
    public function test_manifest_contains_installable_cape_tennis_metadata(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Cape Tennis', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertFileExists(public_path('assets/img/pwa/cape-tennis-app-192.png'));
        $this->assertFileExists(public_path('assets/img/pwa/cape-tennis-app-512.png'));
        $this->assertFileExists(public_path('assets/img/pwa/cape-tennis-app.svg'));
    }

    public function test_service_worker_has_an_offline_fallback_without_caching_account_pages(): void
    {
        $serviceWorker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString("const OFFLINE_URL = '/offline.html'", $serviceWorker);
        $this->assertStringContainsString("event.request.mode !== 'navigate'", $serviceWorker);
        $this->assertStringNotContainsString("cache.put(event.request", $serviceWorker);
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_deployment_syncs_all_pwa_root_files(): void
    {
        $deploymentConfig = file_get_contents(base_path('deploy.config'));

        foreach (['manifest.webmanifest', 'mix-manifest.json', 'offline.html', 'service-worker.js'] as $file) {
            $this->assertStringContainsString($file, $deploymentConfig);
        }
    }
}
