<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{PlayerPathwayEvents, PlayerPathwayService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PlayerPathwayEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        CarbonImmutable::setTestNow('2026-11-15 10:00:00 Africa/Johannesburg');
        Schema::create('events', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->date('start_date'); $t->date('end_date')->nullable();
            $t->boolean('published')->default(true); $t->boolean('results_published')->default(false);
            $t->boolean('signUp')->default(true); $t->integer('deadline')->nullable();
            $t->string('status')->nullable(); $t->unsignedBigInteger('eventType');
        });
        Schema::create('eventtypes', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('code')->nullable(); $t->integer('type')->default(1); });
        Schema::create('categories', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('category_events', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('category_id'); });
        Schema::create('draws', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('category_event_id'); });
        Schema::create('venues', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('event_venues', function (Blueprint $t) { $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('venue_id'); $t->integer('num_courts')->nullable(); });
        Schema::create('draw_venue_court_allocations', function (Blueprint $t) { $t->unsignedBigInteger('draw_id'); $t->unsignedBigInteger('venue_id'); $t->string('court_label'); });
        Schema::create('event_venue_courts', function (Blueprint $t) { $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('venue_id'); $t->string('label'); $t->string('ball_type'); });
        DB::table('eventtypes')->insert([
            ['id' => 1, 'name' => 'Individual', 'code' => null, 'type' => 1],
            ['id' => 2, 'name' => 'Wilson', 'code' => 'wilson', 'type' => 1],
            ['id' => 3, 'name' => 'TSA tournaments', 'code' => 'tsa', 'type' => 1],
            ['id' => 4, 'name' => 'Wilson', 'code' => 'wilson', 'type' => 2],
            ['id' => 5, 'name' => 'Wilson', 'code' => 'masters', 'type' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function player(): Player
    {
        return (new Player)->setRawAttributes(['id' => 1, 'dateOfBirth' => '2016-02-01', 'gender' => 1]);
    }

    private function pathway(string $stage): array
    {
        return ['presentation' => ['event_stage' => $stage, 'state' => 'current']];
    }

    private function event(string $category, array $attributes = []): int
    {
        $id = DB::table('events')->insertGetId($attributes + ['name' => 'Fictional tournament', 'start_date' => '2026-11-20', 'eventType' => 1]);
        $categoryId = DB::table('categories')->insertGetId(['name' => $category]);
        DB::table('category_events')->insert(['event_id' => $id, 'category_id' => $categoryId]);
        return $id;
    }

    private function ids(string $stage): array
    {
        return array_column(app(PlayerPathwayEvents::class)->forPlayer($this->player(), $this->pathway($stage))['events'], 'id');
    }

    public function test_only_public_upcoming_individual_nonterminal_events_are_projected_without_mutation(): void
    {
        $good = $this->event('Red ball');
        foreach ([['published' => false], ['status' => ' CANCELLED '], ['status' => 'completed'], ['status' => 'archived'], ['status' => 'postponed'],
            ['start_date' => '2026-11-01', 'end_date' => '2026-11-14'], ['results_published' => true], ['eventType' => 4], ['eventType' => 5]] as $attributes) {
            $this->event('Red ball', $attributes);
        }
        $ongoing = $this->event('Red ball', ['start_date' => '2026-11-14', 'end_date' => '2026-11-16']);
        $before = DB::table('events')->get()->toJson();
        $this->assertSame([$ongoing, $good], $this->ids('red'));
        $this->assertSame($before, DB::table('events')->get()->toJson());
        $this->assertDatabaseCount('events', 11);
        $this->assertDatabaseCount('category_events', 11);
    }

    public function test_exact_categories_and_type_evidence_match_but_titles_and_ambiguous_divisions_do_not(): void
    {
        $red = $this->event('Red ball');
        $orange = $this->event('U/9 Boys B');
        $b = $this->event('Wilson B');
        $a = $this->event('U/13 Boys A', ['eventType' => 2]);
        $tsa = $this->event('U/13 Boys', ['eventType' => 3]);
        $this->event('U/13 Boys', ['name' => 'Wilson A TSA tournament']);
        $this->event('U/13 Boys A/B', ['eventType' => 2]);
        $this->event('Red / Orange ball');
        $this->event('U/13 Boys A');
        $this->assertSame([$red], $this->ids('red'));
        $this->assertSame([$orange], $this->ids('orange'));
        $this->assertSame([$b], $this->ids('Wilson B'));
        $this->assertSame([$a], $this->ids('Wilson A'));
        $this->assertSame([$tsa], $this->ids('TSA tournaments'));
    }

    public function test_known_age_gender_exclusions_are_applied_without_claiming_eligibility_for_unknowns(): void
    {
        $good = $this->event('U/13 Boys A', ['eventType' => 2]);
        $this->event('U/9 Boys A', ['eventType' => 2]);
        $this->event('U/13 Girls A', ['eventType' => 2]);
        $this->assertSame([$good], $this->ids('Wilson A'));
        $unknown = $this->player()->setRawAttributes(['id' => 1]);
        $this->assertCount(3, app(PlayerPathwayEvents::class)->forPlayer($unknown, $this->pathway('Wilson A'))['events']);
    }

    public function test_court_metadata_precedence_mixed_context_and_cross_event_allocations_are_respected(): void
    {
        $red = $this->event('Red ball');
        $mixed = $this->event('Red ball');
        $field = fn ($id) => DB::table('category_events')->where('event_id', $id)->value('id');
        $first = DB::table('draws')->insertGetId(['event_id' => $red, 'category_event_id' => $field($red)]);
        $second = DB::table('draws')->insertGetId(['event_id' => $mixed, 'category_event_id' => $field($mixed)]);
        foreach ([[$first, $red, '1', 'orange'], [$second, $mixed, '2', 'red'], [$second, $mixed, '3', 'orange']] as [$draw, $event, $label, $ball]) {
            DB::table('draw_venue_court_allocations')->insert(['draw_id' => $draw, 'venue_id' => 1, 'court_label' => $label]);
            DB::table('event_venue_courts')->insert(['event_id' => $event, 'venue_id' => 1, 'label' => $label, 'ball_type' => $ball]);
        }
        $this->assertSame([], $this->ids('red'));
        // Contradictory labels do not become Orange recommendations either.
        $this->assertSame([], $this->ids('orange'));
        DB::table('event_venue_courts')->where('event_id', $red)->update(['event_id' => 999]);
        $this->assertSame([$red], $this->ids('red'));
    }

    public function test_event_projection_has_public_link_date_venue_and_canonical_registration_status(): void
    {
        $id = $this->event('Red ball', ['deadline' => 10]);
        DB::table('venues')->insert(['id' => 1, 'name' => 'Fictional courts']);
        DB::table('event_venues')->insert(['event_id' => $id, 'venue_id' => 1]);
        $event = app(PlayerPathwayEvents::class)->forPlayer($this->player(), $this->pathway('red'))['events'][0];
        $this->assertSame(route('events.show', $id), $event['url']);
        $this->assertSame('20 Nov 2026', $event['date']);
        $this->assertSame('Fictional courts', $event['venue']);
        $this->assertSame('Entries closed', $event['status']);
    }

    public function test_matching_is_bounded_and_missing_pending_or_untrusted_stages_yield_no_events(): void
    {
        for ($i = 0; $i <= PlayerPathwayEvents::CANDIDATE_LIMIT; $i++) $this->event('Red ball');
        $service = app(PlayerPathwayEvents::class);
        $result = $service->forPlayer($this->player(), $this->pathway('red'));
        $this->assertCount(PlayerPathwayEvents::RESULT_LIMIT, $result['events']);
        $this->assertTrue($result['limited']);
        foreach ([[], ['presentation' => ['event_stage' => null]], $this->pathway('unknown')] as $pathway) {
            $this->assertSame([], $service->forPlayer($this->player(), $pathway)['events']);
        }
    }

    public function test_presentation_unifies_saved_pending_local_and_missing_states_and_validates_the_whole_cutoff_pair(): void
    {
        $service = app(PlayerPathwayService::class);
        $metrics = ['player_id' => 1, 'available' => true, 'stale' => false,
            'stage_ratings' => ['standard' => ['score' => 92.7, 'comparable' => true, 'local' => false]], 'stage_events' => ['standard' => 3]];
        $summary = fn ($m) => $service->summary($this->player(), ['pathway_metrics' => $m]);
        $this->assertSame('current', $summary($metrics)['presentation']['state']);
        $this->assertSame('TSA tournaments', $summary($metrics)['presentation']['event_stage']);
        $this->assertStringContainsString('above 75', $summary($metrics)['presentation']['why']);
        $stale = array_replace($metrics, ['stale' => true]);
        $this->assertSame('update_pending', $summary($stale)['presentation']['state']);
        $this->assertSame('TSA tournaments', $summary($stale)['presentation']['event_stage']);
        $this->assertSame('no_rating', $summary(array_replace($metrics, ['player_id' => 2]))['presentation']['state']);
        $local = $metrics; $local['stage_ratings']['standard']['local'] = true;
        $this->assertSame('insufficient_evidence', $summary($local)['presentation']['state']);
        $this->assertNull($summary($local)['presentation']['event_stage']);
        foreach ([[50, null], [null, 75], [75, 50], [50, 50], [-1, 75], [50, INF]] as [$wilson, $tsa]) {
            config(['pathway.wilson_a_above' => $wilson, 'pathway.tsa_above' => $tsa]);
            $pathway = $summary($metrics);
            $this->assertFalse($pathway['competition_bands_available']);
            $this->assertNull($pathway['currentCompetition']);
            $this->assertNull($pathway['suggestedCompetition']);
            $this->assertSame('configuration_unavailable', $pathway['presentation']['state']);
            $this->assertNull($pathway['presentation']['event_stage']);
        }
    }
}
