<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\CategoryEventRegistration;
use App\Models\RegistrationOrder;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Legacy Adapter Integration Tests
 *
 * Verifies that legacy HTTP controllers route through the canonical financial
 * services rather than manipulating payment state directly.
 *
 * Controllers under test:
 *   - RegistrationPaymentController  → PaymentOrchestrator
 *   - BankRefundController           → RefundExecutionService
 *   - RegisterController (notify)    → PaymentOrchestrator
 */
class LegacyAdapterCallsCanonicalServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // =========================================================================
    // RegistrationPaymentController::hybridPay()
    // → must call PaymentOrchestrator::initiatePayment()
    // =========================================================================

    public function test_hybrid_pay_calls_payment_orchestrator_initiate_payment(): void
    {
        $user  = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        // Fund the wallet so the balance check passes
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 200,
            'source_type' => 'test_seed',
            'source_id'   => 0,
            'meta'        => [],
        ]);

        $order = RegistrationOrder::create([
            'user_id'            => $user->id,
            'wallet_reserved'    => 0,
            'wallet_debited'     => false,
            'payfast_paid'       => false,
            'payfast_amount_due' => 100,
            'pay_status'         => false,
        ]);

        $mock = Mockery::mock(PaymentOrchestrator::class)->makePartial();
        $mock->shouldReceive('initiatePayment')
            ->once()
            ->andReturn($order);
        app()->instance(PaymentOrchestrator::class, $mock);

        $this->actingAs($user)->post(route('registration.hybrid.pay'), [
            'type'             => 'registration',
            'custom_int5'      => $order->id,
            'wallet_applied'   => 50,
            'remaining_amount' => 50,
        ]);

        // Mockery assertion happens via shouldReceive()->once() above
        $this->assertTrue(true); // If we got here, mock was satisfied
    }

    public function test_hybrid_pay_does_not_call_initiate_when_order_already_paid(): void
    {
        $user  = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        $order = RegistrationOrder::create([
            'user_id'            => $user->id,
            'wallet_reserved'    => 0,
            'wallet_debited'     => false,
            'payfast_paid'       => true,  // already paid
            'payfast_amount_due' => 0,
            'pay_status'         => true,
        ]);

        $mock = Mockery::mock(PaymentOrchestrator::class);
        $mock->shouldNotReceive('initiatePayment');
        app()->instance(PaymentOrchestrator::class, $mock);

        $response = $this->actingAs($user)->post(route('registration.hybrid.pay'), [
            'type'             => 'registration',
            'custom_int5'      => $order->id,
            'wallet_applied'   => 50,
            'remaining_amount' => 50,
        ]);

        // Must redirect to success page, not call the orchestrator
        $response->assertRedirect();
    }

    // =========================================================================
    // BankRefundController::complete()
    // → must call RefundExecutionService::executeBankRefund()
    // =========================================================================

    public function test_bank_refund_complete_calls_execution_service(): void
    {
        $superUser = $this->makeSuperUser();

        // Registration with NO PayFast transaction — triggers manual path
        $reg = CategoryEventRegistration::factory()->create([
            'refund_method' => 'bank',
            'refund_status' => 'pending',
            'pf_transaction_id' => null,
        ]);

        $mock = Mockery::mock(RefundExecutionService::class);
        $mock->shouldReceive('executeBankRefund')
            ->once()
            ->with(Mockery::on(fn ($r) => $r->id === $reg->id), Mockery::any())
            ->andReturn($reg);
        app()->instance(RefundExecutionService::class, $mock);

        $this->actingAs($superUser)
            ->post(route('admin.refunds.bank.complete', $reg));

        $this->assertTrue(true);
    }

    public function test_bank_refund_complete_returns_error_when_already_processed(): void
    {
        $superUser = $this->makeSuperUser();

        $reg = CategoryEventRegistration::factory()->create([
            'refund_method' => 'bank',
            'refund_status' => 'completed', // already done
        ]);

        $mock = Mockery::mock(RefundExecutionService::class);
        $mock->shouldNotReceive('executeBankRefund');
        app()->instance(RefundExecutionService::class, $mock);

        $response = $this->actingAs($superUser)
            ->post(route('admin.refunds.bank.complete', $reg));

        $response->assertSessionHasErrors();
    }

    public function test_bank_refund_complete_returns_error_when_wrong_method(): void
    {
        $superUser = $this->makeSuperUser();

        $reg = CategoryEventRegistration::factory()->create([
            'refund_method' => 'wallet', // wrong method
            'refund_status' => 'pending',
        ]);

        $mock = Mockery::mock(RefundExecutionService::class);
        $mock->shouldNotReceive('executeBankRefund');
        app()->instance(RefundExecutionService::class, $mock);

        $response = $this->actingAs($superUser)
            ->post(route('admin.refunds.bank.complete', $reg));

        $response->assertSessionHasErrors();
    }

    // =========================================================================
    // BankRefundController::completeTeam()
    // → must call RefundExecutionService::executeBankRefund() for no-PayFast orders
    // =========================================================================

    public function test_bank_refund_complete_team_calls_execution_service_for_manual_refund(): void
    {
        $superUser = $this->makeSuperUser();

        // Team order with NO PayFast payment ID → manual path
        $order = TeamPaymentOrder::create([
            'user_id'               => $superUser->id,
            'total_amount'          => 150,
            'wallet_reserved'       => 0,
            'payfast_amount_due'    => 150,
            'payfast_paid'          => true,
            'payfast_pf_payment_id' => null, // no PF id → manual
            'pay_status'            => true,
            'refund_method'         => 'bank',
            'refund_status'         => 'pending',
            'refund_gross'          => 150,
            'refund_fee'            => 10,
            'refund_net'            => 140,
        ]);

        $mock = Mockery::mock(RefundExecutionService::class);
        $mock->shouldReceive('executeBankRefund')
            ->once()
            ->with(Mockery::on(fn ($o) => $o->id === $order->id), Mockery::any())
            ->andReturn($order);
        app()->instance(RefundExecutionService::class, $mock);

        $this->actingAs($superUser)
            ->post(route('admin.refunds.bank.complete.team', $order));

        $this->assertTrue(true);
    }

    // =========================================================================
    // RegisterController::notify() — PayFast ITN
    // → must call PaymentOrchestrator::finalizePayment()
    //
    // We cannot POST a real ITN (requires valid PayFast signature) so we verify
    // by inspecting the controller source rather than HTTP:
    // =========================================================================

    /**
     * Structural test: RegisterController source code must call
     * app(PaymentOrchestrator::class)->finalizePayment() for hybrid ITN.
     */
    public function test_register_controller_itn_handler_references_payment_orchestrator(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Frontend/RegisterController.php')
        );

        $this->assertStringContainsString(
            'PaymentOrchestrator',
            $source,
            'RegisterController must import or reference PaymentOrchestrator'
        );

        $this->assertStringContainsString(
            'finalizePayment',
            $source,
            'RegisterController notify handler must call finalizePayment()'
        );
    }

    /**
     * Structural test: RegistrationPaymentController source code must call
     * app(PaymentOrchestrator::class)->initiatePayment().
     */
    public function test_registration_payment_controller_references_payment_orchestrator(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Frontend/RegistrationPaymentController.php')
        );

        $this->assertStringContainsString(
            'PaymentOrchestrator',
            $source,
            'RegistrationPaymentController must import or reference PaymentOrchestrator'
        );

        $this->assertStringContainsString(
            'initiatePayment',
            $source,
            'RegistrationPaymentController::hybridPay must call initiatePayment()'
        );
    }

    /**
     * Structural test: BankRefundController must inject RefundExecutionService
     * via constructor injection (canonical service, not legacy direct writes).
     */
    public function test_bank_refund_controller_injects_refund_execution_service(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Backend/BankRefundController.php')
        );

        $this->assertStringContainsString(
            'RefundExecutionService',
            $source,
            'BankRefundController must use RefundExecutionService'
        );

        $this->assertStringContainsString(
            'executeBankRefund',
            $source,
            'BankRefundController must call executeBankRefund()'
        );
    }

    /**
     * Structural test: No legacy direct wallet balance mutation path exists
     * in the production app code (balance is always derived from ledger).
     */
    public function test_no_direct_balance_column_mutation_in_app_code(): void
    {
        $appPath = app_path();
        $files   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appPath)
        );

        $directMutations = [];

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname());

            // Look for direct model attribute assignment: $model->balance = something
            // This is the dangerous pattern that bypasses the ledger.
            // Exclude array key assignments like 'balance' => ... which are reports/summaries.
            if (preg_match('/\$\w+->balance\s*=\s*[^=]/', $content)) {
                $relative = str_replace($appPath . '/', '', $file->getPathname());
                $directMutations[] = $relative;
            }
        }

        $this->assertEmpty(
            $directMutations,
            'Direct model balance attribute mutations found — these bypass the ledger in: ' . implode(', ', $directMutations)
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSuperUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }
}
