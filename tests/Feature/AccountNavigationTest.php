<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, View};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_navigation_uses_accessible_own_account_destinations_for_each_role(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->app->instance(\App\Services\Performance\PendingBankRefundCount::class, new class {
            public function count(): int { return 4; }
        });
        foreach ([null, 'admin', 'super-user'] as $role) {
            $user = User::factory()->create();
            if ($role) $user->assignRole(Role::findOrCreate($role, 'web'));
            $player = Player::factory()->create(['userId' => $user->id]);
            $this->actingAs($user);
            $html = View::make('layouts.sections.navbar.navbar', ['navbarFull' => true, 'pendingBankRefundCount' => 4])->render();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//button[@id="account-menu-toggle"][@aria-expanded="false"]')->length);
            $this->assertStringContainsString(route('backend.dashboard', ['manage_players' => 1]).'#dashboard-account', $html);
            $this->assertStringContainsString(route('backend.dashboard', ['wallet_page' => 1]).'#wallet-history', $html);
            $this->assertStringNotContainsString(route('wallet.show', $user->id), $html);
            $this->assertSame($role === 'super-user' ? 1 : 0, $xpath->query('//a[@href="'.route('admin.refunds.bank.index').'"][@data-super-admin-only]')->length);
            if ($role !== 'super-user') $this->assertStringNotContainsString('Bank refunds', $html);
            $response = $this->get(route('backend.dashboard', ['wallet_page' => 1]))->assertOk()
                ->assertSee('id="wallet-history"', false)
                ->assertSee(route('my.tennis', ['player' => $player->id, 'tab' => 'profile']))
                ->assertDontSee(route('wallet.show', $user->id), false);
            if (getenv('CT_ACCOUNT_NAV_QA_HTML') === '1') {
                File::ensureDirectoryExists(storage_path('framework/testing/account-navigation'));
                file_put_contents(storage_path('framework/testing/account-navigation/'.($role ?? 'standard').'.html'), $response->getContent());
            }
        }
    }

    public function test_guest_account_menu_does_not_link_to_protected_account_or_wallet(): void
    {
        $html = View::make('layouts.sections.navbar.navbar', ['navbarFull' => true])->render();
        $this->assertStringNotContainsString('My Account', $html);
        $this->assertStringNotContainsString('My Wallet', $html);
        $this->assertStringNotContainsString(route('backend.dashboard'), $html);
        $this->assertStringContainsString(route('login'), $html);
        if (getenv('CT_ACCOUNT_NAV_QA_HTML') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing/account-navigation'));
            file_put_contents(storage_path('framework/testing/account-navigation/guest.html'), $this->get(route('home'))->assertOk()->getContent());
        }
    }

    public function test_non_super_accounts_do_not_query_global_refund_totals(): void
    {
        $this->app->instance(\App\Services\Performance\PendingBankRefundCount::class, new class {
            public function count(): int { throw new \LogicException('Non-super navigation must not query global refund totals.'); }
        });
        foreach ([null, 'admin'] as $role) {
            $user = User::factory()->create();
            if ($role) $user->assignRole(Role::findOrCreate($role, 'web'));
            $this->actingAs($user);
            $html = View::make('layouts.sections.navbar.navbar', ['navbarFull' => true])->render();
            $this->assertStringNotContainsString('Bank refunds', $html);
        }
    }
}
