<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MastersInvitationBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersInvitationDeadlineManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-08 10:00:00');
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->superUser = User::factory()->create()->assignRole('super-user');
        $mastersTypeId = DB::table('eventtypes')->where('code', 'masters')->value('id');
        $this->event = Event::factory()->create(['eventType' => $mastersTypeId]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sent_batch_deadlines_can_only_be_extended_without_changing_batch_status(): void
    {
        $batch = $this->batch('sent');

        $this->actingAs($this->superUser)
            ->from(route('backend.masters.show', $batch))
            ->patch(route('backend.masters.deadlines.extend', $batch), [
                'response_deadline' => '2026-09-12 12:00:00',
                'payment_deadline' => '2026-09-13 12:00:00',
                'replacement_payment_deadline' => '2026-09-14 12:00:00',
            ])
            ->assertRedirect(route('backend.masters.show', $batch))
            ->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('sent', $batch->status);
        $this->assertSame('2026-09-12 12:00:00', $batch->response_deadline->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-13 12:00:00', $batch->payment_deadline->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-14 12:00:00', $batch->replacement_payment_deadline->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'masters',
            'description' => 'Masters invitation deadlines extended',
            'subject_id' => $batch->id,
        ]);
    }

    public function test_sent_batch_deadlines_cannot_be_shortened_or_put_out_of_order(): void
    {
        $batch = $this->batch('sent');

        $this->actingAs($this->superUser)
            ->from(route('backend.masters.show', $batch))
            ->patch(route('backend.masters.deadlines.extend', $batch), [
                'response_deadline' => '2026-09-09 12:00:00',
                'payment_deadline' => '2026-09-11 12:00:00',
                'replacement_payment_deadline' => '2026-09-12 12:00:00',
            ])
            ->assertRedirect(route('backend.masters.show', $batch))
            ->assertSessionHasErrors('deadlines');

        $this->assertSame('2026-09-10 12:00:00', $batch->fresh()->response_deadline->format('Y-m-d H:i:s'));

        $this->actingAs($this->superUser)
            ->from(route('backend.masters.show', $batch))
            ->patch(route('backend.masters.deadlines.extend', $batch), [
                'response_deadline' => '2026-09-13 12:00:00',
                'payment_deadline' => '2026-09-12 12:00:00',
                'replacement_payment_deadline' => '2026-09-14 12:00:00',
            ])
            ->assertRedirect(route('backend.masters.show', $batch))
            ->assertSessionHasErrors('payment_deadline');
    }

    public function test_deadline_extension_requires_a_sent_batch_and_an_authorized_event_admin(): void
    {
        $batch = $this->batch('generated');
        $deadlines = [
            'response_deadline' => '2026-09-12 12:00:00',
            'payment_deadline' => '2026-09-13 12:00:00',
            'replacement_payment_deadline' => '2026-09-14 12:00:00',
        ];

        $this->actingAs($this->superUser)
            ->from(route('backend.masters.show', $batch))
            ->patch(route('backend.masters.deadlines.extend', $batch), $deadlines)
            ->assertSessionHasErrors('deadlines');

        $ordinaryUser = User::factory()->create();
        $batch->update(['status' => 'sent']);
        $this->actingAs($ordinaryUser)
            ->patch(route('backend.masters.deadlines.extend', $batch), $deadlines)
            ->assertForbidden();

        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherEventAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $otherEvent->id,
            'user_id' => $otherEventAdmin->id,
        ]);
        $this->actingAs($otherEventAdmin)
            ->patch(route('backend.masters.deadlines.extend', $batch), $deadlines)
            ->assertForbidden();
    }

    public function test_original_details_endpoint_cannot_reopen_a_sent_batch(): void
    {
        $batch = $this->batch('sent');

        $this->actingAs($this->superUser)
            ->from(route('backend.masters.show', $batch))
            ->patch(route('backend.masters.details.update', $batch), [
                'response_deadline' => '2026-09-12 12:00:00',
                'payment_deadline' => '2026-09-13 12:00:00',
                'replacement_payment_deadline' => '2026-09-14 12:00:00',
            ])
            ->assertSessionHasErrors('deadlines');

        $batch->refresh();
        $this->assertSame('sent', $batch->status);
        $this->assertSame('2026-09-10 12:00:00', $batch->response_deadline->format('Y-m-d H:i:s'));
    }

    public function test_dashboard_restores_invitation_setup_and_unsent_restart_actions(): void
    {
        $batch = $this->batch('generated');

        $this->actingAs($this->superUser)
            ->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSee('Invitation setup')
            ->assertSee('Restart invitation batch')
            ->assertSee(route('backend.masters.restart', $batch), false)
            ->assertDontSee('Masters selection setup');

        $batch->update(['status' => 'sent']);

        $this->actingAs($this->superUser)
            ->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertDontSee('Restart invitation batch');

        $this->actingAs($this->superUser)
            ->get(route('backend.masters.show', $batch))
            ->assertOk()
            ->assertSee('Extend invitation deadlines')
            ->assertSee(route('backend.masters.deadlines.extend', $batch), false);
    }

    private function batch(string $status): MastersInvitationBatch
    {
        return MastersInvitationBatch::create([
            'event_id' => $this->event->id,
            'series_id' => 1,
            'ranking_run_id' => 'published-run',
            'created_by' => $this->superUser->id,
            'top_x' => 8,
            'status' => $status,
            'response_deadline' => '2026-09-10 12:00:00',
            'payment_deadline' => '2026-09-11 12:00:00',
            'replacement_payment_deadline' => '2026-09-12 12:00:00',
        ]);
    }
}
