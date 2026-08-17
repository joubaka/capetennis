<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_only_sees_published_tournaments_on_the_front_page_list(): void
    {
        $published = Event::factory()->create(['name' => 'Published Tournament']);
        $draft = Event::factory()->unpublished()->create(['name' => 'Private Draft Tournament']);

        $response = $this->getJson(route('home.events.get', ['period' => 'all']));

        $response->assertOk()
            ->assertJsonFragment(['id' => $published->id])
            ->assertJsonMissing(['id' => $draft->id]);

        $this->assertArrayNotHasKey('admin_status', $response->json('0'));
    }

    public function test_assigned_admin_sees_their_draft_but_not_another_tournament_draft(): void
    {
        $admin = User::factory()->create();
        $assignedDraft = Event::factory()->unpublished()->create(['name' => 'Assigned Draft']);
        $otherDraft = Event::factory()->unpublished()->create(['name' => 'Other Draft']);
        EventAdmin::create(['event_id' => $assignedDraft->id, 'user_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->getJson(route('home.events.get', ['period' => 'all']));

        $response->assertOk()
            ->assertJsonFragment(['id' => $assignedDraft->id])
            ->assertJsonMissing(['id' => $otherDraft->id])
            ->assertJsonFragment([
                'publication' => 'Unpublished',
                'entries' => 'Sign-up open',
            ]);
    }

    public function test_super_user_sees_all_draft_tournaments(): void
    {
        $superUser = User::factory()->create()->assignRole('super-user');
        $firstDraft = Event::factory()->unpublished()->create();
        $secondDraft = Event::factory()->unpublished()->create();

        $response = $this->actingAs($superUser)
            ->getJson(route('home.events.get', ['period' => 'all']));

        $response->assertOk()
            ->assertJsonFragment(['id' => $firstDraft->id])
            ->assertJsonFragment(['id' => $secondDraft->id])
            ->assertJsonFragment([
                'publication' => 'Unpublished',
                'entries' => 'Sign-up open',
            ]);
    }

    public function test_admin_sees_publication_status_on_visible_tournaments(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $published = Event::factory()->create(['name' => 'Admin Visible Tournament']);

        $response = $this->actingAs($admin)
            ->getJson(route('home.events.get', ['period' => 'all']));

        $response->assertOk()
            ->assertJsonFragment(['id' => $published->id])
            ->assertJsonFragment([
                'publication' => 'Published',
                'entries' => 'Sign-up open',
            ]);
    }

    public function test_unassigned_admin_cannot_open_an_unpublished_tournament_directly(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $draft = Event::factory()->unpublished()->create();

        $this->actingAs($admin)
            ->get(route('events.show', $draft))
            ->assertNotFound();
    }
}
