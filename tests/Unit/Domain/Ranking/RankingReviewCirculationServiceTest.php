<?php

namespace Tests\Unit\Domain\Ranking;

use App\Domain\Ranking\Services\RankingReviewCirculationService;
use App\Models\RankingReviewCampaign;
use App\Models\Series;
use App\Models\User;
use App\Mail\RankingReviewMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RankingReviewCirculationServiceTest extends TestCase
{
    private string $previousConnection;
    private Series $series;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->previousConnection = DB::getDefaultConnection();
        config()->set('database.connections.ranking_review_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('ranking_review_test');
        $this->createBaseSchema();
        $migration = require database_path('migrations/2026_09_07_040000_create_ranking_review_circulations.php');
        $migration->up();

        DB::table('series')->insert([
            'id' => 17, 'name' => 'Overberg 2026', 'year' => 2026,
            'ranking_review_default_hours' => 24, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('categories')->insert(['id' => 13, 'name' => 'U/13 Boys']);
        $this->series = Series::findOrFail(17);
        $this->actor = (new User())->forceFill(['id' => 99, 'email' => 'admin@example.test']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('ranking_review_test');
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('ranking_review_test');
        parent::tearDown();
    }

    public function test_preview_deduplicates_family_email_and_reports_missing_player(): void
    {
        $this->addRankedPlayer(1, 'Nico', 'family@example.test', 1);
        $this->addRankedPlayer(2, 'Rome', 'FAMILY@example.test', 2);
        $this->addRankedPlayer(3, 'Riekie', null, 3);

        $preview = app(RankingReviewCirculationService::class)->preview($this->series);

        $this->assertSame(3, $preview['audience']['player_count']);
        $this->assertSame(1, $preview['audience']['recipient_count']);
        $this->assertSame(1, $preview['audience']['shared_email_count']);
        $this->assertSame(1, $preview['audience']['missing_email_count']);
        $this->assertSame('Riekie Player', $preview['audience']['missing'][0]['name']);
    }

    public function test_send_is_idempotent_and_records_exact_audience_and_delivery_log(): void
    {
        $this->addRankedPlayer(1, 'Nico', 'nico@example.test', 1);
        $this->addRankedPlayer(2, 'Riekie', null, 2);
        $uuid = (string) Str::uuid();
        $service = app(RankingReviewCirculationService::class);

        $campaign = $service->send(
            $this->series, $this->actor, $uuid, 'Check rankings', 'Please review.',
            'reply@example.test', now()->addDay(),
        );
        $again = $service->send(
            $this->series, $this->actor, $uuid, 'Changed', 'Changed',
            'changed@example.test', now()->addDays(2),
        );

        $this->assertSame($campaign->id, $again->id);
        $this->assertSame('Check rankings', $again->subject);
        $this->assertSame(2, $campaign->player_count);
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertSame(1, $campaign->missing_email_count);
        $this->assertSame(2, $campaign->recipients()->count());
        $this->assertDatabaseHas('bulk_email_logs', [
            'mail_type' => 'ranking_review', 'related_id' => $campaign->id,
            'recipient_email' => 'nico@example.test', 'status' => 'queued',
        ], 'ranking_review_test');
        $html = (new RankingReviewMail($campaign, ['Nico Player']))->render();
        $this->assertStringContainsString('Nico Player', $html);
        $this->assertStringContainsString('View provisional rankings', $html);
        $this->assertStringContainsString('ranking-review/'.$campaign->uuid, $html);
    }

    public function test_cutoff_and_snapshot_guards_protect_finalization(): void
    {
        $this->addRankedPlayer(1, 'Nico', 'nico@example.test', 1);
        $service = app(RankingReviewCirculationService::class);
        $campaign = $service->send(
            $this->series, $this->actor, (string) Str::uuid(), 'Check rankings',
            'Please review.', 'reply@example.test', now()->addHour(),
        );

        try {
            $service->assertReadyToFinalize($this->series);
            $this->fail('The open review cutoff should block finalization.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cutoff', $exception->errors());
        }

        $campaign->update(['cutoff_at' => now()->subMinute()]);
        DB::table('bulk_email_logs')->where('related_id', $campaign->id)->update(['status' => 'sent']);
        $this->assertSame($campaign->id, $service->assertReadyToFinalize($this->series)?->id);

        DB::table('series_rankings')->where('player_id', 1)->update(['total_points' => 9999]);
        $this->assertFalse($service->snapshotMatches($campaign));
    }

    private function addRankedPlayer(int $id, string $name, ?string $email, int $position): void
    {
        DB::table('players')->insert([
            'id' => $id, 'name' => $name, 'surname' => 'Player', 'email' => $email,
            'userId' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('series_rankings')->insert([
            'series_id' => 17, 'ranking_list_id' => 1, 'category_id' => 13,
            'player_id' => $id, 'rank_position' => $position,
            'total_points' => 1000 - ($position * 100), 'meta_json' => '[]',
            'status' => 'reviewed', 'run_id' => 'review-run',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createBaseSchema(): void
    {
        Schema::create('series', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->integer('year')->nullable(); $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->id(); $table->string('name');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id(); $table->string('email')->nullable();
        });
        Schema::create('players', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('surname');
            $table->string('email')->nullable(); $table->unsignedBigInteger('userId')->nullable(); $table->timestamps();
        });
        Schema::create('user_players', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('player_id');
        });
        Schema::create('series_rankings', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('series_id');
            $table->unsignedBigInteger('ranking_list_id')->nullable(); $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('player_id'); $table->unsignedInteger('rank_position');
            $table->unsignedInteger('total_points'); $table->longText('meta_json')->nullable();
            $table->string('status'); $table->string('run_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable(); $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable(); $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('bulk_email_logs', function (Blueprint $table): void {
            $table->id(); $table->string('mail_type'); $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable(); $table->string('recipient_email');
            $table->string('recipient_name')->nullable(); $table->string('status');
            $table->text('error_message')->nullable(); $table->json('payload')->nullable();
            $table->timestamp('queued_at')->nullable(); $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable(); $table->timestamp('skipped_at')->nullable(); $table->timestamps();
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id(); $table->string('log_name')->nullable(); $table->text('description');
            $table->nullableMorphs('subject'); $table->nullableMorphs('causer');
            $table->json('properties')->nullable(); $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable(); $table->timestamps();
        });
    }
}
