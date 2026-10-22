<?php

namespace Tests\Feature;

use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\RegionalClothingSettlement;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminCombinedBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_workspace_includes_clothing_payouts_and_retains_negative_balance(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 12:00:00'));
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create(['start_date' => '2026-06-01']);
        ClothingOrder::create(['event_id' => $event->id, 'user_id' => $admin->id,
            'pay_status' => 1, 'status' => 'completed', 'total' => 100, 'amount_paid' => 100,
            'payfast_fee' => 0, 'subtotal' => 100]);
        EventPayout::create(['event_id' => $event->id, 'amount' => 10,
            'paid_by' => $admin->id, 'paid_at' => now()]);
        $region = TeamRegion::create(['region_name' => 'Synthetic']);
        $scope = DB::table('event_regions')->insertGetId(['event_id' => $event->id,
            'region_id' => $region->id, 'ordering' => 1]);
        // Use a saved settlement fixture without executing any financial service.
        RegionalClothingSettlement::create(['event_id' => $event->id, 'event_region_id' => $scope,
            'region_id' => $region->id, 'region_name' => 'Synthetic', 'kind' => 'payout',
            'amount' => 120, 'recorded_by' => $admin->id, 'recorded_by_name' => 'Synthetic',
            'recipient_name' => 'Synthetic recipient', 'occurred_at' => now(), 'reference' => 'QA',
            'evidence' => 'Synthetic saved payout', 'request_token' => Str::uuid(),
            'request_fingerprint' => str_repeat('b', 64)]);

        $this->actingAs($admin)->get(route('backend.superadmin.workspace', ['finance_year' => '2026']))
            ->assertOk()
            ->assertViewHas('financeSummary', fn ($summary) =>
                $summary['total_paid_out'] === 130.0 && $summary['balance'] === -30.0)
            ->assertSee('R 130.00')
            ->assertSee('R -30.00');
        $this->getJson(route('backend.superadmin.balance'))
            ->assertOk()
            ->assertJsonPath('balance', '-30.00');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
        $this->assertDatabaseCount('event_payouts', 1);
    }
}
