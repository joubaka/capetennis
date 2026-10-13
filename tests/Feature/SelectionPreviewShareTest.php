<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, CategoryEvent, Event, SelectionPreviewGrant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectionPreviewShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_is_idempotent_account_scoped_read_only_and_has_exact_canonical_email_links(): void
    {
        [$event, $actor] = $this->scenario();
        $recipients = User::factory()->count(2)->create();
        $url = route('backend.selection-preview-share.store', $event);
        $input = ['request_key' => (string) Str::uuid(), 'recipient_ids' => $recipients->pluck('id')->all(), 'preview_url' => 'https://attacker.invalid', 'body' => 'forged'];
        $this->postJson($url, $input)->assertOk()->assertJsonPath('recipients', 2)->assertJsonPath('queued', 2)->assertJsonPath('accepted', 0);
        $this->postJson($url, $input)->assertOk();
        $this->assertDatabaseCount('selection_preview_grants', 2);
        $this->assertDatabaseCount('selection_preview_share_batches', 1);
        $this->assertDatabaseCount('selection_preview_access_events', 2);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        foreach (BulkEmailLog::all() as $log) {
            $this->assertContains($log->recipient_email, $recipients->pluck('email')->all());
            $this->assertSame(rtrim(config('app.url'), '/').route('backend.selection-preview.show', $event, false), $log->payload['preview_url']);
            $this->assertStringNotContainsString('attacker.invalid', $log->payload['body']);
            $this->assertSame('selection_preview_share', $log->mail_type);
        }
        $this->postJson($url, array_replace($input, ['recipient_ids' => [$recipients[0]->id]]))->assertConflict();
        $viewer = $recipients->first();
        $this->actingAs($viewer)->get(route('backend.selection-preview.show', $event))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('id="preview-team-setup"', false)->assertDontSee('Share preview')->assertDontSee('Back to Result Ranks');
        $this->putJson(route('backend.team-selection-size.store', $event), ['team_size' => 10, 'reserve_count' => 2])->assertForbidden();
        $this->putJson(route('backend.team-result-selection.store', $event), [])->assertForbidden();
        $this->postJson($url, $input)->assertForbidden();
        $this->get(route('backend.selection-preview-share.index', $event))->assertForbidden();
        $other = Event::factory()->create();
        $this->get(route('backend.selection-preview.show', $other))->assertForbidden();
        $this->assertFalse($viewer->can('event.manage', $event));
        $this->actingAs($actor)->deleteJson(route('backend.selection-preview-share.destroy', ['event' => $event, 'user' => $viewer]))->assertOk();
        $this->actingAs($viewer)->get(route('backend.selection-preview.show', $event))->assertForbidden();
        $this->assertDatabaseCount('selection_preview_access_events', 3);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        Mail::assertNothingSent();
    }

    public function test_sender_authorization_recipient_validation_pagination_and_regrant_are_bounded(): void
    {
        [$event, $actor] = $this->scenario();
        $users = User::factory()->count(17)->create(['name' => 'Convener']);
        $url = route('backend.selection-preview-share.store', $event);
        $this->get(route('backend.selection-preview-share.index', ['event' => $event, 'q' => 'Convener']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee($users[0]->email)->assertSee('Send preview link');
        foreach ([[], [999999], [$users[0]->id, $users[0]->id], $users->pluck('id')->concat(range(9000, 9014))->all()] as $ids) {
            $this->postJson($url, ['request_key' => (string) Str::uuid(), 'recipient_ids' => $ids])->assertUnprocessable();
        }
        $input = ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$users[0]->id]];
        $this->postJson($url, $input)->assertOk();
        $revoke = route('backend.selection-preview-share.destroy', ['event' => $event, 'user' => $users[0]]);
        $this->deleteJson($revoke)->assertOk();
        $this->deleteJson($revoke)->assertOk();
        $this->postJson($url, $input)->assertOk(); // Replaying an old request does not regrant revoked access.
        $this->assertNotNull(SelectionPreviewGrant::first()->revoked_at);
        $input['request_key'] = (string) Str::uuid();
        $this->postJson($url, $input)->assertOk();
        $this->assertNull(SelectionPreviewGrant::first()->revoked_at);
        $this->assertDatabaseCount('selection_preview_access_events', 3);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $other = Event::factory()->create();
        $this->postJson(route('backend.selection-preview-share.store', $other), $input)->assertForbidden();
        $this->deleteJson(route('backend.selection-preview-share.destroy', ['event' => $other, 'user' => $users[0]]))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_mail_builder_checks_revoked_grant_and_current_recipient_email_before_send(): void
    {
        [$event] = $this->scenario();
        $user = User::factory()->create();
        $this->postJson(route('backend.selection-preview-share.store', $event), ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$user->id]])->assertOk();
        $log = BulkEmailLog::first();
        $job = new class($log->id) extends SendBulkEmailJob {
            public function mailFor(BulkEmailLog $log) { return $this->buildMailable($log, 'test@example.com'); }
        };
        $this->assertInstanceOf(\App\Mail\BulkEventMail::class, $job->mailFor($log));
        $user->update(['email' => 'changed@example.com']);
        $this->assertNull($job->mailFor($log));
        $user->update(['email' => $log->recipient_email]);
        SelectionPreviewGrant::first()->update(['revoked_at' => now()]);
        $this->assertNull($job->mailFor($log));
        Mail::assertNothingSent();
    }

    public function test_canonical_share_passes_event_email_review_and_tampered_content_is_rejected(): void
    {
        [$event] = $this->scenario();
        $event->update(['eventType' => 3]);
        $user = User::factory()->create();
        $this->postJson(route('backend.selection-preview-share.store', $event), ['request_key' => (string) Str::uuid(), 'recipient_ids' => [$user->id]])->assertOk();
        $log = BulkEmailLog::first();
        $log->update(['status' => 'sending']);
        $mailable = app(\App\Services\SelectionPreviewShareService::class)->authorizedMailable($log);
        $message = (new \Symfony\Component\Mime\Email)->to($user->email)->subject($mailable->subjectLine)->html($mailable->body);
        $listener = app(\App\Listeners\RequireEventEmailReview::class);
        $this->assertNull($listener->handle(new \Illuminate\Mail\Events\MessageSending($message, ['selection_preview_log_id' => $log->id, 'review_event' => $event])));
        $message->html('<p>Unapproved content</p>');
        try {
            $listener->handle(new \Illuminate\Mail\Events\MessageSending($message, ['selection_preview_log_id' => $log->id]));
            $this->fail('Changed preview content must not be sent.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('event_communication_batches', 0);
        Mail::assertNothingSent();
    }

    private function scenario(): array
    {
        Bus::fake(); Mail::fake(); $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $actor->adminEvents()->attach($event);
        $this->actingAs($actor);
        return [$event, $actor];
    }
}
