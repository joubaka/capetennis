<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawCardPublicationPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_compact_team_card_prioritises_confirmation_and_retains_workspace_tools(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create());
        $draw = Draw::factory()->create([
            'event_id' => Event::factory()->create()->id,
            'published' => false, 'locked' => false,
            'team_scoring_rules' => ['undecided' => true],
            'team_draw_selection' => ['competition' => ['phase' => 'round_robin']],
            'team_format_snapshot' => ['rubbers' => []],
        ]);
        $draw->setRelation('draw_types', (new \App\Models\DrawType)->forceFill(['type' => 'team']));

        $html = view('backend.draw._includes.draw_tab_team', ['draw' => $draw, 'compactOverview' => true])->render();
        $this->assertStringContainsString(route('team-draw.competition.scoring', $draw), $html);
        $this->assertStringContainsString('>Confirm scoring</a>', $html);
        foreach (['Continue setup', 'Open team fixtures', 'Team ties', 'Draw standings', 'Schedule matches', 'Public schedule preview', 'Publish schedule', 'Assign venues', 'Advanced actions'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('<details class="event-draw-schedule"', $html);
        $this->assertStringContainsString('<details class="event-draw-tools"', $html);

        $draw->team_draw_selection = null;
        $html = view('backend.draw._includes.draw_tab_team', ['draw' => $draw, 'compactOverview' => true])->render();
        $this->assertStringNotContainsString(route('team-draw.competition.scoring', $draw), $html);
        $this->assertStringContainsString('>Continue setup</a>', $html);
    }

    public function test_draw_card_exposes_separate_scoped_publication_controls_and_preview(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => false]);

        $html = view('backend.draw._includes.draw_tab_team', compact('draw'))->render();

        foreach (['Publish draw', 'Publish schedule', 'Draw hidden', 'Schedule hidden', 'More actions'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString(route('draw.toggle.publish', $draw), $html);
        $this->assertStringContainsString(route('draw.toggle.publish.schedule', $draw), $html);
        $this->assertStringContainsString(e(route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id, 'draw_id' => $draw->id, 'date' => 'all'])), $html);
        $this->assertStringContainsString(e(route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'draw_id' => $draw->id, 'date' => 'all'])), $html);
    }

    public function test_card_uses_canonical_publication_fields_and_explains_hidden_names(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => true]);

        $html = view('backend.draw._includes.draw_tab_team', compact('draw'))->render();
        $this->assertStringContainsString('Schedule preview only', $html);
        $this->assertStringContainsString('Hide schedule', $html);
        $this->assertStringContainsString('Publish draw', $html);

        $draw->published = true;
        $html = view('backend.draw._includes.draw_tab_team', compact('draw'))->render();
        $this->assertStringContainsString('Draw published', $html);
        $this->assertStringContainsString('Hide draw', $html);
        $this->assertStringNotContainsString('Schedule preview only', $html);
    }

    public function test_unassigned_actor_sees_no_publication_or_destructive_controls(): void
    {
        $this->actingAs(User::factory()->create());
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $html = view('backend.draw._includes.draw_tab_team', compact('draw'))->render();
        foreach (['toggle-publish', 'btn-delete-draw', 'btn-recreate-fixtures', 'btn-add-venues'] as $control) {
            $this->assertStringNotContainsString($control, $html);
        }
    }

    public function test_superuser_destructive_controls_stay_disabled_for_locked_or_published_draws(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        foreach (['locked', 'published'] as $protectedState) {
            $draw = Draw::factory()->create(['event_id' => $event->id, $protectedState => true, 'team_category_id' => null]);
            $html = view('backend.draw._includes.draw_tab_team', compact('draw'))->render();
            $this->assertMatchesRegularExpression('/class="dropdown-item text-danger btn-delete-draw"\s+disabled/', $html);
        }
    }
}
