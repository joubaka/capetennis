<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Player;
use App\Models\User;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRegistrationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registration_is_created_as_paid_through_canonical_entry_service(): void
    {
        Mail::fake();
        SiteSetting::set('player_email_on_admin_entry', '1', SiteSetting::GROUP_EMAIL);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $player = Player::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $this->actingAs($admin)->post(route('register.admin'), [
            'player_id' => $player->id,
            'categoryEvent' => $categoryEvent->id,
        ])->assertOk()->assertSee('success');

        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $categoryEvent->id,
            'user_id' => $admin->id,
            'status' => 'active',
            'payment_status_id' => 1,
        ]);

        Mail::assertNothingOutgoing();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 0);
    }

    public function test_manual_trials_entry_creates_no_email_or_review_draft_even_with_legacy_setting_enabled(): void
    {
        config(['queue.default' => 'database', 'mail.default' => 'array']);
        SiteSetting::set('player_email_on_admin_entry', '1', SiteSetting::GROUP_EMAIL);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $player = Player::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId([
            'name' => 'Interprovincial Trials', 'type' => 1,
            'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE,
        ]);
        $categoryEvent->event->update(['eventType' => $type, 'entryFee' => 100]);
        \App\Models\EventNomination::create([
            'event_id' => $categoryEvent->event_id,
            'category_event_id' => $categoryEvent->id,
            'player_id' => $player->id,
        ]);

        $entry = app(\App\Domain\Entries\Services\EntryService::class)->addPlayerAsAdmin(
            $categoryEvent->fresh(), $player->id, $admin, 'paid_privately'
        );

        $this->assertSame('active', $entry->status);
        $this->assertSame(1, (int) $entry->payment_status_id);
        $this->assertSame('paid', $entry->admin_payment_status);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
