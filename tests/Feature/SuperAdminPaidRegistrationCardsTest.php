<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminPaidRegistrationCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cards_count_distinct_paid_registrations_by_registration_date(): void
    {
        $this->travelTo(now()->setDateTime(2026, 3, 15, 12, 0));
        $january = $this->registration('2026-01-01 00:00:00', 1);
        $february = $this->registration('2026-02-01 00:00:00', 1);
        $this->registration('2026-02-28 23:59:59', 1);
        $this->registration('2026-03-01 00:00:00', 1);
        $this->registration('2026-03-15 12:00:00', 1);
        $this->registration('2025-12-31 23:59:59', 1);
        $this->registration('2026-03-15 12:00:01', 1);
        $this->registration('2026-02-10 12:00:00', null);
        $this->registration('2026-02-10 12:00:00', 0);
        $admin = $this->registration('2026-02-11 12:00:00', 1);
        $admin->categoryEventRegistrations()->first()->update(['admin_payment_status' => 'unpaid']);
        $refunded = $this->registration('2026-02-12 12:00:00', 1);
        $refunded->categoryEventRegistrations()->first()->update([
            'status' => 'withdrawn',
            'refund_status' => 'completed',
            'refunded_at' => now(),
        ]);
        Registration::factory()->create(['created_at' => '2026-02-10 12:00:00']);
        CategoryEventRegistration::factory()->paid()->create(['registration_id' => $february->id]);
        // The parent date controls the period, rather than the category entry date.
        CategoryEventRegistration::factory()->paid()->create(['registration_id' => $january->id]);
        $deleted = $this->registration('2026-02-10 12:00:00', 1);
        $deleted->categoryEventRegistrations()->first()->delete();

        $this->dashboard()->assertOk()
            ->assertViewHas('paidRegistrationsThisYear', 7)
            ->assertViewHas('paidRegistrationsLastMonth', 4)
            ->assertSee('Paid registrations · 2026')
            ->assertSee('Paid registrations · February 2026')
            ->assertDontSee('Registrations · 30 days');
    }

    public function test_previous_month_crosses_the_year_boundary_and_empty_counts_are_zero(): void
    {
        $this->travelTo(now()->setDateTime(2026, 1, 15, 12, 0));
        $this->dashboard()->assertOk()
            ->assertViewHas('paidRegistrationsThisYear', 0)
            ->assertViewHas('paidRegistrationsLastMonth', 0);

        $this->registration('2025-11-30 23:59:59', 1);
        $this->registration('2025-12-01 00:00:00', 1);
        $this->registration('2025-12-31 23:59:59', 1);
        $this->registration('2026-01-01 00:00:00', 1);

        $this->dashboard()->assertOk()
            ->assertViewHas('paidRegistrationsThisYear', 1)
            ->assertViewHas('paidRegistrationsLastMonth', 2)
            ->assertSee('Paid registrations · December 2025');
    }

    private function registration(string $createdAt, ?int $paid): Registration
    {
        $registration = Registration::factory()->create(['created_at' => $createdAt]);
        CategoryEventRegistration::factory()->create([
            'registration_id' => $registration->id,
            'payment_status_id' => $paid,
        ]);

        return $registration;
    }

    private function dashboard(): \Illuminate\Testing\TestResponse
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-user');

        return $this->actingAs($user)->get(route('backend.superadmin.index'));
    }
}
