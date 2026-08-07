<?php

namespace Tests\Feature;

use App\Mail\DailyWithdrawalSummaryMail;
use App\Mail\WithdrawalAdminMail;
use App\Models\CategoryEventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WithdrawalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    public function test_immediate_notification_goes_to_super_user_and_relevant_event_admin_once(): void
    {
        Mail::fake();

        $superUser = User::factory()->create(['email' => 'super@example.test']);
        $superUser->assignRole('super-user');
        $eventAdmin = User::factory()->create(['email' => 'admin@example.test']);
        $otherAdmin = User::factory()->create(['email' => 'other@example.test']);
        $registration = CategoryEventRegistration::factory()->withdrawn()->create();
        $event = $registration->categoryEvent->event;

        DB::table('event_admins')->insert([
            ['event_id' => $event->id, 'user_id' => $eventAdmin->id],
            ['event_id' => $event->id, 'user_id' => $superUser->id],
        ]);

        $registration->sendWithdrawalEmails();

        Mail::assertQueued(WithdrawalAdminMail::class, 2);
        Mail::assertQueued(WithdrawalAdminMail::class, fn ($mail) => $mail->hasTo($superUser->email));
        Mail::assertQueued(WithdrawalAdminMail::class, fn ($mail) => $mail->hasTo($eventAdmin->email));
        Mail::assertNotQueued(WithdrawalAdminMail::class, fn ($mail) => $mail->hasTo($otherAdmin->email));
    }

    public function test_daily_summary_is_event_scoped_and_only_reports_previous_day(): void
    {
        Carbon::setTestNow('2026-08-07 06:00:00');
        Mail::fake();

        $superUser = User::factory()->create(['email' => 'super@example.test']);
        $superUser->assignRole('super-user');
        $eventAdmin = User::factory()->create(['email' => 'admin@example.test']);
        $otherAdmin = User::factory()->create(['email' => 'other@example.test']);

        $included = CategoryEventRegistration::factory()->withdrawn()->create([
            'withdrawn_at' => '2026-08-06 14:30:00',
        ]);
        $event = $included->categoryEvent->event;
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $eventAdmin->id]);

        CategoryEventRegistration::factory()->withdrawn()->create([
            'category_event_id' => $included->category_event_id,
            'withdrawn_at' => '2026-08-07 01:00:00',
        ]);

        $otherEventWithdrawal = CategoryEventRegistration::factory()->withdrawn()->create([
            'withdrawn_at' => '2026-08-05 14:30:00',
        ]);
        DB::table('event_admins')->insert([
            'event_id' => $otherEventWithdrawal->categoryEvent->event_id,
            'user_id' => $otherAdmin->id,
        ]);

        $this->artisan('withdrawals:send-daily-summary')->assertSuccessful();

        Mail::assertQueued(DailyWithdrawalSummaryMail::class, 2);
        Mail::assertQueued(DailyWithdrawalSummaryMail::class, function ($mail) use ($superUser, $event, $included) {
            return $mail->hasTo($superUser->email)
                && $mail->event->is($event)
                && $mail->withdrawals->count() === 1
                && $mail->withdrawals->first()->is($included);
        });
        Mail::assertQueued(DailyWithdrawalSummaryMail::class, fn ($mail) => $mail->hasTo($eventAdmin->email));
        Mail::assertNotQueued(DailyWithdrawalSummaryMail::class, fn ($mail) => $mail->hasTo($otherAdmin->email));
    }

    public function test_daily_summary_sends_nothing_when_there_were_no_withdrawals(): void
    {
        Carbon::setTestNow('2026-08-07 06:00:00');
        Mail::fake();

        $this->artisan('withdrawals:send-daily-summary')
            ->expectsOutput('No withdrawals to report.')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
