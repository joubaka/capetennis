<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Agreement;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Player;
use App\Models\User;
use App\Services\SuperAdminMailHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminWorkspaceLoadingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    public function test_non_finance_and_non_mail_pages_do_not_build_hidden_ledgers_or_mail_reports(): void
    {
        $this->mock(FinancialLedgerService::class)->shouldNotReceive('buildFySummaryRow');
        $this->mock(SuperAdminMailHistory::class)->shouldNotReceive('data');
        $this->actingAs($this->admin());
        foreach (['overview', 'settings', 'wallets', 'players', 'agreements', 'withdrawals', 'audit', 'disciplinary'] as $tab) {
            $response = $this->get(route('backend.superadmin.workspace', ['tab' => $tab]));
            $response->assertOk()->assertViewHas('selectedTab', $tab)
                ->assertViewMissing('financeSummary')->assertViewMissing('mailLogs')
                ->assertSee('id="sa-pane-'.$tab.'"', false)
                ->assertDontSee('id="sa-pane-finance"', false)
                ->assertDontSee('id="sa-pane-mails"', false);
            if ($tab !== 'settings') $response->assertViewMissing('generalSettings')->assertDontSee('id="sa-settings-form"', false);
            if ($tab !== 'wallets') $response->assertViewMissing('wallets')->assertDontSee('id="modal-wallet-add-tx"', false);
        }
    }

    public function test_invalid_or_array_tab_falls_back_without_loading_hidden_datasets(): void
    {
        $this->mock(FinancialLedgerService::class)->shouldNotReceive('buildFySummaryRow');
        $this->mock(SuperAdminMailHistory::class)->shouldNotReceive('data');
        $this->actingAs($this->admin());
        foreach (['unknown', ['finance'], ''] as $tab) {
            $this->get(route('backend.superadmin.workspace', ['tab' => $tab, 'wallet_search' => ['ignored']]))
                ->assertOk()->assertViewHas('selectedTab', 'overview')->assertViewMissing('wallets');
        }
    }

    public function test_overview_profile_categories_are_exclusive(): void
    {
        $this->actingAs($this->admin());
        Player::factory()->create(['profile_complete' => true, 'profile_updated_at' => now()]);
        Player::factory()->create(['profile_complete' => true, 'profile_updated_at' => now()->subYears(2)]);
        Player::factory()->create(['profile_complete' => true, 'profile_updated_at' => null]);
        Player::factory()->create(['profile_complete' => false, 'profile_updated_at' => null]);
        $this->get(route('backend.superadmin.workspace'))
            ->assertOk()->assertViewHas('profileStats', fn ($stats) => array_sum($stats) === 4
                && $stats['up_to_date'] === 1 && $stats['needs_update'] === 1
                && $stats['never_updated'] === 1 && $stats['incomplete'] === 1);
    }

    public function test_players_and_agreements_use_true_totals_and_reachable_second_pages(): void
    {
        $this->actingAs($this->admin());
        Player::factory()->count(27)->create(['profile_complete' => false, 'profile_updated_at' => null]);
        $this->get(route('backend.superadmin.workspace', ['tab' => 'players', 'player_page' => 2]))
            ->assertOk()->assertViewHas('playersAttentionCount', 27)
            ->assertViewHas('playersNeedingAttention', fn ($rows) => $rows->total() === 27 && $rows->count() === 2 && $rows->currentPage() === 2)
            ->assertSee('tab=players', false);
        for ($i = 0; $i < 27; $i++) Agreement::create(['title' => 'Agreement '.$i, 'version' => 'v'.$i, 'content' => 'Reviewed text']);
        $this->get(route('backend.superadmin.workspace', ['tab' => 'agreements', 'agreement_page' => 2]))
            ->assertOk()->assertViewHas('agreements', fn ($rows) => $rows->total() === 27 && $rows->count() === 2)
            ->assertSee('tab=agreements', false);
    }

    public function test_wallet_search_uses_server_pagination_and_preserves_search_context(): void
    {
        $this->actingAs($this->admin());
        foreach (User::factory()->count(27)->create(['name' => 'Wallet Pagination Target']) as $user) $user->wallet()->create();
        $this->get(route('backend.superadmin.workspace', ['tab' => 'wallets', 'wallet_search' => 'Pagination', 'wallet_page' => 2]))
            ->assertOk()->assertViewHas('wallets', fn ($rows) => $rows->total() === 27 && $rows->count() === 2 && $rows->first()->transactions_count === 0)
            ->assertSee('wallet_search=Pagination', false)->assertSee('tab=wallets', false)
            ->assertDontSee('id="sa-settings-form"', false);
    }

    public function test_refund_subtab_fetches_only_its_queue_and_uses_full_totals(): void
    {
        $this->actingAs($this->admin());
        CategoryEventRegistration::factory()->count(27)->pendingRefund()->create();
        CategoryEventRegistration::factory()->withdrawn()->create(['refund_method' => 'bank', 'refund_status' => 'completed']);
        $this->get(route('backend.superadmin.workspace', ['tab' => 'withdrawals', 'refund_page' => 2]))
            ->assertOk()->assertViewHas('pendingWithdrawals', 27)->assertViewHas('completedRefundCount', 1)
            ->assertViewHas('withdrawalPendingRefunds', fn ($rows) => $rows->total() === 27 && $rows->count() === 2)
            ->assertViewMissing('withdrawalCompletedRefunds')->assertViewMissing('withdrawalWalletRefunds')
            ->assertDontSee('id="sa-wpane-completed"', false)->assertSee('withdrawal_tab=pending', false);
        $this->get(route('backend.superadmin.workspace', ['tab' => 'withdrawals', 'withdrawal_tab' => 'completed']))
            ->assertOk()->assertViewHas('withdrawalCompletedRefunds', fn ($rows) => $rows->total() === 1)
            ->assertViewMissing('withdrawalPendingRefunds')->assertViewMissing('withdrawalWalletRefunds');
        $this->get(route('backend.superadmin.workspace', ['tab' => 'withdrawals', 'withdrawal_tab' => 'wallet']))
            ->assertOk()->assertViewHas('withdrawalWalletRefunds')->assertViewMissing('withdrawalPendingRefunds');
    }

    public function test_finance_totals_cover_all_year_events_while_only_one_page_is_rendered(): void
    {
        $this->actingAs($this->admin());
        Event::factory()->count(27)->create(['start_date' => '2026-06-01']);
        Event::factory()->create(['start_date' => '2025-06-01']);
        $this->mock(SuperAdminMailHistory::class)->shouldNotReceive('data');
        $this->mock(FinancialLedgerService::class)->shouldReceive('buildFySummaryRow')->times(27)
            ->andReturnUsing(fn ($event) => ['event' => $event, 'total_gross' => 10.01, 'total_income' => 9.01,
                'total_entries' => 1, 'combined_paid_out' => 11.01, 'balance' => -2.00]);
        $this->get(route('backend.superadmin.workspace', ['tab' => 'finance', 'finance_year' => '2026', 'finance_page' => 2]))
            ->assertOk()->assertViewHas('financeEvents', fn ($rows) => $rows->total() === 27 && $rows->count() === 2)
            ->assertViewHas('financeByEvent', fn ($rows) => $rows->count() === 2)
            ->assertViewHas('financeSummary', fn ($totals) => $totals['total_gross'] === 270.27
                && $totals['total_paid_out'] === 297.27 && $totals['balance'] === -54.0 && $totals['total_entries'] == 27)
            ->assertSee('finance_year=2026', false)->assertSee('tab=finance', false);
    }

    public function test_all_workspace_selections_remain_super_user_only(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['finance', 'settings', 'wallets', 'withdrawals', 'audit', 'mails'] as $tab) {
            $this->get(route('backend.superadmin.workspace', ['tab' => $tab]))->assertForbidden();
        }
    }
}
