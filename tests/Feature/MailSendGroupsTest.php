<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, Event, User};
use App\Services\MailSendGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Tests\TestCase;

class MailSendGroupsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalSqlMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->originalSqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS sql_mode')->sql_mode;
            $modes = array_filter(explode(',', $this->originalSqlMode));
            $modes[] = 'ONLY_FULL_GROUP_BY';
            DB::statement('SET SESSION sql_mode = ?', [implode(',', array_unique($modes))]);
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->originalSqlMode !== null) {
                DB::statement('SET SESSION sql_mode = ?', [$this->originalSqlMode]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function log(Event $event, array $payload, string $status = 'sent'): BulkEmailLog
    {
        return BulkEmailLog::create(['mail_type' => 'event_email', 'recipient_email' => 'recipient@example.test', 'status' => $status, 'payload' => ['event_id' => $event->id, ...$payload]]);
    }

    public function test_groups_paginate_sends_across_all_recipients_and_separate_batch_preview_and_legacy_days(): void
    {
        $event = Event::factory()->create();
        for ($i = 0; $i < 30; $i++) $this->log($event, ['event_communication_batch_id' => 10, 'subject' => 'Same subject'], $i === 0 ? 'failed' : 'sent');
        $this->log($event, ['event_communication_batch_id' => 11, 'subject' => 'Same subject']);
        $this->log($event, ['preview_id' => 10, 'subject' => 'Same subject']);
        $legacy = $this->log($event, ['event_communication_batch_id' => null, 'preview_id' => null, 'subject' => 'Legacy']);
        $older = $this->log($event, ['event_communication_batch_id' => null, 'preview_id' => null, 'subject' => 'Legacy']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $query = BulkEmailLog::where('payload->event_id', $event->id);
        $groups = app(MailSendGroups::class)->paginate($query);
        $this->assertSame(5, $groups->total());
        $batch = $groups->firstWhere('send_key', 'batch:10');
        $this->assertSame(30, (int) $batch->recipient_count);
        $this->assertSame(1, (int) $batch->failed_count);
        $recipients = app(MailSendGroups::class)->recipients($query, $batch->representative_id);
        $this->assertSame(30, $recipients->total());
        $this->assertCount(25, $recipients);
        for ($i = 0; $i < 20; $i++) $this->log($event, ['event_communication_batch_id' => 100 + $i, 'subject' => 'Other send']);
        $page = app(MailSendGroups::class)->paginate($query);
        $this->assertSame(25, $page->total());
        $this->assertCount(15, $page);
        $this->assertStringContainsString('history_sends_page=2', $page->nextPageUrl());
    }

    public function test_recipient_endpoint_preserves_filters_event_isolation_and_does_not_send(): void
    {
        Mail::fake(); Queue::fake();
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $failed = $this->log($event, ['event_communication_batch_id' => 10, 'subject' => 'Selected send'], 'failed');
        $sent = $this->log($event, ['event_communication_batch_id' => 10, 'subject' => 'Selected send']);
        $foreign = $this->log(Event::factory()->create(), ['event_communication_batch_id' => 10, 'subject' => 'Foreign secret']);
        $url = route('backend.event-communications.index', ['event' => $event, 'history_send' => $failed->id, 'history_outcome' => 'failed']);
        $this->actingAs($actor)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Selected send')->assertDontSee('Foreign secret')->assertViewHas('sendRecipients', fn ($logs) => $logs->total() === 1);
        $this->get(route('backend.event-communications.index', ['event' => $event, 'history_send' => $foreign->id]))->assertNotFound();
        $this->get(route('backend.event-communications.index', ['event' => $event, 'history_send' => $sent->id, 'history_outcome' => 'failed']))->assertNotFound();
        $this->get(route('backend.event-communications.index', ['event' => $event]))->assertOk()->assertSee('data-mail-send', false)->assertDontSee('recipient@example.test')->assertViewHas('historyReport', fn ($report) => $report['groups']->total() === 1);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        Mail::assertNothingSent(); Queue::assertNothingPushed();
    }
    public function test_related_batch_fallback_and_region_scoped_shared_send_do_not_expose_other_regions(): void
    {
        Mail::fake(); Queue::fake();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Team event', 'type' => \App\Models\EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type]);
        $manager = User::factory()->create();
        $region = \App\Models\TeamRegion::create(['region_name' => 'Allowed region']);
        $otherRegion = \App\Models\TeamRegion::create(['region_name' => 'Private region']);
        $eventRegion = DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id]);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $otherRegion->id]);
        \App\Models\EventRegionManager::create(['event_id' => $event->id, 'region_id' => $region->id, 'event_region_id' => $eventRegion, 'user_id' => $manager->id]);
        $allowed = $this->log($event, ['subject' => 'Shared send', 'region_id' => $region->id], 'acceptance_unknown');
        $private = $this->log($event, ['subject' => 'Private recipient', 'region_id' => $otherRegion->id]);
        foreach ([$allowed, $private] as $log) $log->forceFill(['related_type' => \App\Models\EventCommunicationBatch::class, 'related_id' => 555])->save();
        $this->actingAs($manager)->get(route('backend.event-communications.index', ['event' => $event]))
            ->assertOk()->assertDontSee('Private recipient')->assertViewHas('historyReport', function ($report) {
                $group = $report['groups']->first();
                return $report['groups']->total() === 1 && $group->send_key === 'batch:555' && (int) $group->recipient_count === 1 && (int) $group->uncertain_count === 1;
            });
        $this->get(route('backend.event-communications.index', ['event' => $event, 'history_send' => $allowed->id]))
            ->assertOk()->assertDontSee('Private recipient')->assertViewHas('sendRecipients', fn ($logs) => $logs->total() === 1);
        $this->get(route('backend.event-communications.index', ['event' => $event, 'history_send' => $private->id]))->assertNotFound();
        Mail::assertNothingSent(); Queue::assertNothingPushed();
    }

}


