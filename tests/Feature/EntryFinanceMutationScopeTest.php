<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntryFinanceMutationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_entry_uses_scoped_payment_write_without_moving_money(): void
    {
        Event::fake([\App\Domain\Entries\Events\EntryCreated::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $category = CategoryEvent::factory()->create();
        $player = Player::factory()->create();
        Log::spy();

        $entry = app(EntryService::class)->addPlayerAsAdmin($category, $player->id, $admin, 'paid_privately');

        $this->assertSame(1, (int) $entry->payment_status_id);
        $this->assertSame('paid', $entry->admin_payment_status);
        $this->assertSame($category->id, $entry->category_event_id);
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['event_id' => $category->event_id, 'amount_gross' => 0, 'pf_payment_id' => null]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertNoFinancialWarning();
    }

    public function test_player_and_admin_withdrawals_use_scoped_refund_initialization(): void
    {
        Event::fake([\App\Domain\Entries\Events\EntryWithdrawn::class]);
        $owner = User::factory()->create();
        $entries = CategoryEventRegistration::factory()->count(2)->create([
            'user_id' => $owner->id, 'payment_status_id' => 1,
        ]);
        // Newly created admin entries have database refund defaults that have
        // not yet been hydrated onto the in-memory model.
        foreach ($entries as $entry) {
            $entry->setRawAttributes(array_diff_key($entry->getAttributes(), array_flip([
                'refund_status', 'refund_gross', 'refund_fee', 'refund_net',
            ])), true);
        }
        Log::spy();

        app(EntryService::class)->withdrawEntry($entries[0], $owner);
        app(EntryService::class)->withdrawEntryAsAdmin($entries[1], $owner);

        foreach ($entries as $entry) {
            $this->assertDatabaseHas('category_event_registrations', [
                'id' => $entry->id, 'status' => 'withdrawn', 'payment_status_id' => 1,
                'refund_status' => 'not_refunded', 'refund_gross' => 0, 'refund_fee' => 0, 'refund_net' => 0,
            ]);
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertNoFinancialWarning();
    }

    public function test_direct_payment_write_still_warns_outside_service(): void
    {
        $entry = CategoryEventRegistration::factory()->create();
        Log::spy();

        $entry->update(['payment_status_id' => 1]);

        Log::shouldHaveReceived('warning')->once()->with(
            'FINANCE LOCKDOWN: direct financial state mutation detected',
            \Mockery::on(fn ($context) => $context['id'] === $entry->id
                && $context['dirty'] === ['payment_status_id'] && $context['scopes'] === [])
        );
        $this->assertSame([], FinanceMutationScope::current());
    }

    private function assertNoFinancialWarning(): void
    {
        Log::shouldNotHaveReceived('warning', fn ($message) => $message === 'FINANCE LOCKDOWN: direct financial state mutation detected');
        $this->assertSame([], FinanceMutationScope::current());
    }
}
