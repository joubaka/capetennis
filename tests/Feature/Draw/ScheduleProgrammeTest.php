<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Player, TeamFixture, Venue};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleProgrammeTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $rubbers = 1, int $courts = 1): array
    {
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Programme courts'])->save();
        $players = Player::factory()->count($rubbers * 2)->create();
        $rounds = []; $drawIds = [];
        foreach (['Singles', 'Singles Reverse', 'Doubles', 'Mixed doubles'] as $index => $discipline) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Boys – '.$discipline, 'gender' => 'Boys']);
            $draw->forceFill(['team_category_id' => 1])->save();
            $draw->venues()->attach($venue->id, ['num_courts' => $courts]);
            $drawIds[] = $draw->id;
            foreach ([1, 2, 3] as $round) {
                foreach (range(1, $rubbers) as $number) {
                    $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $number, 'rubber_sequence' => $number, 'player_count_per_team' => 1]);
                    $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $players[($number - 1) * 2]->id, 'team2_id' => $players[($number - 1) * 2 + 1]->id]);
                }
                $day = $index === 0 ? 1 : ($index === 1 ? ($round === 1 ? 1 : 2) : ($index === 2 ? 2 : 3));
                $sequence = $index === 0 ? $round : ($index === 1 ? ($round === 1 ? 4 : $round - 1) : ($index === 2 ? $round + 2 : $round));
                $rounds[] = ['draw_id' => $draw->id, 'round' => $round, 'day' => $day, 'sequence' => $sequence];
            }
        }
        $days = array_map(fn ($day) => ['start' => '2026-10-'.$day.' 08:00:00', 'end' => '2026-10-'.$day.' 18:00:00'], ['09', '10', '11']);
        $options = ['start' => $days[0]['start'], 'end' => $days[2]['end'], 'duration' => 30, 'wave_minutes' => 30, 'court_gap' => 0, 'player_rest' => 0, 'draw_ids' => $drawIds, 'programme' => compact('days', 'rounds')];
        return [$event, $options];
    }

    public function test_complete_preview_uses_all_three_days_without_writes_and_applies_once(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertCount(12, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertSame([4, 5, 3], collect($preview['matches'])->groupBy(fn ($row) => substr($row['scheduled_at'], 0, 10))->map->count()->values()->all());
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
        $this->assertSame(12, $service->apply($event, $options, $preview['revision'])['count']);
        $saved = $service->preview($event, $options);
        $this->assertCount(0, $saved['matches']);
        $this->assertSame(12, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_short_day_leaves_matches_unallocated_and_does_not_spill_overnight(): void
    {
        [$event, $options] = $this->scenario();
        $options['programme']['days'][0]['end'] = '2026-10-09 08:45:00';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertNotEmpty($preview['unscheduled']);
        $this->assertCount(1, collect($preview['matches'])->filter(fn ($row) => str_starts_with($row['scheduled_at'], '2026-10-09')));
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_omitted_round_and_foreign_round_are_rejected(): void
    {
        [$event, $options] = $this->scenario();
        $foreign = Draw::factory()->create();
        foreach (['missing', 'foreign', 'overnight', 'same_date', 'reversed_rounds'] as $case) {
            $invalid = $options;
            if ($case === 'missing') array_pop($invalid['programme']['rounds']);
            if ($case === 'foreign') $invalid['programme']['rounds'][0]['draw_id'] = $foreign->id;
            if ($case === 'overnight') $invalid['programme']['days'][0]['end'] = '2026-10-10 01:00:00';
            if ($case === 'same_date') $invalid['programme']['days'][1] = ['start' => '2026-10-09 19:00:00', 'end' => '2026-10-09 23:00:00'];
            if ($case === 'reversed_rounds') $invalid['programme']['rounds'][0]['sequence'] = 4;
            try { app(EventVenueScheduleService::class)->preview($event, $invalid); $this->fail('Invalid programme accepted.'); }
            catch (\InvalidArgumentException $exception) { $this->assertNotEmpty($exception->getMessage()); }
        }
    }

    public function test_changed_programme_revision_rejects_apply_without_writes(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $options['programme']['days'][2]['end'] = '2026-10-11 17:00:00';
        try { $service->apply($event, $options, $preview['revision']); $this->fail('Stale programme accepted.'); }
        catch (\InvalidArgumentException $exception) { $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count()); }
    }

    public function test_daily_breaks_keep_matches_and_rest_out_of_the_break(): void
    {
        [$event, $options] = $this->scenario();
        $options['programme']['days'][0] += ['break_start' => '2026-10-09 08:45:00', 'break_end' => '2026-10-09 10:00:00'];
        $options['player_rest'] = 15;
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $this->assertCount(12, $preview['matches']);
        $dayOne = collect($preview['matches'])->filter(fn ($row) => str_starts_with($row['scheduled_at'], '2026-10-09'));
        $this->assertSame(['2026-10-09 08:00:00', '2026-10-09 10:00:00', '2026-10-09 10:45:00', '2026-10-09 11:30:00'], $dayOne->pluck('scheduled_at')->all());
    }

    public function test_representative_288_fixture_programme_uses_one_calendar(): void
    {
        [$event, $options] = $this->scenario(24, 15);
        $started = microtime(true);
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        fwrite(STDERR, '\n288-fixture preview: '.number_format(microtime(true) - $started, 2).' seconds\n');
        $this->assertCount(288, $preview['matches']);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(288, collect($preview['matches'])->pluck('fixture_key')->unique());
        $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count());
    }

    public function test_incoherent_fixed_programme_order_is_rejected(): void
    {
        [$event, $options] = $this->scenario();
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        TeamFixture::where('draw_id', $options['draw_ids'][1])->where('round_nr', 1)->update(['scheduled_at' => '2026-10-09 08:00:00']);
        $this->expectException(\InvalidArgumentException::class);
        $service->preview($event, $options);
    }

    public function test_gender_order_repeats_within_each_round(): void
    {
        [$event, $options] = $this->scenario();
        $source = Draw::findOrFail($options['draw_ids'][0]);
        $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Girls – Singles', 'gender' => 'Girls']);
        $girls->forceFill(['team_category_id' => 1])->save();
        $girls->venues()->attach($source->venues->first()->id, ['num_courts' => 1]);
        $options['draw_ids'][] = $girls->id;
        foreach ([1, 2, 3] as $round) {
            $fixture = TeamFixture::create(['draw_id' => $girls->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
            $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
            $options['programme']['rounds'][] = ['draw_id' => $girls->id, 'round' => $round, 'day' => 1, 'sequence' => $round];
        }
        $options['gender_waves'] = 'boys_then_girls';
        $preview = app(EventVenueScheduleService::class)->preview($event, $options);
        $singles = collect($preview['matches'])->whereIn('draw_id', [$source->id, $girls->id])->values();
        $this->assertSame([$source->id, $girls->id, $source->id, $girls->id, $source->id, $girls->id], $singles->pluck('draw_id')->all());
        $this->assertSame([1, 1, 2, 2, 3, 3], $singles->pluck('round')->all());
    }

    public function test_each_day_uses_its_own_gender_order(): void
    {
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Daily order courts'])->save();
        $source = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Boys – Singles', 'gender' => 'Boys']);
        $source->forceFill(['team_category_id' => 1])->save();
        $source->venues()->attach($venue->id, ['num_courts' => 2]);
        $days = array_map(fn ($day) => ['start' => '2026-10-'.$day.' 08:00:00', 'end' => '2026-10-'.$day.' 18:00:00'], ['09', '10', '11']);
        $options = ['start' => $days[0]['start'], 'end' => $days[2]['end'], 'duration' => 30, 'wave_minutes' => 30, 'court_gap' => 0, 'player_rest' => 0, 'draw_ids' => [$source->id], 'programme' => ['days' => $days, 'rounds' => []]];
        $girls = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/10 Girls – Singles', 'gender' => 'Girls']);
        $girls->forceFill(['team_category_id' => 1])->save();
        $girls->venues()->attach($source->venues->first()->id, ['num_courts' => 2]);
        $options['draw_ids'][] = $girls->id;
        foreach ([1, 2, 3] as $round) {
            $boysFixture = TeamFixture::create(['draw_id' => $source->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
            $boysFixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
            $fixture = TeamFixture::create(['draw_id' => $girls->id, 'fixture_type' => 1, 'round_nr' => $round, 'match_nr' => $round, 'rubber_sequence' => 1, 'player_count_per_team' => 1]);
            $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
            foreach ([$source->id, $girls->id] as $drawId) $options['programme']['rounds'][] = ['draw_id' => $drawId, 'round' => $round, 'day' => $round, 'sequence' => 1];
        }
        foreach (['girls_then_boys', 'boys_then_girls', 'combined'] as $index => $order) $options['programme']['days'][$index]['gender_waves'] = $order;
        $options['gender_waves'] = 'boys_then_girls';
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $rows = collect($preview['matches']);
        foreach ([[$girls->id, 1, '08:00:00'], [$source->id, 1, '08:30:00'], [$source->id, 2, '08:00:00'], [$girls->id, 2, '08:30:00'], [$source->id, 3, '08:00:00'], [$girls->id, 3, '08:00:00']] as [$drawId, $round, $time]) {
            $this->assertSame($time, substr($rows->where('draw_id', $drawId)->where('round', $round)->first()['scheduled_at'], 11));
        }
        $options['programme']['days'][0]['gender_waves'] = 'boys_then_girls';
        try { $service->apply($event, $options, $preview['revision']); $this->fail('Changed day order accepted with stale preview.'); }
        catch (\InvalidArgumentException $exception) { $this->assertSame(0, TeamFixture::whereNotNull('scheduled_at')->count()); }
    }
}
