<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminFinancePayoutDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->superUser = User::factory()->create();
        $this->superUser->assignRole('super-user');
    }

    public function test_payout_form_defaults_to_remaining_balance_convenor_entry_fees_bank_and_today(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 15.00]);
        $convenorUser = User::factory()->create(['name' => 'Primary Convenor']);
        $convenor = EventConvenor::create([
            'event_id' => $event->id,
            'user_id' => $convenorUser->id,
            'role' => 'hoof',
        ]);

        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF-PAYOUT-DEFAULT',
            'event_id' => $event->id,
            'transaction_type' => 'Registration',
            'amount_gross' => 300.00,
            'is_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        EventPayout::create([
            'event_id' => $event->id,
            'convenor_id' => $convenor->id,
            'amount' => 25.00,
            'description' => 'Part payment',
            'payment_method' => 'bank_transfer',
            'paid_by' => $this->superUser->id,
            'paid_at' => now(),
        ]);

        $expectedBalance = app(FinancialLedgerService::class)
            ->buildForEvent($event)['totals']['registration_balance'];

        $this->actingAs($this->superUser)
            ->get(route('superadmin.finances.event', $event))
            ->assertSuccessful()
            ->assertViewHas('defaultPayoutAmount', $expectedBalance)
            ->assertViewHas('defaultConvenor', fn ($default) => $default?->is($convenor))
            ->assertSee('Primary Convenor')
            ->assertSee('Entry fees')
            ->assertSee('Bank Transfer')
            ->assertSee(now()->format('Y-m-d'));
    }

    public function test_event_admin_is_default_recipient_when_event_has_no_convenor(): void
    {
        $event = Event::factory()->create();
        $admin = User::factory()->create(['name' => 'Event Administrator']);
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($this->superUser)
            ->get(route('superadmin.finances.event', $event))
            ->assertSuccessful()
            ->assertViewHas('defaultConvenor', null)
            ->assertViewHas('defaultAdmin', fn ($default) => $default?->is($admin))
            ->assertSee('Event Administrator')
            ->assertSee('Event admin');
    }

    public function test_payout_rejects_convenor_from_another_event(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $otherConvenor = EventConvenor::create([
            'event_id' => $otherEvent->id,
            'user_id' => User::factory()->create()->id,
            'role' => 'hoof',
        ]);

        $this->actingAs($this->superUser)
            ->from(route('superadmin.finances.event', $event))
            ->post(route('superadmin.finances.payout.store', $event), [
                'convenor_id' => $otherConvenor->id,
                'amount' => 100,
                'description' => 'Entry fees',
                'payment_method' => 'bank_transfer',
                'paid_at' => now()->format('Y-m-d'),
            ])
            ->assertRedirect(route('superadmin.finances.event', $event))
            ->assertSessionHasErrors('convenor_id');

        $this->assertDatabaseCount('event_payouts', 0);
    }
}
