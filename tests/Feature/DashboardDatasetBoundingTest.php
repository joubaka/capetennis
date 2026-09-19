<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardDatasetBoundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_remote_player_search_and_paginates_wallet_history(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        $unlinkedPlayer = Player::factory()->create([
            'name' => 'UnlinkedDashboardMarker',
        ]);

        foreach (range(1, 12) as $number) {
            WalletTransaction::factory()->for($wallet)->create([
                'meta' => ['reference' => 'dashboard-reference-'.$number],
                'created_at' => now()->addMinutes($number),
            ]);
        }

        $response = $this->actingAs($user)->get(route('backend.dashboard'));

        $response->assertOk()
            ->assertViewMissing('players')
            ->assertViewMissing('users')
            ->assertViewHas('transactions', function ($transactions): bool {
                return $transactions instanceof LengthAwarePaginator
                    && $transactions->getPageName() === 'wallet_page'
                    && $transactions->count() === 10
                    && $transactions->total() === 12;
            })
            ->assertSee('format: \'select2\'', false)
            ->assertSee('<ul class="pagination">', false)
            ->assertSee('class="page-item', false)
            ->assertDontSee($unlinkedPlayer->name);

        $this->actingAs($user)
            ->get(route('backend.dashboard', ['wallet_page' => 2]))
            ->assertOk()
            ->assertViewHas('transactions', fn ($transactions): bool =>
                $transactions->currentPage() === 2 && $transactions->count() === 2
            );
    }

    public function test_user_search_is_super_user_only_paginated_and_minimal(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $ordinaryUser = User::factory()->create();
        $superUser = User::factory()->create()->assignRole('super-user');

        User::factory()->count(21)->create([
            'userName' => 'SearchableAdmin',
            'userSurname' => 'Candidate',
        ]);

        $this->actingAs($ordinaryUser)
            ->getJson(route('backend.user.search', ['q' => 'SearchableAdmin']))
            ->assertForbidden();

        $firstPage = $this->actingAs($superUser)
            ->getJson(route('backend.user.search', ['q' => 'SearchableAdmin', 'page' => 1]));

        $firstPage->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('results.0.text', 'SearchableAdmin Candidate')
            ->assertJsonPath('pagination.more', true)
            ->assertJsonStructure([
                'results' => [['id', 'text']],
                'pagination' => ['more'],
            ]);

        $this->assertSame(['id', 'text'], array_keys($firstPage->json('results.0')));

        $this->actingAs($superUser)
            ->getJson(route('backend.user.search', ['q' => 'SearchableAdmin', 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('pagination.more', false);
    }

    public function test_dashboard_event_admin_remote_select_preserves_scalar_request_contract(): void
    {
        $modal = view('_partials._modals.modal-add-event', [
            'eventTypes' => collect(),
        ])->render();

        $this->assertStringContainsString('name="admins"', $modal);
        $this->assertStringNotContainsString('name="admins[]"', $modal);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]+id="select2user"[^>]+multiple/', $modal);
    }
}
