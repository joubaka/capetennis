<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Domain\Teams\Services\ExternalTeamRosterService;
use App\Http\Controllers\Backend\TeamController;
use App\Models\Event;
use App\Models\User;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeamPaymentRecoveryTest extends TestCase
{
    private const BUSY_MESSAGE = 'Someone is busy processing a payment. Please wait five minutes before trying again.';

    protected function setUp(): void
    {
        parent::setUp();
        // Only these read models are needed; avoid the shared MySQL test database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        foreach (['events', 'teams', 'players'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->id();
                if ($table === 'events') {
                    $blueprint->decimal('entryFee', 10, 2);
                }
            });
        }
        foreach (['team_players', 'no_profile_team_players'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->unsignedInteger('team_id');
                $blueprint->integer('rank');
            });
        }
        DB::table('events')->insert(['id' => 41, 'entryFee' => 490]);
        DB::table('teams')->insert(['id' => 52]);
        DB::table('players')->insert(['id' => 63]);
        $this->actingAs((new User)->forceFill(['id' => 74])->setRelation('roles', collect()));
        $this->mock(ExternalTeamRosterService::class, function ($mock) {
            $mock->shouldReceive('assertSelectedRole')->once();
            $mock->shouldReceive('assertCanRegister')->once();
        });
        $invitations = \Mockery::mock(app(TeamSelectionInvitationService::class));
        $invitations->shouldReceive('beginRegistration')->once()->with(41, 52, 63, \Mockery::type(User::class))->andReturnNull();
        $this->instance(TeamSelectionInvitationService::class, $invitations);
    }

    public function test_busy_checkout_redirects_to_scoped_popup_without_generic_errors(): void
    {
        $this->freezeTime();
        $this->mock(TeamPaymentService::class, function ($mock) {
            $mock->shouldReceive('ensureOrder')->once()->andThrow(ValidationException::withMessages(['payment' => self::BUSY_MESSAGE]));
            $mock->shouldReceive('pendingRestartContext')->once()->andReturn(['order' => 85, 'state' => str_repeat('a', 64), 'actor' => 74]);
        });

        $response = app(TeamController::class)->team_payment_payfast(Request::create('/team/payment/52/63/41'), 52, 63, 41);

        $this->assertSame(route('events.show', 41), $response->getTargetUrl());
        $context = session('team_payment_recovery');
        $this->assertSame(41, $context['event_id']);
        $this->assertSame(self::BUSY_MESSAGE, $context['message']);
        $signedRequest = Request::create($context['restart_url'], 'POST');
        $this->assertTrue(\Illuminate\Support\Facades\URL::hasValidSignature($signedRequest));
        $this->assertSame('85', $signedRequest->query('expected_order'));
        $this->assertSame(str_repeat('a', 64), $signedRequest->query('expected_state'));
        $this->assertFalse(session()->has('errors'));

        $html = view('frontend.event.partials.team-payment-recovery', ['event' => (new Event)->forceFill(['id' => 41])])->render();
        $this->assertStringContainsString('Restart registration', $html);
        $this->assertStringContainsString('name="confirm_restart"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringNotContainsString(' href=', $html);
        $this->assertStringContainsString('If you have already paid, do not pay again.', $html);
        $this->assertStringContainsString('This cannot stop a payment already open at PayFast.', $html);
        $otherEventHtml = view('frontend.event.partials.team-payment-recovery', ['event' => (new Event)->forceFill(['id' => 99])])->render();
        $this->assertStringNotContainsString('teamPaymentRecoveryModal', $otherEventHtml);
    }

    public function test_other_payment_validation_keeps_existing_exception_handling(): void
    {
        $this->mock(TeamPaymentService::class, function ($mock) {
            $mock->shouldReceive('ensureOrder')->once()->andThrow(ValidationException::withMessages(['payment' => 'This checkout contains payment evidence and cannot be restarted by another payer.']));
        });
        $this->expectException(ValidationException::class);
        app(TeamController::class)->team_payment_payfast(Request::create('/team/payment/52/63/41'), 52, 63, 41);
    }
}
