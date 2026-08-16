<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BankRefundWaiverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    private function superUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super-user');

        return $user;
    }

    public function test_super_user_can_waive_pending_registration_refund_without_recording_payment(): void
    {
        $admin = $this->superUser();
        $refund = CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank',
            'refund_status' => 'pending',
            'refund_net' => 256.50,
            'refunded_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.refunds.bank.waive', $refund), [
                'reason' => 'Customer confirmed that no refund is required.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $refund->refresh();
        $this->assertSame('waived', $refund->refund_status);
        $this->assertSame($admin->id, $refund->refund_waived_by);
        $this->assertSame('Customer confirmed that no refund is required.', $refund->refund_waiver_reason);
        $this->assertNotNull($refund->refund_waived_at);
        $this->assertNull($refund->refunded_at);
    }

    public function test_waiver_requires_a_meaningful_reason(): void
    {
        $refund = CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank',
            'refund_status' => 'pending',
        ]);

        $this->actingAs($this->superUser())
            ->post(route('admin.refunds.bank.waive', $refund), ['reason' => 'no'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $refund->fresh()->refund_status);
    }

    public function test_completed_or_already_waived_refund_cannot_be_waived(): void
    {
        $admin = $this->superUser();
        foreach (['completed', 'waived'] as $status) {
            $refund = CategoryEventRegistration::factory()->withdrawn()->create([
                'refund_method' => 'bank',
                'refund_status' => $status,
            ]);

            $this->actingAs($admin)
                ->post(route('admin.refunds.bank.waive', $refund), [
                    'reason' => 'Duplicate request should be rejected.',
                ])
                ->assertSessionHasErrors('refund');

            $this->assertSame($status, $refund->fresh()->refund_status);
        }
    }

    public function test_non_super_user_cannot_waive_a_refund(): void
    {
        $refund = CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank',
            'refund_status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.refunds.bank.waive', $refund), [
                'reason' => 'This request must not be accepted.',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $refund->fresh()->refund_status);
    }

    public function test_super_user_can_waive_pending_team_refund(): void
    {
        $admin = $this->superUser();
        $order = TeamPaymentOrder::create([
            'user_id' => $admin->id,
            'total_amount' => 285,
            'wallet_reserved' => 0,
            'payfast_amount_due' => 285,
            'wallet_debited' => false,
            'payfast_paid' => true,
            'pay_status' => true,
            'refund_method' => 'bank',
            'refund_status' => 'pending',
            'refund_gross' => 285,
            'refund_fee' => 28.50,
            'refund_net' => 256.50,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.refunds.bank.waive.team', $order), [
                'reason' => 'Team manager waived the refund in writing.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('waived', $order->refund_status);
        $this->assertSame($admin->id, $order->refund_waived_by);
        $this->assertNull($order->refunded_at);
    }

    public function test_waived_refund_leaves_pending_list_and_remains_in_audit_history(): void
    {
        $refund = CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank',
            'refund_status' => 'waived',
            'refund_net' => 90,
            'refund_waived_at' => now(),
            'refund_waived_by' => $this->superUser()->id,
            'refund_waiver_reason' => 'Approved administrative waiver.',
        ]);

        $response = $this->actingAs($this->superUser())
            ->get(route('admin.refunds.bank.index'));

        $response->assertOk()
            ->assertSee('No pending refunds require action')
            ->assertSee('Waived Refunds')
            ->assertSee('Approved administrative waiver.');
    }

    public function test_failed_bulk_payfast_submission_remains_pending(): void
    {
        $refund = CategoryEventRegistration::factory()->withdrawn()->paid()->create([
            'payment_method' => 'payfast',
            'refund_method' => 'bank',
            'refund_status' => 'pending',
            'refund_gross' => 285,
            'refund_fee' => 28.50,
            'refund_net' => 256.50,
        ]);

        DB::table('registration_order_items')->insert([
            'order_id' => 999999,
            'registration_id' => $refund->registration_id,
            'category_event_id' => $refund->category_event_id,
            'item_price' => 285,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'api.payfast.co.za/refunds/*' => Http::response(['message' => 'rejected'], 422),
        ]);

        $this->actingAs($this->superUser())
            ->post(route('admin.refunds.bank.bulk-complete'), [
                'registration_ids' => [$refund->id],
            ])
            ->assertRedirect();

        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertNull($refund->fresh()->refunded_at);
    }
}
