<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomatedEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_can_switch_every_automated_email_setting(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $super = User::factory()->create()->assignRole('super-user');

        foreach (SiteSetting::AUTOMATED_EMAIL_TOGGLES as $key) {
            $this->actingAs($super)->postJson(route('settings.store.single'), [
                'key' => $key,
                'value' => '0',
            ])->assertOk();

            $this->assertSame('0', SiteSetting::get($key));
        }
    }

    public function test_non_super_user_cannot_change_email_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('settings.store.single'), [
            'key' => 'player_email_on_team_registration',
            'value' => '0',
        ])->assertForbidden();
    }
}
