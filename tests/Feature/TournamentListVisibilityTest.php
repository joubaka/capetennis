<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TournamentListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_only_renders_published_series_and_accessible_event_controls(): void
    {
        $published = Series::factory()->create([
            'name' => 'Published Cape Series',
            'leaderboard_published' => true,
        ]);
        $draft = Series::factory()->create([
            'name' => 'Private Draft Series',
            'leaderboard_published' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Upcoming events')
            ->assertSee('Event period')
            ->assertSee('Published Cape Series')
            ->assertDontSee('Private Draft Series')
            ->assertViewHas('series', fn ($series) => $series->modelKeys() === [$published->id]
                && !$series->contains('id', $draft->id));
    }

    public function test_guest_only_sees_published_tournaments_on_the_front_page_list(): void
    {
        $published = Event::factory()->create(['name' => 'Published Tournament']);
        $draft = Event::factory()->unpublished()->create(['name' => 'Private Draft Tournament']);

        $response = $this->getJson(route('home.events.get', ['period' => 'all']));

        $response->assertOk()
            ->assertJsonFragment(['id' => $published->id])
            ->assertJsonMissing(['id' => $draft->id])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.signUp')
            ->assertJsonMissingPath('data.0.published')
            ->assertJsonMissingPath('data.0.status');

        $this->assertArrayNotHasKey('admin_status', $response->json('data.0'));
    }

    public function test_event_feed_is_paginated_and_reports_exact_totals(): void
    {
        Event::factory()->count(25)->create();

        $firstPage = $this->getJson(route('home.events.get', ['period' => 'all']));
        $firstPage->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 25);

        $this->getJson(route('home.events.get', ['period' => 'all', 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_assigned_admin_sees_their_draft_but_not_another_tournament_draft(): void
    {
        $admin = User::factory()->create();
        $assignedDraft = Event::factory()->unpublished()->create([
            'name' => 'Assigned Draft',
            'start_date' => now()->addMonth(),
            'deadline' => 0,
        ]);
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
        Role::findOrCreate('super-user');
        $superUser = User::factory()->create()->assignRole('super-user');
        $firstDraft = Event::factory()->unpublished()->create([
            'start_date' => now()->addMonth(),
            'deadline' => 0,
        ]);
        $secondDraft = Event::factory()->unpublished()->create([
            'start_date' => now()->addMonth(),
            'deadline' => 0,
        ]);

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
        Role::findOrCreate('admin');
        $admin = User::factory()->create()->assignRole('admin');
        $published = Event::factory()->create([
            'name' => 'Admin Visible Tournament',
            'start_date' => now()->addMonth(),
            'deadline' => 0,
        ]);

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
        Role::findOrCreate('admin');
        $admin = User::factory()->create()->assignRole('admin');
        $draft = Event::factory()->unpublished()->create();

        $this->actingAs($admin)
            ->get(route('events.show', $draft))
            ->assertNotFound();
    }
}
