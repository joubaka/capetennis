<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminWorkspacePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_workspace_renders_compact_registration_controls_and_explicit_save_guidance(): void
    {
        $export = getenv('CT_SUPERADMIN_WORKSPACE_QA_HTML') === '1';
        $capturePath = storage_path('framework/testing/superadmin-workspace.html');
        if ($export && is_file($capturePath)) {
            unlink($capturePath);
        }

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'name' => 'Dashboard QA Administrator',
            'email' => 'dashboard-qa@example.test',
        ])->assignRole('super-user');

        $response = $this->actingAs($admin)
            ->get(route('backend.superadmin.workspace', ['tab' => 'settings']));

        $response->assertOk()
            ->assertSee('Player Plus purchases &amp; pricing', false)
            ->assertSee('Switches save immediately.')
            ->assertSee('Settings change history')
            ->assertSee(route('superadmin.audit.index', ['category' => 'business', 'action' => 'settings.updated']))
            ->assertSee('monthly renewal settings use their own Save monthly settings button.')
            ->assertDontSee('id="sa-pane-wallets"', false)
            ->assertDontSee('id="sa-pane-withdrawals"', false)
            ->assertViewMissing('wallets')
            ->assertViewMissing('financeSummary')
            ->assertSee('id="sa-settings-toast-close"', false)
            ->assertSee('action="'.route('settings.store').'"', false)
            ->assertSee('data-setting-key="disciplinary_system_enabled"', false);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadHTML($response->getContent()));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $plusColumn = $xpath->query('//*[@id="sa-plus-settings-heading"]/parent::section/parent::div')->item(0);
        $this->assertNotNull($plusColumn);
        $this->assertContains('col-12', explode(' ', $plusColumn->getAttribute('class')));

        $disciplineCard = $xpath->query('//*[@id="sa-disciplinary-system-enabled"]/parent::div/parent::div')->item(0);
        $this->assertNotNull($disciplineCard);
        $this->assertNotContains('h-100', explode(' ', $disciplineCard->getAttribute('class')));
        $this->assertSame(1, $xpath->query('//*[@id="sa-settings-toast-close"]')->length);
        $this->assertSame('', $xpath->query('//*[@id="sa-settings-toast-close"]')->item(0)->getAttribute('data-bs-dismiss'));

        // Export only this successful current render, never a previous failed run.
        if ($export) {
            if (! is_dir(dirname($capturePath))) {
                mkdir(dirname($capturePath), 0777, true);
            }
            $this->assertNotFalse(file_put_contents($capturePath, $response->getContent()));
        }
    }

    public function test_workspace_remains_unavailable_to_an_ordinary_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('backend.superadmin.workspace', ['tab' => 'settings']))
            ->assertForbidden();
    }
}
