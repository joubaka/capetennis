<?php

namespace Tests\Feature;

use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private CategoryEvent $category;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Regional Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $this->event = Event::factory()->create(['eventType' => $type]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    private function invitation(string $status): InterprovincialTrialInvitation
    {
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nominee_name' => 'New', 'nominee_surname' => 'Nominee']);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $this->admin->id]);
        return InterprovincialTrialInvitation::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id, 'batch_id' => $batch->id, 'status' => $status]);
    }

    public function test_category_fee_requires_event_authority_and_trials_cannot_override_it(): void
    {
        $url = route('admin.events.category-fee.update', $this->category);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->patchJson($url, ['entry_fee' => 500])->assertForbidden();
        $this->actingAs($this->admin)->patchJson($url, ['entry_fee' => 500])->assertUnprocessable()->assertJsonValidationErrors('entry_fee');
        $this->patchJson($url, ['enabled' => false])->assertOk();
        $this->assertNull($this->category->fresh()->entry_fee);
    }

    public function test_pending_and_paid_nominations_cannot_be_deleted(): void
    {
        foreach (['accepted_pending_payment', 'paid_confirmed', 'queued'] as $status) {
            $invitation = $this->invitation($status);
            $this->actingAs($this->admin)->deleteJson(route('backend.interprovincial-trials.nominations.destroy', [$this->event, $this->category, $invitation->nomination_id]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('event_nominations', 3);
    }

    public function test_categories_with_nomination_history_are_not_deleted_or_cleaned_up(): void
    {
        $this->invitation('sent');
        $empty = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->actingAs($this->admin)->deleteJson(route('admin.category.delete', $this->category))->assertUnprocessable();
        $this->deleteJson(route('admin.categories.cleanup', $this->event))->assertOk()->assertJsonPath('removed', 1);
        $this->assertDatabaseHas('category_events', ['id' => $this->category->id]);
        $this->assertDatabaseMissing('category_events', ['id' => $empty->id]);
    }

    public function test_mail_failure_never_downgrades_registration_and_requires_manual_retry(): void
    {
        foreach (['accepted_pending_payment', 'paid_confirmed', 'withdrawn', 'declined'] as $status) {
            $invitation = $this->invitation($status);
            $log = BulkEmailLog::create(['mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => 'family@example.test', 'status' => 'queued', 'payload' => ['kind' => 'initial']]);
            $job = new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id);
            $job->failed(new \RuntimeException('Transport unavailable'));
            $this->assertSame($status, $invitation->fresh()->status);
            $this->assertSame('failed', $log->fresh()->status);
            $this->assertSame(0, $job->tries);
        }
    }

    public function test_profileless_status_is_visible_across_batches_without_duplicate_counts(): void
    {
        $invitation = $this->invitation('paid_confirmed');
        $controller = app(\App\Http\Controllers\Backend\InterprovincialTrialInvitationController::class);
        $method = new \ReflectionMethod($controller, 'nominationInvitations');
        $items = $method->invoke($controller, $this->event, collect([$invitation->nomination]));
        $this->assertCount(1, $items);
        $this->assertSame($invitation->id, $items->first()->id);
        $prepared = $invitation->replicate();
        $batch = $invitation->batch->replicate();
        $batch->save();
        $prepared->batch_id = $batch->id;
        $prepared->status = 'prepared';
        $prepared->save();
        $items = $method->invoke($controller, $this->event, collect([$invitation->nomination]));
        $this->assertCount(1, $items);
        $this->assertSame('paid_confirmed', $items->first()->status);
    }

    public function test_moves_are_blocked_when_source_or_target_has_a_draw(): void
    {
        $target = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $this->category->id]);
        foreach ([$this->category, $target] as $category) {
            $draw = \App\Models\Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $category->id]);
            $this->actingAs($this->admin)->postJson(route('admin.category.movePlayer', $entry->id), ['new_category_id' => $target->id])->assertUnprocessable();
            $this->assertSame($this->category->id, $entry->fresh()->category_event_id);
            $draw->delete();
        }
    }

    public function test_overview_counts_registered_nominee_when_latest_batch_is_only_a_draft(): void
    {
        $invitation = $this->invitation('paid_confirmed');
        $batch = $invitation->batch->replicate();
        $batch->save();
        $prepared = $invitation->replicate();
        $prepared->batch_id = $batch->id;
        $prepared->status = 'prepared';
        $prepared->save();
        $this->actingAs($this->admin)->get(route('admin.events.overview', $this->event))
            ->assertOk()->assertViewHas('interproStats', fn ($stats) => $stats['paidConfirmed'] === 1);
    }

    public function test_initial_mail_success_does_not_overwrite_a_concurrent_acceptance(): void
    {
        $invitation = $this->invitation('queued');
        $log = BulkEmailLog::create(['mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => 'family@example.test', 'status' => 'queued', 'payload' => ['kind' => 'initial', 'rendered_html' => '<p>Reviewed</p>', 'rendered_subject' => 'Reviewed', 'from_address' => 'sender@example.test', 'from_name' => 'Cape Tennis', 'reply_to' => 'sender@example.test']]);
        $this->mock(\App\Services\InvitationMailSecurity::class, fn ($mock) => $mock->shouldReceive('logMatchesSignedSnapshot')->once()->andReturn(true));
        $this->mock(\App\Services\MailAccountManager::class, fn ($mock) => $mock->shouldReceive('getMailer')->once()->andReturn('array'));
        $mailer = \Mockery::mock();
        $mailer->shouldReceive('to')->with('family@example.test')->once()->andReturnSelf();
        $mailer->shouldReceive('sendNow')->once()->andReturnUsing(function () use ($invitation) {
            $invitation->update(['status' => 'accepted_pending_payment']);
            $email = (new \Symfony\Component\Mime\Email)->from('sender@example.test')->to('family@example.test')->text('Reviewed');
            return new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($email, \Symfony\Component\Mailer\Envelope::create($email)));
        });
        $mailer->shouldReceive('getSymfonyTransport')->once()->andReturn(new \Illuminate\Mail\Transport\ArrayTransport);
        \Illuminate\Support\Facades\Mail::shouldReceive('mailer')->once()->with('array')->andReturn($mailer);
        (new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id))->handle();
        $this->assertSame('accepted_pending_payment', $invitation->fresh()->status);
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_initial_review_shows_each_exact_message_without_creating_invitations(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['mail.from.address' => 'sender@example.test', 'mail.from.name' => 'Cape Tennis']);
        foreach (['First', 'Second'] as $name) {
            EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id,
                'nominee_name' => $name, 'nominee_surname' => 'Nominee', 'nominee_email' => strtolower($name).'@example.test']);
        }
        $service = app(\App\Services\InterprovincialTrials\InvitationService::class);
        $preview = $service->previewAttempt($this->event, $this->admin, 'profileless');
        $this->assertStringContainsString('Hello First Nominee', $preview['rendered_body']);
        $this->assertStringContainsString('Hello Second Nominee', $preview['rendered_body']);
        $this->assertStringNotContainsString('[recipient name]', $preview['rendered_body']);
        $this->assertDatabaseCount('interprovincial_trial_invitations', 0);
        $result = $service->queueAttempt($this->event, $this->admin, $preview);
        $this->assertSame(2, $result['queued_count']);
        foreach (BulkEmailLog::where('mail_type', 'interprovincial_trial_invitation')->get() as $log) {
            $this->assertStringContainsString($log->payload['rendered_html'], $preview['rendered_body']);
            $this->assertTrue(app(\App\Services\InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->event->id));
        }
    }
}
