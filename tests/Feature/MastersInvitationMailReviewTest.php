<?php

namespace Tests\Feature;

use App\Models\BulkEmailLog;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\User;
use App\Services\InvitationMailSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendMastersInvitationEmailJob;
use App\Services\Masters\RetryMastersInvitationEmails;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersInvitationMailReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private MastersInvitationBatch $batch;
    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'info@capetennis.co.za', 'mail.from.name' => 'Cape Tennis', 'mail.allowed_from_addresses' => ['masters@example.test']]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $typeId = DB::table('eventtypes')->where('code', 'masters')->value('id');
        $this->event = Event::factory()->create(['eventType' => $typeId, 'name' => 'Test Masters']);
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->batch = MastersInvitationBatch::create(['event_id' => $this->event->id, 'series_id' => 1, 'ranking_run_id' => 'mail-review', 'created_by' => $this->admin->id, 'top_x' => 1, 'status' => 'ready_for_invitation', 'response_deadline' => now()->addDay(), 'payment_deadline' => now()->addDays(2), 'replacement_payment_deadline' => now()->addDays(3)]);
        $this->recipient = User::factory()->create(['email' => 'invitee@example.test']);
        $player = Player::factory()->create(['userId' => $this->recipient->id, 'name' => 'Exact', 'surname' => 'Invitee']);
        MastersInvitation::create(['batch_id' => $this->batch->id, 'event_id' => $this->event->id, 'category_event_id' => $category->id, 'player_id' => $player->id, 'ranking_position' => 1, 'queue_position' => 1, 'status' => MastersInvitation::INVITED]);
        $blocked = Player::factory()->create(['userId' => null, 'name' => 'Blocked', 'surname' => 'Invitee']);
        MastersInvitation::create(['batch_id' => $this->batch->id, 'event_id' => $this->event->id, 'category_event_id' => $category->id, 'player_id' => $blocked->id, 'ranking_position' => 2, 'queue_position' => 2, 'status' => MastersInvitation::INVITED]);
    }

    public function test_review_search_preserves_every_invitee_and_mail_proof(): void
    {
        $response = $this->actingAs($this->admin)->get(route('backend.masters.review', $this->batch))->assertOk()
            ->assertSee('masters-category-search')->assertSee('Blocked Invitee')
            ->assertSee('Exact Invitee')->assertSee('recipient_hash')->assertSee('review_proof');
        $this->assertDatabaseCount('masters_invitations', 2);
        $this->writeBatchPreview('masters', $response->getContent());
    }

    private function writeBatchPreview(string $name, string $html): void
    {
        if (getenv('BATCH2326_QA') === '1') {
            $path = storage_path('app/batches2326-qa');
            if (!is_dir($path)) { mkdir($path, 0777, true); }
            file_put_contents($path.'/'.$name.'.html', $html);
        }
    }

    public function test_authorized_preview_returns_exact_recipients_blockers_and_defaults(): void
    {
        $this->actingAs($this->admin)->postJson(route('backend.masters.send-invitations.preview', $this->batch), [])
            ->assertOk()->assertJsonCount(1, 'recipients')->assertJsonCount(1, 'blockers')
            ->assertJsonPath('recipients.0.email', 'invitee@example.test')
            ->assertJsonPath('blockers.0.name', 'Blocked Invitee')
            ->assertJsonPath('from_address', 'info@capetennis.co.za')
            ->assertJsonPath('from_name', 'Cape Tennis');
    }

    public function test_preview_and_send_require_batch_event_authorization(): void
    {
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->postJson(route('backend.masters.send-invitations.preview', $this->batch), [])->assertForbidden();
        $this->actingAs($outsider)->post(route('backend.masters.send-invitations', $this->batch), [])->assertForbidden();
    }

    public function test_proof_rejects_other_actor_staleness_and_recipient_drift_then_exact_token_is_idempotent(): void
    {
        Bus::fake();
        $composition = ['subject' => 'Custom Masters', 'body' => 'Custom invitation body.', 'from_address' => 'masters@example.test', 'from_name' => 'Masters Desk', 'reply_to' => 'reply@example.test'];
        $preview = $this->actingAs($this->admin)->postJson(route('backend.masters.send-invitations.preview', $this->batch), $composition)->assertOk()->json();
        $payload = $composition + collect($preview)->only(['request_token', 'recipient_hash', 'composition_hash', 'review_proof', 'review_expires_at'])->all();

        $otherAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $otherAdmin->id]);
        $this->actingAs($otherAdmin)->post(route('backend.masters.send-invitations', $this->batch), $payload)->assertSessionHasErrors('review');

        $this->recipient->update(['email' => 'changed@example.test']);
        $this->actingAs($this->admin)->post(route('backend.masters.send-invitations', $this->batch), $payload)->assertSessionHasErrors('recipients');
        $this->recipient->update(['email' => 'invitee@example.test']);

        $payload['review_expires_at'] = now()->subMinute()->getTimestamp();
        $payload['review_proof'] = app(InvitationMailSecurity::class)->reviewProof($this->admin->id, 'masters:'.$this->batch->id.':'.$this->event->id, $payload['request_token'], $payload['recipient_hash'], $payload['composition_hash'], $payload['review_expires_at']);
        $this->actingAs($this->admin)->post(route('backend.masters.send-invitations', $this->batch), $payload)->assertSessionHasErrors('review');

        $preview = $this->actingAs($this->admin)->postJson(route('backend.masters.send-invitations.preview', $this->batch), $composition)->assertOk()->json();
        $payload = $composition + collect($preview)->only(['request_token', 'recipient_hash', 'composition_hash', 'review_proof', 'review_expires_at'])->all();
        $this->actingAs($this->admin)->post(route('backend.masters.send-invitations', $this->batch), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('backend.masters.send-invitations', $this->batch), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bulk_email_logs', 1);
        $log = BulkEmailLog::sole();
        $this->assertSame('invitee@example.test', $log->recipient_email);
        $this->assertSame('Custom Masters', $log->payload['subject']);
        $this->assertSame($payload['request_token'], $log->payload['request_token']);
        $this->assertSame(app(InvitationMailSecurity::class)->payloadIntegrity($log->payload), $log->payload['payload_integrity']);
    }

    public function test_job_rejects_tampered_recipient_column_before_transport(): void
    {
        Mail::fake();
        $log = $this->signedQueuedLog();
        $log->update(['recipient_email' => 'attacker@example.test']);

        (new SendMastersInvitationEmailJob($log->id, $this->event->id))->handle();

        $this->assertSame('skipped', $log->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_job_rejects_tampered_related_id_before_transport(): void
    {
        Mail::fake();
        $log = $this->signedQueuedLog();
        $log->update(['related_id' => $log->related_id + 9999]);

        (new SendMastersInvitationEmailJob($log->id, $this->event->id))->handle();

        $this->assertSame('skipped', $log->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_unsigned_legacy_failed_log_is_rebuilt_from_canonical_invitation_before_retry(): void
    {
        Bus::fake();
        $log = $this->signedQueuedLog();
        $log->update(['status' => 'failed', 'recipient_email' => 'mutable@example.test', 'payload' => ['invitation_id' => $log->related_id, 'kind' => 'invitation']]);

        $report = app(RetryMastersInvitationEmails::class)->run($this->event->id, true);

        $this->assertSame(1, $report['queued']);
        $log->refresh();
        $this->assertSame('invitee@example.test', $log->recipient_email);
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->event->id));
        Bus::assertDispatched(SendMastersInvitationEmailJob::class, fn ($job) => $job->logId === $log->id);
    }

    private function signedQueuedLog(): BulkEmailLog
    {
        $invitation = MastersInvitation::query()->whereHas('player', fn ($query) => $query->where('userId', $this->recipient->id))->firstOrFail();
        $payload = ['invitation_id' => $invitation->id, 'event_id' => $this->event->id, 'kind' => 'invitation', 'recipient_email' => 'invitee@example.test', 'recipient_name' => 'Exact Invitee', 'related_type' => MastersInvitation::class, 'related_id' => $invitation->id];
        $payload['payload_integrity'] = app(InvitationMailSecurity::class)->payloadIntegrity($payload);
        return BulkEmailLog::create(['mail_type' => 'masters_invitation', 'related_type' => MastersInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => 'invitee@example.test', 'recipient_name' => 'Exact Invitee', 'status' => 'queued', 'payload' => $payload, 'queued_at' => now()]);
    }
}
