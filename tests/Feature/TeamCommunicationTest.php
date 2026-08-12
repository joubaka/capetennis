<?php

namespace Tests\Feature;

use App\Mail\TeamActionMail;
use App\Events\PaymentCompleted;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\TeamCommunicationService;
use App\Listeners\SendTeamRegistrationConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_confirmation_is_queued_to_payer(): void
    {
        Mail::fake();
        $order = $this->order();

        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));

        Mail::assertQueued(TeamActionMail::class, fn ($mail) =>
            $mail->hasTo($order->user->email) && $mail->action === 'registration'
        );
    }

    public function test_withdrawal_notifies_payer_super_users_and_event_admins_once(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $order = $this->order();
        $super = User::factory()->create(['email' => 'super@example.test'])->assignRole('super-user');
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        DB::table('event_admins')->insert(['event_id' => $order->event_id, 'user_id' => $admin->id]);

        app(TeamCommunicationService::class)->withdrawal($order->fresh(), ['refund_available' => true]);

        Mail::assertQueuedCount(3);
        foreach ([$order->user->email, $super->email, $admin->email] as $email) {
            Mail::assertQueued(TeamActionMail::class, fn ($mail) => $mail->hasTo($email));
        }
    }

    public function test_disabled_team_registration_email_is_not_queued(): void
    {
        Mail::fake();
        SiteSetting::set('player_email_on_team_registration', '0', SiteSetting::GROUP_EMAIL);
        $order = $this->order();

        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));

        Mail::assertNothingQueued();
    }

    public function test_player_and_admin_team_withdrawal_switches_apply_independently(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $order = $this->order();
        $super = User::factory()->create(['email' => 'super@example.test'])->assignRole('super-user');
        SiteSetting::set('player_email_on_team_withdrawal', '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('email_on_team_withdrawal', '1', SiteSetting::GROUP_EMAIL);

        app(TeamCommunicationService::class)->withdrawal($order->fresh());

        Mail::assertQueued(TeamActionMail::class, fn ($mail) => $mail->hasTo($super->email));
        Mail::assertNotQueued(TeamActionMail::class, fn ($mail) => $mail->hasTo($order->user->email));
    }

    private function order(): TeamPaymentOrder
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $team = Team::factory()->create();

        return TeamPaymentOrder::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'total_amount' => 285,
            'pay_status' => 1,
        ]);
    }
}
