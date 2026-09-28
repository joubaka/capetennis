<?php

namespace Tests\Feature;

use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Models\CategoryEvent;
use App\Models\BulkEmailLog;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Models\User;
use App\Services\MailAccountManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialInvitationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private CategoryEvent $category;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $typeId = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => EventType::INDIVIDUAL,
            'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $this->event = Event::factory()->create(['eventType' => $typeId]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    public function test_nomination_update_is_authorized_event_scoped_and_preserves_unchanged_rows(): void
    {
        $first = Player::factory()->create(); $second = Player::factory()->create();
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $first->id]);
        $this->actingAs($this->admin)->post('/backend/nomination/save', ['category_event_id' => $this->category->id, 'player_ids' => [$first->id, $second->id]])->assertOk();
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id]);
        $this->assertSame(2, EventNomination::where('event_id', $this->event->id)->count());

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $this->actingAs($this->admin)->post('/backend/nomination/save', ['category_event_id' => $otherCategory->id, 'player_ids' => [$first->id]])->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/backend/nomination/selected/{$this->category->id}")->assertForbidden();
    }

    public function test_prepare_review_and_send_are_exact_idempotent_and_create_no_financial_records(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id, 'email' => 'stale-player@example.test']);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $url = route('backend.interprovincial-trials.invitations.prepare', $this->event);
        $this->actingAs($this->admin)->post($url)->assertRedirect();
        $this->actingAs($this->admin)->post($url)->assertRedirect();
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->assertSame(1, $batch->invitations()->count());

        $this->assertSame($owner->email, $batch->invitations()->sole()->recipient_email);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), ['snapshot_hash' => $batch->snapshot_hash])->assertRedirect();
        $send = route('backend.interprovincial-trials.batches.send', [$this->event, $batch]);
        $this->actingAs($this->admin)->post($send)->assertRedirect();
        $this->actingAs($this->admin)->post($send)->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 1);
        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_batch_and_player_access_reject_cross_event_and_unlinked_accounts(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole(); $invitation = InterprovincialTrialInvitation::sole();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$other, $batch]), ['snapshot_hash' => $batch->snapshot_hash])->assertNotFound();
        $signed = URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->addMinute(), ['invitation' => $invitation]);
        $this->actingAs(User::factory()->create())->get($signed)->assertForbidden();

        $this->actingAs($owner)->get($signed)->assertNotFound();
        $invitation->update(['status' => 'queued']);
        $this->actingAs($owner)->get($signed)->assertOk()->assertSee('Registration will open later');
        $this->actingAs($owner)->get($signed.'&tampered=1')->assertForbidden();
        $expired = URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->subMinute(), ['invitation' => $invitation]);
        $this->actingAs($owner)->get($expired)->assertForbidden();
    }

    public function test_recipient_requires_an_authorized_account_and_is_deterministic(): void
    {
        $direct = User::factory()->create(['email' => 'direct@example.test']);
        $firstLink = User::factory()->create(['email' => 'first@example.test']);
        $secondLink = User::factory()->create(['email' => 'second@example.test']);
        $player = Player::factory()->create(['userId' => $direct->id, 'email' => 'stale@example.test']);
        DB::table('user_players')->insert([
            ['user_id' => $secondLink->id, 'player_id' => $player->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $firstLink->id, 'player_id' => $player->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $this->assertSame('direct@example.test', InterprovincialTrialInvitation::sole()->recipient_email);

        $player->update(['userId' => null]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $expected = $firstLink->id < $secondLink->id ? $firstLink->email : $secondLink->email;
        $this->assertSame($expected, InterprovincialTrialInvitation::sole()->recipient_email);

        DB::table('user_players')->where('player_id', $player->id)->delete();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $this->assertNull(InterprovincialTrialInvitation::sole()->recipient_email);
    }

    public function test_stale_snapshot_unassigned_admin_super_user_and_malformed_nomination_boundaries(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $unassigned = User::factory()->create()->assignRole('admin');
        $this->actingAs($unassigned)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event))->assertForbidden();
        $super = User::factory()->create()->assignRole('super-user');
        $this->actingAs($super)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event))->assertRedirect();
        $batch = InterprovincialTrialInvitationBatch::sole();
        $oldHash = $batch->snapshot_hash;
        $owner->update(['email' => 'changed@example.test']);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $this->assertNotSame($oldHash, $batch->fresh()->snapshot_hash);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), ['snapshot_hash' => $oldHash])->assertSessionHasErrors('batch');

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $nomination->update(['event_id' => $other->id]);
        $this->actingAs($this->admin)->post(route('backend.nomination.remove'), ['nomination_id' => $nomination->id])->assertNotFound();
    }

    public function test_terminal_mail_failure_can_retry_without_a_second_dispatch_record_or_duplicate_send(): void
    {
        Bus::fake();
        config(['mail.default' => 'array']);
        $this->mock(MailAccountManager::class, fn ($mock) => $mock->shouldReceive('getMailer')->andReturn('array'));
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), ['snapshot_hash' => $batch->snapshot_hash]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]));
        $log = BulkEmailLog::sole();
        $job = new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id);
        $job->failed(new \RuntimeException('Transport unavailable'));
        $this->assertSame('failed', InterprovincialTrialInvitation::sole()->status);

        $job->handle();
        $job->handle();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('sent', InterprovincialTrialInvitation::sole()->status);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertCount(1, \Illuminate\Support\Facades\Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_authorized_retry_reuses_the_failed_log_and_dispatch_with_nested_isolation(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), ['snapshot_hash' => $batch->snapshot_hash]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]));
        $invitation = InterprovincialTrialInvitation::sole();
        $log = BulkEmailLog::sole();
        (new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id))->failed(new \RuntimeException('Terminal transport failure'));

        $retry = route('backend.interprovincial-trials.invitations.retry', [$this->event, $batch, $invitation]);
        $unassigned = User::factory()->create()->assignRole('admin');
        $this->actingAs($unassigned)->post($retry)->assertForbidden();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.retry', [$other, $batch, $invitation]))->assertNotFound();

        $this->actingAs($this->admin)->post($retry)->assertRedirect()->assertSessionHas('success', 'The failed invitation was queued for retry.');
        $this->actingAs($this->admin)->post($retry)->assertRedirect()->assertSessionHas('success', 'The invitation is already queued, sending, or sent.');
        $this->assertSame('queued', $invitation->fresh()->status);
        $this->assertSame('queued', $log->fresh()->status);
        $this->assertDatabaseCount('interprovincial_trial_invitations', 1);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 2);
    }
}
