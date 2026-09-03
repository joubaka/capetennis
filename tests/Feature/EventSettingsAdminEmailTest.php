<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSettingsAdminEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_selector_shows_emails_and_preserves_linked_accounts(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $viewer = User::factory()->create()->assignRole('super-user');
        DB::table('eventtypes')->insert([
            'id' => 1,
            'name' => 'Individual',
            'type' => EventType::INDIVIDUAL,
        ]);
        $event = Event::factory()->create();
        $linked = User::factory()->create([
            'name' => 'Shared <Admin> Name',
            'email' => 'linked@example.test',
        ]);
        $unlinked = User::factory()->create([
            'name' => 'Shared <Admin> Name',
            'email' => 'unlinked@example.test',
        ]);
        $event->admins()->attach($linked->id);

        $response = $this->actingAs($viewer)
            ->get(route('admin.events.settings', $event))
            ->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        foreach ([$linked, $unlinked] as $admin) {
            $option = $xpath->query('//select[@name="admins"]/option[@value="'.$admin->id.'"]')->item(0);
            $this->assertNotNull($option);
            $this->assertSame($admin->name.' ('.$admin->email.')', trim($option->textContent));
            $this->assertSame($admin->is($linked), $option->hasAttribute('selected'));
            $this->assertSame(0, $option->getElementsByTagName('admin')->length);
        }
    }
}
