<?php

namespace Tests\Feature\Draw;

use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScoringGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_assignment_gets_guide_once_per_login_and_can_always_reopen(): void
    {
        $user = User::factory()->create(['email' => 'convenor@capetennis.co.za']);
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Assigned courts'])->save();
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => 'score-keeper', 'venue_id' => $venue->id]);
        $this->actingAs($user)->withSession([]);
        request()->setLaravelSession(session()->driver());

        $first = view('layouts/sections/navbar/navbar')->render();
        $this->assertStringContainsString('data-automatic="1"', $first);
        $this->assertStringContainsString('Close scoring guide', $first);
        $this->assertStringContainsString('automatically becomes <strong>Completed</strong>', $first);
        $this->assertStringContainsString(htmlspecialchars(route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id]), ENT_QUOTES), $first);

        $second = view('layouts/sections/navbar/navbar')->render();
        $this->assertStringContainsString('data-automatic="0"', $second);
        $this->assertStringContainsString('data-bs-target="#scoring-guide"', $second);

        event(new Login('web', $user, false));
        $this->assertStringContainsString('data-automatic="1"', view('layouts/sections/navbar/navbar')->render());
    }

    public function test_future_expired_and_other_users_assignments_do_not_show_guide(): void
    {
        $user = User::factory()->create(['email' => 'convenor@capetennis.co.za']);
        foreach ([['starts_at' => now()->addDay()], ['expires_at' => now()->subDay()]] as $window) {
            EventConvenor::create($window + ['event_id' => Event::factory()->create()->id, 'user_id' => $user->id, 'role' => 'score-keeper']);
        }
        EventConvenor::create(['event_id' => Event::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'role' => 'score-keeper']);
        $this->actingAs($user)->withSession([]);
        $this->assertStringNotContainsString('id="scoring-guide"', view('layouts/sections/navbar/navbar')->render());
    }

    public function test_global_super_user_without_assignment_and_guest_do_not_get_guide(): void
    {
        $this->withSession([]);
        $this->assertStringNotContainsString('id="scoring-guide"', view('layouts/sections/navbar/navbar')->render());
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => 'convenor@capetennis.co.za']);
        $user->assignRole('super-user');
        $this->actingAs($user);
        $this->assertStringNotContainsString('id="scoring-guide"', view('layouts/sections/navbar/navbar')->render());
    }

    public function test_other_accounts_with_active_scoring_access_do_not_get_popup_or_menu_link(): void
    {
        $user = User::factory()->create(['email' => 'other-convenor@example.test']);
        EventConvenor::create(['event_id' => Event::factory()->create()->id, 'user_id' => $user->id, 'role' => 'score-keeper']);
        $this->actingAs($user)->withSession([]);
        request()->setLaravelSession(session()->driver());

        $html = view('layouts/sections/navbar/navbar')->render();
        $this->assertStringNotContainsString('id="scoring-guide"', $html);
        $this->assertStringNotContainsString('data-bs-target="#scoring-guide"', $html);
    }
}
