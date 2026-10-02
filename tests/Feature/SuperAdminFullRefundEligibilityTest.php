<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminFullRefundEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function paidEntry(Event $event, string $paymentId, array $overrides = [], bool $test = false): CategoryEventRegistration
    {
        $entry = CategoryEventRegistration::factory()->paid()->create($overrides + [
            'category_event_id' => \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id])->id,
            'payment_method' => 'payfast', 'pf_transaction_id' => $paymentId,
        ]);
        DB::table('transactions_pf')->insert(['pf_payment_id' => $paymentId, 'event_id' => $event->id,
            'category_event_id' => $entry->category_event_id, 'registration_id' => $entry->registration_id,
            'transaction_type' => 'Registration', 'payment_status' => 'COMPLETE', 'amount_gross' => 285,
            'is_test' => $test, 'created_at' => now(), 'updated_at' => now()]);
        return $entry;
    }

    public function test_real_payfast_and_hybrid_transactions_resolve_lazily_eagerly_and_in_existence_queries(): void
    {
        $event = Event::factory()->create();
        $payfast = $this->paidEntry($event, 'PF-REAL');
        $hybrid = $this->paidEntry($event, 'PF-HYBRID', ['payment_method' => 'hybrid']);
        $this->assertSame('PF-REAL', $payfast->fresh()->payfastTransaction->pf_payment_id);
        $this->assertSame('PF-HYBRID', $hybrid->fresh()->payfastTransaction->pf_payment_id);
        $loaded = CategoryEventRegistration::with('payfastTransaction')->findMany([$payfast->id, $hybrid->id])->keyBy('id');
        $this->assertSame('PF-REAL', $loaded[$payfast->id]->payfastTransaction->pf_payment_id);
        $this->assertSame('PF-HYBRID', $loaded[$hybrid->id]->payfastTransaction->pf_payment_id);
        $this->assertEqualsCanonicalizing([$payfast->id, $hybrid->id], CategoryEventRegistration::whereHas('payfastTransaction')->pluck('id')->all());
    }

    public function test_wallet_and_missing_or_blank_payment_ids_have_no_provider_relation(): void
    {
        $event = Event::factory()->create();
        $real = $this->paidEntry($event, 'PF-SHARED');
        $wallet = CategoryEventRegistration::factory()->paid()->create(['payment_method' => 'wallet', 'pf_transaction_id' => 'PF-SHARED']);
        $missing = CategoryEventRegistration::factory()->create(['payment_method' => 'payfast', 'pf_transaction_id' => null]);
        $blank = CategoryEventRegistration::factory()->create(['payment_method' => 'payfast', 'pf_transaction_id' => '']);
        DB::table('transactions_pf')->insert(['pf_payment_id' => '', 'amount_gross' => 285, 'is_test' => false]);
        foreach ([$wallet, $missing, $blank] as $entry) {
            $this->assertNull($entry->fresh()->payfastTransaction);
            $this->assertNull(CategoryEventRegistration::with('payfastTransaction')->findOrFail($entry->id)->payfastTransaction);
        }
        $this->assertSame([$real->id], CategoryEventRegistration::whereHas('payfastTransaction')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$wallet->id, $missing->id, $blank->id], CategoryEventRegistration::whereDoesntHave('payfastTransaction')->pluck('id')->all());
        $counts = CategoryEventRegistration::withCount('payfastTransaction')->findMany([$real->id, $wallet->id])->keyBy('id');
        $this->assertSame(1, $counts[$real->id]->payfast_transaction_count);
        $this->assertSame(0, $counts[$wallet->id]->payfast_transaction_count);
    }

    public function test_full_refunds_tab_lists_real_uncompleted_event_entry_and_excludes_other_records(): void
    {
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $eligible = $this->paidEntry($event, 'PF-ELIGIBLE');
        $this->paidEntry($event, 'PF-TEST', test: true);
        $this->paidEntry($event, 'PF-COMPLETED', ['refund_status' => 'completed']);
        $this->paidEntry($event, 'PF-UNPAID', ['payment_status_id' => null]);
        $this->paidEntry(Event::factory()->create(), 'PF-OTHER-EVENT');
        $this->actingAs($admin)->get(route('superadmin.finances.event', $event))
            ->assertSuccessful()
            ->assertViewHas('eligibleForRefund', fn ($entries) => $entries->pluck('id')->all() === [$eligible->id]
                && $entries->first()->payfastTransaction?->pf_payment_id === 'PF-ELIGIBLE')
            ->assertSee('Full Refunds')
            ->assertSee(route('superadmin.finances.full-refund.registration', [$event, $eligible]), false);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('active', $eligible->fresh()->status);
    }
}
