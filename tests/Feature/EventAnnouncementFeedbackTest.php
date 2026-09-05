<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAnnouncementFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_without_email_explains_where_the_announcement_was_published(): void
    {
        [$admin, $event] = $this->eventAdmin();

        $this->actingAs($admin)
            ->postJson(route('admin.events.announcements.store', $event), [
                'title' => 'Weather update',
                'message' => '<p>Play starts at 10:00.</p>',
                'sendMail' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Announcement published on the event page. No email was sent.');

        $this->assertDatabaseHas('announcements', [
            'event_id' => $event->id,
            'title' => 'Weather update',
        ]);
    }

    public function test_visually_empty_rich_text_is_rejected_with_actionable_feedback(): void
    {
        [$admin, $event] = $this->eventAdmin();

        $this->actingAs($admin)
            ->postJson(route('admin.events.announcements.store', $event), [
                'title' => 'Empty update',
                'message' => '<p><br></p>',
                'sendMail' => false,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message'])
            ->assertJsonPath('errors.message.0', 'Enter an announcement message before saving.');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_update_and_visibility_changes_return_specific_feedback(): void
    {
        [$admin, $event] = $this->eventAdmin();
        $announcement = Announcement::create([
            'event_id' => $event->id,
            'title' => 'Original',
            'message' => '<p>Original message.</p>',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.announcements.update', $announcement), [
                'title' => 'Updated',
                'message' => '<p>Updated message.</p>',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Announcement updated. Previously queued emails were not resent.');

        $this->actingAs($admin)
            ->patchJson(route('admin.announcements.toggle', $announcement))
            ->assertOk()
            ->assertJsonPath('hidden', true)
            ->assertJsonPath('message', 'Announcement hidden from the public event page.');

        $this->actingAs($admin)
            ->patchJson(route('admin.announcements.toggle', $announcement))
            ->assertOk()
            ->assertJsonPath('hidden', false)
            ->assertJsonPath('message', 'Announcement is visible on the public event page again.');
    }

    /** @return array{User, Event} */
    private function eventAdmin(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $admin->id,
        ]);

        return [$admin, $event];
    }
}
