<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, DrawAuditLog, Event, Fixture, Player, Registration, User};
use App\Services\Draw\LuckyPrizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LuckyPrizeTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true, 'locked' => true]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'num_sets' => 1,
            'playoff_config' => [['name' => 'Main', 'slug' => 'main', 'size' => 2, 'enabled' => true]]]);
        $user = User::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        return $draw;
    }

    private function entrant(Draw $draw, ?Player $player = null): Registration
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach(($player ?? Player::factory()->create())->id);
        $draw->registrations()->attach($registration->id);
        CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $registration->id]);
        return $registration;
    }

    private function final(Draw $draw, Registration $winner, Registration $loser): Fixture
    {
        return Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 1,
            'registration1_id' => $winner->id, 'registration2_id' => $loser->id, 'winner_registration' => $winner->id]);
    }

    private function url(Draw $draw, ?Event $event = null): string
    {
        return route('frontend.scoring.lucky-prize.pool', [$event?->id ?? $draw->event_id, $draw]);
    }

    public function test_prize_pool_excludes_champion_aliases_withdrawals_and_other_draws_and_deduplicates_people(): void
    {
        $draw = $this->draw();
        $champion = $this->entrant($draw);
        $eligible = $this->entrant($draw);
        $this->entrant($draw, $champion->players->first());
        $this->entrant($draw, $eligible->players->first());
        $withdrawn = $this->entrant($draw);
        $withdrawn->categoryEventRegistrations()->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $outsider = Registration::factory()->create();
        $outsider->players()->attach(Player::factory()->create()->id);
        CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $outsider->id]);
        $this->final($draw, $champion, $eligible);
        $pool = $this->getJson($this->url($draw))->assertOk()->assertJsonPath('ready', true)->json();
        $this->assertSame([$eligible->players->first()->id], array_column($pool['players'], 'id'));
        $this->assertSame([$champion->players->first()->id], array_column($pool['champions'], 'id'));
        if (getenv('CT_LUCKY_PRIZE_SNAPSHOT') === '1') {
            file_put_contents(storage_path('framework/testing/lucky-prize-wheel.html'), view('frontend.scoring.partials.lucky-prize', ['luckyPrizeDraws' => collect([$draw])])->render());
        }
    }

    public function test_spin_is_idempotent_and_prior_winners_persist_without_changing_competition_records(): void
    {
        $draw = $this->draw(); $champion = $this->entrant($draw); $other = $this->entrant($draw); $this->entrant($draw);
        $this->final($draw, $champion, $other);
        $before = [Fixture::all()->toJson(), DB::table('fixture_results')->get()->toJson(), DB::table('draw_registrations')->get()->toJson()];
        $pool = $this->getJson($this->url($draw))->json();
        $payload = ['token' => (string) Str::uuid(), 'revision' => $pool['revision']];
        $first = $this->postJson($this->url($draw), $payload)->assertOk()->json();
        $this->postJson($this->url($draw), $payload)->assertOk()->assertExactJson($first);
        $this->assertSame(1, DrawAuditLog::where('action', LuckyPrizeService::ACTION)->count());
        $next = $this->getJson($this->url($draw))->assertJsonCount(1, 'players')->assertJsonCount(1, 'previous_winners')->json();
        $this->assertNotContains($first['winner']['id'], array_column($next['players'], 'id'));
        $this->postJson($this->url($draw), ['token' => (string) Str::uuid(), 'revision' => $pool['revision']])->assertConflict();
        $second = $this->postJson($this->url($draw), ['token' => (string) Str::uuid(), 'revision' => $next['revision']])->assertOk()->json();
        $this->assertNotSame($first['winner']['id'], $second['winner']['id']);
        $this->getJson($this->url($draw))->assertJsonPath('ready', false)->assertJsonCount(2, 'previous_winners');
        $this->assertSame($before, [Fixture::all()->toJson(), DB::table('fixture_results')->get()->toJson(), DB::table('draw_registrations')->get()->toJson()]);
    }

    public function test_unresolved_or_foreign_first_place_and_doubles_are_blocked(): void
    {
        $draw = $this->draw(); $one = $this->entrant($draw); $two = $this->entrant($draw);
        $this->getJson($this->url($draw))->assertJsonPath('ready', false);
        $this->postJson($this->url($draw), ['token' => (string) Str::uuid(), 'revision' => str_repeat('0', 64)])->assertUnprocessable();
        $foreign = Registration::factory()->create(); $foreign->players()->attach(Player::factory()->create()->id);
        $fixture = $this->final($draw, $foreign, $two);
        $this->getJson($this->url($draw))->assertJsonPath('ready', false)->assertJsonCount(0, 'champions');
        $fixture->update(['registration1_id' => $one->id, 'winner_registration' => $one->id]);
        $two->players()->attach(Player::factory()->create()->id);
        $this->getJson($this->url($draw))->assertJsonPath('ready', false)->assertJsonCount(0, 'players');
        $this->assertSame(0, DrawAuditLog::where('action', LuckyPrizeService::ACTION)->count());
    }

    public function test_independent_round_robin_groups_require_every_champion_resolved(): void
    {
        $draw = $this->draw(); $draw->settings->update(['workflow' => 'round_robin']);
        foreach (['A', 'B'] as $name) {
            $group = $draw->groups()->create(['name' => $name]);
            $winner = $this->entrant($draw); $loser = $this->entrant($draw);
            $group->registrations()->attach([$winner->id, $loser->id]);
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
                'registration1_id' => $winner->id, 'registration2_id' => $loser->id, 'winner_registration' => $winner->id]);
            if ($name === 'B') $this->getJson($this->url($draw))->assertJsonPath('ready', false);
            $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 3, 'registration2_score' => 0]);
        }
        $this->getJson($this->url($draw))->assertJsonPath('ready', true)->assertJsonCount(2, 'champions')->assertJsonCount(2, 'players');
    }

    public function test_prize_routes_enforce_event_scope_organiser_authority_and_input_validation(): void
    {
        $draw = $this->draw(); $one = $this->entrant($draw); $two = $this->entrant($draw); $this->final($draw, $one, $two);
        $this->getJson($this->url($draw, Event::factory()->create()))->assertNotFound();
        $this->postJson($this->url($draw), ['token' => 'invalid', 'revision' => 'invalid'])->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->getJson($this->url($draw))->assertForbidden();
        $this->postJson($this->url($draw), ['token' => (string) Str::uuid(), 'revision' => str_repeat('0', 64)])->assertForbidden();
        $this->assertSame(0, DrawAuditLog::where('action', LuckyPrizeService::ACTION)->count());
    }
}
